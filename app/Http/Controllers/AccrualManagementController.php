<?php

namespace App\Http\Controllers;

use App\Models\Accrual;
use App\Models\AccrualHistory;
use App\Models\Employee;
use App\Support\AutoAccrualSettings;
use App\Support\BalanceLedger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccrualManagementController extends Controller
{
    private function authorizeRole(): void
    {
        abort_unless(in_array((string) Auth::user()->role, ['admin', 'hr', 'personnel'], true), 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeRole();

        $employees = Employee::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $search = trim((string) $request->query('history_q', ''));
        $history = AccrualHistory::query()
            ->with('employee')
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('employee', function ($inner) use ($search) {
                    $inner->whereRaw("concat_ws(' ', first_name, middle_name, last_name) like ?", ['%' . $search . '%']);
                })->orWhere('month_reference', 'like', '%' . $search . '%')
                  ->orWhere('amount', 'like', '%' . $search . '%')
                  ->orWhereDate('date_accrued', $search);
            })
            ->orderByDesc('date_accrued')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'history_page')
            ->withQueryString();

        if ($history->total() === 0) {
            $history = Accrual::query()
                ->with('employee')
                ->when($search !== '', function ($query) use ($search) {
                    $query->whereHas('employee', function ($inner) use ($search) {
                        $inner->whereRaw("concat_ws(' ', first_name, middle_name, last_name) like ?", ['%' . $search . '%']);
                    })->orWhere('amount', 'like', '%' . $search . '%')
                      ->orWhereDate('created_at', $search);
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(12, ['*'], 'history_page')
                ->withQueryString();
        }

        $totalEmployees = Employee::query()->count();
        $autoAccrualSettings = AutoAccrualSettings::get();
        $autoAccrualNextRun = AutoAccrualSettings::nextRunAt();

        return view('accruals.index', compact('employees', 'history', 'search', 'totalEmployees', 'autoAccrualSettings', 'autoAccrualNextRun'));
    }


    public function updateAutomatic(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'mode' => ['required', 'string', 'in:enable,update,disable'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
        ]);

        if ($data['mode'] === 'disable') {
            AutoAccrualSettings::save(['enabled' => false]);

            return redirect()->route('manage-accruals')->with('success', 'Automatic month-end accrual has been disabled. Manual accrual remains available.');
        }

        AutoAccrualSettings::save([
            'enabled' => true,
            'amount' => $this->trunc3((float) ($data['amount'] ?? 1.250)),
        ]);

        return redirect()->route('manage-accruals')->with('success', 'Automatic month-end accrual is active and scheduled for 11:59 PM on the last day of each month.');
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $this->applyAccrualToEmployees([(int) $data['employee_id']], (float) $data['amount'], (string) $data['month']);

        return redirect()->route('manage-accruals')->with('success', 'Manual accrual recorded successfully.');
    }

    public function storeCto(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'earned_at' => ['required', 'date'],
        ]);

        $amount = $this->trunc3((float) $data['amount']);
        $earnedAt = Carbon::parse((string) $data['earned_at'])->toDateString();

        DB::transaction(function () use ($data, $amount, $earnedAt) {
            $employee = Employee::query()->lockForUpdate()->findOrFail((int) $data['employee_id']);
            $oldBalance = (float) ($employee->cto_balance ?? 0);
            $firstEarnedAt = $employee->cto_first_earned_at ? Carbon::parse($employee->cto_first_earned_at) : null;
            $earnedDate = Carbon::parse($earnedAt);

            if ($firstEarnedAt && $earnedDate->greaterThanOrEqualTo($firstEarnedAt->copy()->addYear())) {
                $oldBalance = 0.0;
                $employee->cto_balance = 0.0;
                $employee->cto_first_earned_at = $earnedAt;
            } elseif (!$firstEarnedAt) {
                $employee->cto_first_earned_at = $earnedAt;
            }

            $availableRoom = max(0.0, 15.0 - (float) ($employee->cto_balance ?? 0));
            if ($availableRoom <= 0 || $amount > $availableRoom) {
                throw new \RuntimeException('CTO balance can only be earned up to 15.000 days. Available room: ' . number_format($availableRoom, 3) . ' day(s).');
            }

            $employee->cto_balance = $this->trunc3((float) ($employee->cto_balance ?? 0) + $amount);
            $employee->save();

            $expiresAt = Carbon::parse($employee->cto_first_earned_at)->addYear()->toDateString();
            BalanceLedger::logBudgetChange(
                $employee->id,
                'CTO',
                $oldBalance,
                (float) $employee->cto_balance,
                'cto_earning',
                null,
                'CTO earned on ' . $earnedAt . '; expires on ' . $expiresAt,
                $earnedAt
            );
        });

        return redirect()->route('manage-accruals')->with('success', 'CTO earning recorded successfully.');
    }

    public function storeBulk(Request $request): RedirectResponse
    {
        $this->authorizeRole();

        $data = $request->validate([
            'bulk_amount' => ['required', 'numeric', 'gt:0'],
            'bulk_month' => ['required', 'date_format:Y-m'],
        ]);

        $employeeIds = Employee::query()->pluck('id')->all();
        $count = $this->applyAccrualToEmployees($employeeIds, (float) $data['bulk_amount'], (string) $data['bulk_month']);

        return redirect()->route('manage-accruals')->with('success', "Bulk accrual completed for {$count} employee(s).");
    }


    private function trunc3(float $value): float
    {
        $multiplier = 1000;

        return $value >= 0
            ? floor($value * $multiplier) / $multiplier
            : ceil($value * $multiplier) / $multiplier;
    }

    /** @param int[] $employeeIds */
    private function applyAccrualToEmployees(array $employeeIds, float $amount, string $month): int
    {
        $dateAccrued = Carbon::now()->toDateString();
        $count = 0;

        DB::transaction(function () use ($employeeIds, $amount, $month, $dateAccrued, &$count) {
            $employees = Employee::query()->whereIn('id', $employeeIds)->lockForUpdate()->get();
            foreach ($employees as $employee) {
                $employee->annual_balance = round((float) $employee->annual_balance + $amount, 3);
                $employee->sick_balance = round((float) $employee->sick_balance + $amount, 3);
                $employee->save();

                AccrualHistory::query()->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'date_accrued' => $dateAccrued,
                    'month_reference' => $month,
                    'created_at' => now(),
                ]);

                Accrual::query()->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'created_at' => now(),
                ]);

                $count++;
            }
        });

        return $count;
    }
}
