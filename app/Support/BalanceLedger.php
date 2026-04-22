<?php

namespace App\Support;

use App\Models\BudgetHistory;
use App\Models\LeaveBalanceLog;
use Illuminate\Support\Facades\Schema;

class BalanceLedger
{
    public static function undertimeDaysFromChart(int $hours, int $minutes): float
    {
        $minutes = max(0, min(60, $minutes));

        $minMapTh = [
            0=>0, 1=>2, 2=>4, 3=>6, 4=>8, 5=>10,
            6=>12, 7=>15, 8=>17, 9=>19, 10=>21,
            11=>23, 12=>25, 13=>27, 14=>29, 15=>31,
            16=>33, 17=>35, 18=>37, 19=>40, 20=>42,
            21=>44, 22=>46, 23=>48, 24=>50, 25=>52,
            26=>54, 27=>56, 28=>58, 29=>60, 30=>62,
            31=>65, 32=>67, 33=>69, 34=>71, 35=>73,
            36=>75, 37=>77, 38=>79, 39=>81, 40=>83,
            41=>85, 42=>87, 43=>90, 44=>92, 45=>94,
            46=>96, 47=>98, 48=>100, 49=>102, 50=>104,
            51=>106, 52=>108, 53=>110, 54=>112, 55=>115,
            56=>117, 57=>119, 58=>121, 59=>123, 60=>125,
        ];

        $hoursTh = max(0, $hours) * 125;
        $minsTh = $minMapTh[$minutes] ?? 0;

        return ($hoursTh + $minsTh) / 1000;
    }

    public static function logBudgetChange(
        int $employeeId,
        string $leaveType,
        float $old,
        float $new,
        string $action,
        ?int $leaveId = null,
        string $notes = '',
        ?string $transDate = null
    ): void {
        if (!Schema::hasTable('budget_history')) {
            return;
        }

        $payload = [
            'employee_id' => $employeeId,
            'leave_type' => $leaveType,
            'action' => $action,
            'old_balance' => $old,
            'new_balance' => $new,
            'notes' => $notes,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('budget_history', 'leave_request_id')) {
            $payload['leave_request_id'] = $leaveId;
        } elseif (Schema::hasColumn('budget_history', 'leave_id')) {
            $payload['leave_id'] = $leaveId;
        }

        if (Schema::hasColumn('budget_history', 'trans_date')) {
            $payload['trans_date'] = $transDate ?: now()->toDateString();
        }

        BudgetHistory::query()->create($payload);
    }

    public static function logLeaveBalanceChange(int $employeeId, float $changeAmount, string $reason, ?int $leaveId = null): void
    {
        if (!Schema::hasTable('leave_balance_logs')) {
            return;
        }

        $payload = [
            'employee_id' => $employeeId,
            'change_amount' => $changeAmount,
            'reason' => $reason,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('leave_balance_logs', 'leave_id')) {
            $payload['leave_id'] = $leaveId;
        }

        LeaveBalanceLog::query()->create($payload);
    }
}
