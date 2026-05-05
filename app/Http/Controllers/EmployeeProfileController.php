<?php

namespace App\Http\Controllers;

use App\Models\BudgetHistory;
use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveCardExcelExportService;
use App\Services\LeaveLedgerService;
use App\Services\LeavePolicyService;
use App\Support\BalanceLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeProfileController extends Controller
{
    private const ADMIN_PROFILE_ROLES = ['admin', 'hr', 'personnel'];

    public function __construct(
        private LeaveLedgerService $ledger,
        private LeaveCardExcelExportService $leaveCardExcel,
        private LeavePolicyService $policyService
    ) {
    }

    public function show(Request $request): View|StreamedResponse|Response
    {
        $user = Auth::user();
        $targetId = (int) ($request->query('employee') ?: $request->query('id') ?: ($user->employee?->id ?? 0));
        $employee = Employee::query()->with('user')->findOrFail($targetId);
        abort_unless($this->canView($user->role, $user->employee?->id, $user->employee?->department_id, $user->employee?->department, $employee), 403);

        $history = LeaveRequest::query()
            ->with('leaveTypeRelation')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $budgetHistory = BudgetHistory::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('trans_date')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $used = $this->ledger->usedBalances($employee);
        $leaveCard = $this->ledger->buildLeaveCardRows($employee->id);

        if ($request->query('export') === 'leave_card') {
            return $this->exportLeaveCard($employee, $leaveCard);
        }

        if ($request->query('export') === 'history') {
            return $this->exportHistory($employee, $history);
        }

        return view('profile.show', [
            'employeeProfile' => $employee,
            'history' => $history,
            'budgetHistory' => $budgetHistory,
            'leaveCard' => $leaveCard,
            'used' => $used,
            'isSelfProfile' => $user->employee?->id === $employee->id,
            'canEditPhoto' => $user->employee?->id === $employee->id,
            'canManageProfileHistory' => in_array((string) $user->role, self::ADMIN_PROFILE_ROLES, true),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $viewer = Auth::user();
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'profile_pic' => ['required', 'image', 'max:2048'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        abort_unless(($viewer->employee?->id ?? 0) === $employee->id, 403);

        $file = $request->file('profile_pic');
        if (!$file || !$file->isValid()) {
            return back()->with('error', 'Please choose a valid profile image file.');
        }

        $uploadDir = public_path('uploads');
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = 'profile_' . Str::uuid()->toString() . '.' . $extension;
        $file->move($uploadDir, $filename);

        $employee->profile_pic = 'uploads/' . $filename;
        $employee->save();

        return redirect()->route('employee-profile', ['employee' => $employee->id])->with('success', 'Profile photo updated successfully.');
    }

    public function updateBalances(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'annual_balance' => ['required', 'numeric', 'min:0'],
            'sick_balance' => ['required', 'numeric', 'min:0'],
            'force_balance' => ['required', 'numeric', 'min:0'],
            'wellness_balance' => ['nullable', 'numeric', 'min:0'],
            'spl_balance' => ['nullable', 'numeric', 'min:0'],
            'cto_balance' => ['nullable', 'numeric', 'min:0', 'max:15'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $this->authorizeProfileAdminAction($employee);

        DB::transaction(function () use ($employee, $data) {
            $oldAnnual = (float) $employee->annual_balance;
            $oldSick = (float) $employee->sick_balance;
            $oldForce = (float) $employee->force_balance;
            $oldWellness = (float) ($employee->wellness_balance ?? 0);
            $oldSpl = (float) ($employee->spl_balance ?? 0);
            $oldCto = (float) ($employee->cto_balance ?? 0);

            $newAnnual = $this->trunc3((float) $data['annual_balance']);
            $newSick = $this->trunc3((float) $data['sick_balance']);
            $newForce = $this->trunc3((float) $data['force_balance']);
            $newWellness = $this->trunc3((float) ($data['wellness_balance'] ?? $oldWellness));
            $newSpl = $this->trunc3((float) ($data['spl_balance'] ?? $oldSpl));
            $newCto = min(15.0, $this->trunc3((float) ($data['cto_balance'] ?? $oldCto)));

            $employee->update([
                'annual_balance' => $newAnnual,
                'sick_balance' => $newSick,
                'force_balance' => $newForce,
                'wellness_balance' => $newWellness,
                'spl_balance' => $newSpl,
                'cto_balance' => $newCto,
            ]);

            if ($this->balancesDiffer($oldAnnual, $newAnnual)) {
                BalanceLedger::logBudgetChange($employee->id, 'Annual', $oldAnnual, $newAnnual, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if ($this->balancesDiffer($oldSick, $newSick)) {
                BalanceLedger::logBudgetChange($employee->id, 'Sick', $oldSick, $newSick, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if ($this->balancesDiffer($oldForce, $newForce)) {
                BalanceLedger::logBudgetChange($employee->id, 'Force', $oldForce, $newForce, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if ($this->balancesDiffer($oldWellness, $newWellness)) {
                BalanceLedger::logBudgetChange($employee->id, 'Wellness', $oldWellness, $newWellness, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if ($this->balancesDiffer($oldSpl, $newSpl)) {
                BalanceLedger::logBudgetChange($employee->id, 'SPL', $oldSpl, $newSpl, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if ($this->balancesDiffer($oldCto, $newCto)) {
                BalanceLedger::logBudgetChange($employee->id, 'CTO', $oldCto, $newCto, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
        });

        return $this->redirectToProfile($employee->id)->with('success', 'Employee balances updated successfully.');
    }

    public function recordUndertime(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'items' => ['nullable', 'array'],
            'items.*.date' => ['required_with:items', 'date'],
            'items.*.hours' => ['nullable', 'integer', 'min:0'],
            'items.*.undertime_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'date' => ['nullable', 'date'],
            'hours' => ['nullable', 'integer', 'min:0'],
            'undertime_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'with_pay' => ['nullable'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $this->authorizeProfileAdminAction($employee);

        $items = $data['items'] ?? [];
        if (empty($items) && !empty($data['date'])) {
            $items = [[
                'date' => $data['date'],
                'hours' => $data['hours'] ?? 0,
                'undertime_minutes' => $data['undertime_minutes'] ?? 0,
            ]];
        }

        $validItems = [];
        $totalDeduct = 0.0;
        $totalMinutes = 0;
        foreach ($items as $item) {
            $hours = (int) ($item['hours'] ?? 0);
            $minutes = (int) ($item['undertime_minutes'] ?? 0);
            if (($hours * 60) + $minutes <= 0) {
                continue;
            }
            $deduct = $this->trunc3(BalanceLedger::undertimeDaysFromChart($hours, $minutes));
            $validItems[] = ['date' => (string) $item['date'], 'hours' => $hours, 'minutes' => $minutes, 'deduct' => $deduct];
            $totalDeduct = $this->trunc3($totalDeduct + $deduct);
            $totalMinutes += ($hours * 60) + $minutes;
        }

        if (empty($validItems) || $totalDeduct <= 0) {
            return $this->redirectToProfile($employee->id)->with('error', 'Undertime minutes required.');
        }

        DB::transaction(function () use ($employee, $validItems, $totalDeduct, $totalMinutes, $request) {
            $oldAnnual = (float) $employee->annual_balance;
            $sickBalance = (float) $employee->sick_balance;
            $forceBalance = (float) $employee->force_balance;
            $withPay = $request->boolean('with_pay');
            $paidDeduct = min($oldAnnual, $totalDeduct);
            $withoutPayDeduct = max(0.0, $totalDeduct - $paidDeduct);
            $newAnnual = $this->trunc3(max(0, $oldAnnual - $paidDeduct));

            $employee->update(['annual_balance' => $newAnnual]);

            $dates = collect($validItems)->map(fn ($row) => \Carbon\Carbon::parse($row['date'])->format('m/d'))->implode(', ');
            $meta = 'UT_DEDUCT=' . number_format($totalDeduct, 3, '.', '') .
                ';UT_WITH_PAY=' . number_format($paidDeduct, 3, '.', '') .
                ';UT_WITHOUT_PAY=' . number_format($withoutPayDeduct, 3, '.', '') .
                ';VAC_OLD=' . number_format($oldAnnual, 3, '.', '') .
                ';VAC_NEW=' . number_format($newAnnual, 3, '.', '') .
                ';VAC=' . number_format($newAnnual, 3, '.', '') .
                ';SICK=' . number_format($sickBalance, 3, '.', '') .
                ';FORCE=' . number_format($forceBalance, 3, '.', '') .
                ';TOTAL_MINUTES=' . $totalMinutes .
                ';DATES=' . $dates;

            BalanceLedger::logBudgetChange(
                $employee->id,
                'Vacation',
                $oldAnnual,
                $newAnnual,
                $withPay && $withoutPayDeduct <= 0 ? 'undertime_paid' : 'undertime_unpaid',
                null,
                'Undertime dates: ' . $dates . ' | ' . $meta,
                collect($validItems)->min('date')
            );

            BalanceLedger::logLeaveBalanceChange($employee->id, -1 * $paidDeduct, $withPay && $withoutPayDeduct <= 0 ? 'undertime_paid' : 'undertime_unpaid');
        });

        return $this->redirectToProfile($employee->id)->with('success', 'Undertime recorded successfully.');
    }

    public function storeHistory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer'],
            'earning_amount' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'snapshot_annual_balance' => ['nullable', 'numeric', 'min:0'],
            'snapshot_sick_balance' => ['nullable', 'numeric', 'min:0'],
            'snapshot_force_balance' => ['nullable', 'numeric', 'min:0'],
            'snapshot_wellness_balance' => ['nullable', 'numeric', 'min:0'],
            'snapshot_spl_balance' => ['nullable', 'numeric', 'min:0'],
            'undertime_hours' => ['nullable', 'integer', 'min:0'],
            'undertime_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'undertime_with_pay' => ['nullable'],
        ]);

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $this->authorizeProfileAdminAction($employee);

        $typeId = (int) $data['leave_type_id'];
        $snapshots = $this->snapshotBalances($employee, $data);

        if ($typeId === 0) {
            return $this->storeHistoricalAccrual($employee, $data, $snapshots);
        }

        if ($typeId === -1) {
            return $this->storeHistoricalUndertime($employee, $data, $snapshots, $request->boolean('undertime_with_pay'));
        }

        return $this->storeHistoricalLeave($employee, $data, $snapshots, $typeId);
    }

    private function canView(string $role, ?int $viewerEmployeeId, ?int $viewerDepartmentId, ?string $viewerDepartment, Employee $target): bool
    {
        if (in_array($role, ['admin', 'hr', 'personnel'], true)) return true;
        if ($viewerEmployeeId && $viewerEmployeeId === $target->id) return true;
        if ($role === 'manager') return $viewerEmployeeId && ((int) $viewerEmployeeId === (int) $target->id || (int) ($target->manager_id ?? 0) === (int) $viewerEmployeeId);
        if ($role === 'department_head') {
            $deptIds = DepartmentHeadAssignment::query()->where('employee_id', $viewerEmployeeId)->where('is_active', 1)->pluck('department_id');
            if ($deptIds->isNotEmpty()) return $deptIds->contains($target->department_id);
            if ($viewerDepartmentId) return (int) $viewerDepartmentId === (int) $target->department_id;
            if ($viewerDepartment) return (string) $viewerDepartment === (string) $target->department;
        }
        return false;
    }

    private function authorizeProfileAdminAction(Employee $employee): void
    {
        $user = Auth::user();
        abort_unless(in_array((string) $user->role, self::ADMIN_PROFILE_ROLES, true), 403);
        abort_unless($this->canView((string) $user->role, $user->employee?->id, $user->employee?->department_id, $user->employee?->department, $employee), 403);
    }

    private function snapshotBalances(Employee $employee, array $data): array
    {
        return [
            'annual_balance' => array_key_exists('snapshot_annual_balance', $data) && $data['snapshot_annual_balance'] !== null ? $this->trunc3((float) $data['snapshot_annual_balance']) : $this->trunc3((float) $employee->annual_balance),
            'sick_balance' => array_key_exists('snapshot_sick_balance', $data) && $data['snapshot_sick_balance'] !== null ? $this->trunc3((float) $data['snapshot_sick_balance']) : $this->trunc3((float) $employee->sick_balance),
            'force_balance' => array_key_exists('snapshot_force_balance', $data) && $data['snapshot_force_balance'] !== null ? $this->trunc3((float) $data['snapshot_force_balance']) : $this->trunc3((float) $employee->force_balance),
            'wellness_balance' => array_key_exists('snapshot_wellness_balance', $data) && $data['snapshot_wellness_balance'] !== null ? $this->trunc3((float) $data['snapshot_wellness_balance']) : $this->trunc3((float) ($employee->wellness_balance ?? 5)),
            'spl_balance' => array_key_exists('snapshot_spl_balance', $data) && $data['snapshot_spl_balance'] !== null ? $this->trunc3((float) $data['snapshot_spl_balance']) : $this->trunc3((float) ($employee->spl_balance ?? 3)),
        ];
    }

    private function storeHistoricalAccrual(Employee $employee, array $data, array $snapshots): RedirectResponse
    {
        $earningAmount = $this->trunc3((float) ($data['earning_amount'] ?? 0));
        if ($earningAmount <= 0) {
            return $this->redirectToProfile($employee->id)->with('error', 'Earning amount required for accrual.');
        }

        DB::transaction(function () use ($employee, $data, $snapshots, $earningAmount) {
            $leave = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'leave_type' => 'Vacation Accrual Earned',
                'leave_type_id' => 0,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $earningAmount,
                'reason' => $this->nullableString($data['reason'] ?? null),
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'workflow_status' => 'finalized',
                'finalized_at' => now(),
                'snapshot_annual_balance' => $snapshots['annual_balance'],
                'snapshot_sick_balance' => $snapshots['sick_balance'],
                'snapshot_force_balance' => $snapshots['force_balance'],
            ]);

            BalanceLedger::logBudgetChange(
                $employee->id,
                'Vacation Accrual Earned',
                0,
                0,
                'earning',
                $leave->id,
                'Historical accrual earning (history only)',
                (string) $data['start_date']
            );
        });

        return $this->redirectToProfile($employee->id)->with('success', 'Historical entry added successfully.');
    }

    private function storeHistoricalUndertime(Employee $employee, array $data, array $snapshots, bool $withPay): RedirectResponse
    {
        $hours = (int) ($data['undertime_hours'] ?? 0);
        $minutes = (int) ($data['undertime_minutes'] ?? 0);
        if (($hours * 60) + $minutes <= 0) {
            return $this->redirectToProfile($employee->id)->with('error', 'Undertime minutes required.');
        }

        DB::transaction(function () use ($employee, $data, $snapshots, $hours, $minutes, $withPay) {
            $deduct = $this->trunc3(BalanceLedger::undertimeDaysFromChart($hours, $minutes));
            $oldAnnual = (float) $snapshots['annual_balance'];
            $newAnnual = (float) $snapshots['annual_balance'];

            $meta = 'UT_DEDUCT=' . number_format($deduct, 3, '.', '') .
                ';VAC=' . number_format((float) $snapshots['annual_balance'], 3, '.', '') .
                ';SICK=' . number_format((float) $snapshots['sick_balance'], 3, '.', '') .
                ';FORCE=' . number_format((float) $snapshots['force_balance'], 3, '.', '') .
                ';H=' . $hours .
                ';M=' . $minutes;

            BalanceLedger::logBudgetChange(
                $employee->id,
                'Vacation',
                $oldAnnual,
                $newAnnual,
                $withPay ? 'undertime_paid' : 'undertime_unpaid',
                null,
                'Historical undertime (no current balance affected) | ' . $meta,
                (string) $data['start_date']
            );
        });

        return $this->redirectToProfile($employee->id)->with('success', 'Historical undertime recorded successfully.');
    }

    private function storeHistoricalLeave(Employee $employee, array $data, array $snapshots, int $typeId): RedirectResponse
    {
        $leaveType = LeaveType::query()->find($typeId);
        if (!$leaveType) {
            return $this->redirectToProfile($employee->id)->with('error', 'Selected leave type was not found.');
        }

        $days = $this->trunc3((float) ($data['total_days'] ?? 0));
        if ($days <= 0) {
            return $this->redirectToProfile($employee->id)->with('error', 'Total days required for leave history entry.');
        }

        DB::transaction(function () use ($employee, $data, $snapshots, $leaveType, $days) {
            $leave = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'leave_type' => $leaveType->name,
                'leave_type_id' => $leaveType->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'total_days' => $days,
                'reason' => $this->nullableString($data['reason'] ?? null),
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'workflow_status' => 'finalized',
                'finalized_at' => now(),
                'snapshot_annual_balance' => $snapshots['annual_balance'],
                'snapshot_sick_balance' => $snapshots['sick_balance'],
                'snapshot_force_balance' => $snapshots['force_balance'],
            ]);

            if ((bool) $leaveType->deduct_balance) {
                $bucket = $this->historyBalanceBucketForLeaveName((string) $leaveType->name);
                $oldBalance = (float) ($snapshots[$bucket] ?? 0);
                $newBalance = $this->trunc3(max(0, $oldBalance - $days));

                BalanceLedger::logBudgetChange(
                    $employee->id,
                    (string) $leaveType->name,
                    $oldBalance,
                    $newBalance,
                    'deduction',
                    $leave->id,
                    'Historical leave entry (no current balance affected)',
                    (string) $data['start_date']
                );

                BalanceLedger::logLeaveBalanceChange($employee->id, -1 * $days, 'historical_deduction', $leave->id);
            }
        });

        return $this->redirectToProfile($employee->id)->with('success', 'Historical entry added successfully.');
    }

    private function historyBalanceBucketForLeaveName(string $name): string
    {
        return match ($this->policyService->normalizeLeaveTypeKey($name)) {
            'sick leave' => 'sick_balance',
            'mandatory/forced leave' => 'force_balance',
            default => 'annual_balance',
        };
    }

    private function redirectToProfile(int $employeeId): RedirectResponse
    {
        return redirect()->route('employee-profile', ['employee' => $employeeId]);
    }

    private function balancesDiffer(float $old, float $new): bool
    {
        return number_format($old, 3, '.', '') !== number_format($new, 3, '.', '');
    }

    private function trunc3(float|int|string|null $value): float
    {
        return $this->ledger->trunc($value);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function exportLeaveCard(Employee $employee, array $rows): Response
    {
        return $this->leaveCardExcel->download($employee, $rows);
    }

    private function exportHistory(Employee $employee, $history): StreamedResponse
    {
        $filename = 'Leave History - '.trim($employee->fullName()).'.csv';
        return response()->streamDownload(function () use ($history) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Leave Type','Dates','Days','Status','Submitted','Vacation Bal','Sick Bal','Force Bal','Comments']);
            foreach ($history as $row) {
                fputcsv($out, [
                    $row->leave_type_name,
                    optional($row->start_date)->format('Y-m-d').' - '.optional($row->end_date)->format('Y-m-d'),
                    $row->total_days,
                    $row->status,
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    $row->snapshot_annual_balance,
                    $row->snapshot_sick_balance,
                    $row->snapshot_force_balance,
                    $row->manager_comments,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
