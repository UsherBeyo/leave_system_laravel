<?php

namespace App\Services;

use App\Models\Accrual;
use App\Models\AccrualHistory;
use App\Models\Employee;
use App\Support\AutoAccrualSettings;
use App\Support\BalanceLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyAccrualService
{
    public function runAutomaticIfDue(?Carbon $now = null, bool $force = false): array
    {
        $now = $now ? $now->copy() : now(config('app.timezone'));
        $settings = AutoAccrualSettings::get();
        $month = $now->format('Y-m');

        if (!$force && empty($settings['enabled'])) {
            return $this->result(false, 0, 'Automatic month-end accrual is disabled.');
        }

        if (!$force && !$now->isLastOfMonth()) {
            return $this->result(false, 0, 'Today is not the last day of the month.');
        }

        if (!$force && $now->format('H:i') < '23:59') {
            return $this->result(false, 0, 'Automatic accrual is scheduled for 11:59 PM.');
        }

        if (!$force && (string) ($settings['last_run_month'] ?? '') === $month) {
            return $this->result(false, (int) ($settings['last_run_count'] ?? 0), 'Automatic accrual already ran for ' . $month . '.');
        }

        $amount = $this->trunc3((float) ($settings['amount'] ?? 1.250));
        if ($amount <= 0) {
            return $this->result(false, 0, 'Automatic accrual amount must be greater than zero.');
        }

        $count = $this->applyAutomaticAccrual($amount, $month, $now);
        $message = "Automatic month-end accrual completed for {$count} employee(s) for {$month}.";

        AutoAccrualSettings::save([
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'amount' => $amount,
            'last_run_month' => $month,
            'last_run_at' => $now->toDateTimeString(),
            'last_run_count' => $count,
            'last_run_message' => $message,
        ]);

        return $this->result(true, $count, $message);
    }

    private function applyAutomaticAccrual(float $amount, string $month, Carbon $now): int
    {
        $dateAccrued = $now->toDateString();
        $count = 0;

        DB::transaction(function () use ($amount, $month, $dateAccrued, $now, &$count) {
            $employees = Employee::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($employees as $employee) {
                $oldAnnual = (float) $employee->annual_balance;
                $oldSick = (float) $employee->sick_balance;
                $newAnnual = $this->trunc3($oldAnnual + $amount);
                $newSick = $this->trunc3($oldSick + $amount);

                $employee->annual_balance = $newAnnual;
                $employee->sick_balance = $newSick;
                $employee->save();

                AccrualHistory::query()->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'date_accrued' => $dateAccrued,
                    'month_reference' => $month,
                    'created_at' => $now,
                ]);

                Accrual::query()->create([
                    'employee_id' => $employee->id,
                    'amount' => $amount,
                    'created_at' => $now,
                ]);

                BalanceLedger::logBudgetChange(
                    (int) $employee->id,
                    'Vacational',
                    $oldAnnual,
                    $newAnnual,
                    'accrual',
                    null,
                    'Automatic month-end accrual for ' . $month,
                    $dateAccrued
                );

                BalanceLedger::logBudgetChange(
                    (int) $employee->id,
                    'Sick',
                    $oldSick,
                    $newSick,
                    'accrual',
                    null,
                    'Automatic month-end accrual for ' . $month,
                    $dateAccrued
                );

                $count++;
            }
        });

        return $count;
    }

    private function result(bool $ran, int $count, string $message): array
    {
        return [
            'ran' => $ran,
            'count' => $count,
            'message' => $message,
        ];
    }

    private function trunc3(float $value): float
    {
        $multiplier = 1000;
        return $value >= 0
            ? floor($value * $multiplier) / $multiplier
            : ceil($value * $multiplier) / $multiplier;
    }
}
