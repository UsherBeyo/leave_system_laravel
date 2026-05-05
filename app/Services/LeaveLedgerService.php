<?php

namespace App\Services;

use App\Models\BudgetHistory;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Support\Collection;

class LeaveLedgerService
{
    public function __construct(private LeavePolicyService $policyService)
    {
    }

    public function trunc(float|int|string|null $value): float
    {
        $n = (float) ($value ?? 0);
        return $n >= 0 ? floor($n * 1000) / 1000 : ceil($n * 1000) / 1000;
    }

    public function buildLeaveCardRows(int $employeeId): array
    {
        $rows = [];

        $leaveRows = LeaveRequest::query()
            ->with(['leaveTypeRelation', 'employee'])
            ->where('employee_id', $employeeId)
            ->orderByRaw('COALESCE(start_date, DATE(created_at)) ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($leaveRows as $row) {
            $leaveType = trim((string) $row->leave_type_name);
            if (strtolower($leaveType) === 'undertime') {
                continue;
            }

            $statusRaw = strtolower(trim((string) $row->status));
            $workflowRaw = strtolower(trim((string) $row->workflow_status));
            $days = (float) $row->total_days;
            $typeKey = $this->policyService->normalizeLeaveTypeKey($leaveType);
            $isSick = $typeKey === 'sick leave';
            $isAccrual = str_contains(strtolower($leaveType), 'accrual');

            $form = \App\Models\LeaveRequestForm::query()->where('leave_request_id', $row->id)->first();

            $vacEarn = 0.0; $sickEarn = 0.0;
            $vacWithPay = 0.0; $vacWithoutPay = 0.0;
            $sickWithPay = 0.0; $sickWithoutPay = 0.0;
            $vacBal = $row->snapshot_annual_balance ?? '';
            $sickBal = $row->snapshot_sick_balance ?? '';

            if ($isAccrual) {
                if ($isSick) {
                    $sickEarn = $days;
                } else {
                    $vacEarn = $days;
                }
                $statusRaw = 'earning';
            } else {
                $isFinalized = $statusRaw === 'approved'
                    || $statusRaw === 'cancelled'
                    || in_array($workflowRaw, ['finalized', 'cancelled_after_approval'], true);

                if ($isFinalized) {
                    $vacDeducted = 0.0;
                    $sickDeducted = 0.0;
                    $withoutPay = 0.0;

                    if ($form && ($form->cert_vacation_less_this_application !== null || $form->cert_sick_less_this_application !== null)) {
                        $vacDeducted = (float) ($form->cert_vacation_less_this_application ?? 0);
                        $sickDeducted = (float) ($form->cert_sick_less_this_application ?? 0);
                        $withoutPay = (float) ($form->approved_for_days_without_pay ?? 0);
                        $vacBal = $form->cert_vacation_balance ?? $vacBal;
                        $sickBal = $form->cert_sick_balance ?? $sickBal;
                    } elseif ($isSick) {
                        $sickDeducted = $days;
                    } else {
                        $vacDeducted = $days;
                    }

                    if ($isSick) {
                        $sickWithPay = $sickDeducted;
                        $sickWithoutPay = $withoutPay;
                    } else {
                        $vacWithPay = $vacDeducted;
                        $vacWithoutPay = $withoutPay;
                    }
                }
            }

            $particulars = $leaveType;
            if (!$isAccrual && !str_contains(strtolower($particulars), 'leave')) {
                $particulars .= ' Leave';
            }

            $txDate = $row->start_date?->toDateString() ?: optional($row->created_at)->toDateString();
            $remarks = ucfirst(str_replace('_', ' ', $workflowRaw ?: $statusRaw));
            $rows[] = [
                'date' => $txDate,
                'period' => $txDate,
                'particulars' => $particulars,
                'vac_earned' => $vacEarn,
                'vac_with_pay' => $vacWithPay,
                'vac_deducted' => $vacWithPay,
                'vac_balance' => $vacBal,
                'vac_without_pay' => $vacWithoutPay,
                'sick_earned' => $sickEarn,
                'sick_with_pay' => $sickWithPay,
                'sick_deducted' => $sickWithPay,
                'sick_balance' => $sickBal,
                'sick_without_pay' => $sickWithoutPay,
                'remarks' => $remarks,
                'status' => $remarks,
                '_sort_ts' => strtotime((string) ($txDate ?: '1970-01-01')),
                '_sort_seq' => 1,
            ];
        }

        $budgetRows = BudgetHistory::query()
            ->where('employee_id', $employeeId)
            ->where(function ($q) {
                $q->whereNull('leave_request_id')->orWhere('leave_request_id', 0);
            })
            ->orderByRaw('COALESCE(trans_date, DATE(created_at)) ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($budgetRows as $row) {
            $leaveType = trim((string) $row->leave_type);
            $action = strtolower(trim((string) $row->action));
            $notes = (string) ($row->notes ?? '');
            $meta = $this->parseBudgetHistoryMeta($notes);
            $old = (float) ($row->old_balance ?? 0);
            $new = (float) ($row->new_balance ?? 0);
            $deltaEarn = max(0.0, $new - $old);
            $deltaDed = max(0.0, $old - $new);

            $vacEarn = 0.0; $sickEarn = 0.0;
            $vacWithPay = 0.0; $vacWithoutPay = 0.0;
            $sickWithPay = 0.0; $sickWithoutPay = 0.0;
            $vacBal = ''; $sickBal = '';
            $particulars = $leaveType !== '' ? $leaveType : 'Balance Adjustment';

            if ($action === 'undertime_paid' || $action === 'undertime_unpaid') {
                $totalUndertime = isset($meta['UT_DEDUCT']) ? (float) $meta['UT_DEDUCT'] : $deltaDed;
                $vacWithPay = isset($meta['UT_WITH_PAY'])
                    ? (float) $meta['UT_WITH_PAY']
                    : ($action === 'undertime_paid' ? $totalUndertime : $deltaDed);
                $vacWithoutPay = isset($meta['UT_WITHOUT_PAY'])
                    ? (float) $meta['UT_WITHOUT_PAY']
                    : ($action === 'undertime_unpaid' ? max(0.0, $totalUndertime - $vacWithPay) : 0.0);
                $vacBal = isset($meta['VAC_NEW']) ? (float) $meta['VAC_NEW'] : (isset($meta['VAC']) ? (float) $meta['VAC'] : $new);
                $sickBal = isset($meta['SICK']) ? (float) $meta['SICK'] : '';
                $dateLabel = isset($meta['DATES']) ? ' - ' . $meta['DATES'] : '';
                $particulars = 'Undertime' . $dateLabel;
            } elseif (str_contains($action, 'earning') || str_contains(strtolower($leaveType), 'accrual')) {
                if ($this->policyService->normalizeLeaveTypeKey($leaveType) === 'sick leave') {
                    $sickEarn = $deltaEarn;
                    $sickBal = $new;
                } else {
                    $vacEarn = $deltaEarn;
                    $vacBal = $new;
                }
                $particulars = $leaveType !== '' ? $leaveType : 'Accrual';
            } elseif (str_contains($action, 'deduction')) {
                if ($this->policyService->normalizeLeaveTypeKey($leaveType) === 'sick leave') {
                    $sickWithPay = $deltaDed;
                    $sickBal = $new;
                } else {
                    $vacWithPay = $deltaDed;
                    $vacBal = $new;
                }
            } elseif (str_contains($action, 'restore') || str_contains($action, 'cancellation')) {
                if ($this->policyService->normalizeLeaveTypeKey($leaveType) === 'sick leave') {
                    $sickEarn = $deltaEarn;
                    $sickBal = $new;
                } else {
                    $vacEarn = $deltaEarn;
                    $vacBal = $new;
                }
                $particulars = $leaveType !== '' ? 'Cancellation / Restoration - ' . $leaveType : 'Cancellation / Restoration';
            } else {
                if ($this->policyService->normalizeLeaveTypeKey($leaveType) === 'sick leave') {
                    $sickBal = $new;
                } else {
                    $vacBal = $new;
                }
            }

            $txDate = optional($row->trans_date)->toDateString() ?: optional($row->created_at)->toDateString();
            $remarks = ucfirst(str_replace('_', ' ', $action ?: 'logged'));
            $rows[] = [
                'date' => $txDate,
                'period' => $txDate,
                'particulars' => $particulars,
                'vac_earned' => $vacEarn,
                'vac_with_pay' => $vacWithPay,
                'vac_deducted' => $vacWithPay,
                'vac_balance' => $vacBal,
                'vac_without_pay' => $vacWithoutPay,
                'sick_earned' => $sickEarn,
                'sick_with_pay' => $sickWithPay,
                'sick_deducted' => $sickWithPay,
                'sick_balance' => $sickBal,
                'sick_without_pay' => $sickWithoutPay,
                'remarks' => $remarks,
                'status' => $remarks,
                '_sort_ts' => strtotime((string) ($txDate ?: '1970-01-01')),
                '_sort_seq' => 2,
            ];
        }

        usort($rows, function (array $a, array $b) {
            if (($a['_sort_ts'] ?? 0) === ($b['_sort_ts'] ?? 0)) {
                return ($a['_sort_seq'] ?? 0) <=> ($b['_sort_seq'] ?? 0);
            }
            return ($a['_sort_ts'] ?? 0) <=> ($b['_sort_ts'] ?? 0);
        });

        return $rows;
    }

