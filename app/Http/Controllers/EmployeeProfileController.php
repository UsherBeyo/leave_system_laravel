<?php

namespace App\Http\Controllers;

use App\Models\DepartmentHeadAssignment;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeProfileController extends Controller
{
    public function __construct(private LeaveLedgerService $ledger)
    {
    }

    public function show(Request $request): View|StreamedResponse
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

        $budgetHistory = \App\Models\BudgetHistory::query()
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

    private function canView(string $role, ?int $viewerEmployeeId, ?int $viewerDepartmentId, ?string $viewerDepartment, Employee $target): bool
    {
        if (in_array($role, ['admin', 'hr', 'personnel'], true)) return true;
        if ($viewerEmployeeId && $viewerEmployeeId === $target->id) return true;
        if ($role === 'manager') return true;
        if ($role === 'department_head') {
            $deptIds = DepartmentHeadAssignment::query()->where('employee_id', $viewerEmployeeId)->where('is_active', 1)->pluck('department_id');
            if ($deptIds->isNotEmpty()) return $deptIds->contains($target->department_id);
            if ($viewerDepartmentId) return (int) $viewerDepartmentId === (int) $target->department_id;
            if ($viewerDepartment) return (string) $viewerDepartment === (string) $target->department;
        }
        return false;
    }

    private function exportLeaveCard(Employee $employee, array $rows): StreamedResponse
    {
        $filename = 'Leave Card - '.trim($employee->fullName()).'.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date','Particulars','Vac Earned','Vac Deducted','Vac Balance','Sick Earned','Sick Deducted','Sick Balance','Status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['date'] ?? '', $row['particulars'] ?? '', $row['vac_earned'] ?? '', $row['vac_deducted'] ?? '', $row['vac_balance'] ?? '', $row['sick_earned'] ?? '', $row['sick_deducted'] ?? '', $row['sick_balance'] ?? '', $row['status'] ?? '']);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function exportHistory(Employee $employee, $history): StreamedResponse
    {
        $filename = 'Leave History - '.trim($employee->fullName()).'.csv';
        return response()->streamDownload(function () use ($history) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Leave Type','Dates','Days','Status','Submitted','Vacational Bal','Sick Bal','Force Bal','Comments']);
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