    public function usedBalances(Employee $employee): array
    {
        $annual = 0.0; $sick = 0.0; $force = 0.0;
        $rows = BudgetHistory::query()->where('employee_id', $employee->id)->get();
        foreach ($rows as $row) {
            $type = strtolower(trim((string) $row->leave_type));
            $delta = max(0.0, (float) $row->old_balance - (float) $row->new_balance);
            if ($delta <= 0) continue;
            if (str_contains($type, 'sick')) {
                $sick += $delta;
            } elseif (str_contains($type, 'force') || str_contains($type, 'mandatory')) {
                $force += $delta;
            } else {
                $annual += $delta;
            }
        }

        return [
            'annual' => $annual,
            'sick' => $sick,
            'force' => $force,
        ];
    }

    public function usageRows(Collection $employees, ?string $departmentFilter = null): array
    {
        $query = LeaveRequest::query()
            ->with(['employee', 'leaveTypeRelation'])
            ->where(function ($q) {
                $q->where('status', 'approved')->orWhere('workflow_status', 'finalized');
            });

        if ($employees->isNotEmpty()) {
            $query->whereIn('employee_id', $employees->pluck('id')->all());
        }

        if ($departmentFilter) {
            $query->whereHas('employee', function ($q) use ($departmentFilter) {
                $q->where('department', $departmentFilter);
            });
        }

        $grouped = [];
        foreach ($query->get() as $row) {
            $dept = (string) ($row->employee?->department ?: 'Unassigned');
            $type = (string) ($row->leave_type_name ?: 'Unknown');
            $key = $dept.'|'.$type;
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['department' => $dept, 'leave_type' => $type, 'count' => 0, 'total_days' => 0.0];
            }
            $grouped[$key]['count']++;
            $grouped[$key]['total_days'] += (float) $row->total_days;
        }

        usort($grouped, fn ($a, $b) => [$a['department'], $a['leave_type']] <=> [$b['department'], $b['leave_type']]);
        return array_values($grouped);
    }

    public function parseBudgetHistoryMeta(?string $notes): array
    {
        $meta = [];
        $notes = (string) $notes;
        if (preg_match_all('/([A-Z_]+)=([^;]+)/', $notes, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $pair) {
                $meta[$pair[1]] = $pair[2];
            }
        }
        return $meta;
    }
}
