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
            $days = (float) $row->total_days;
            $typeKey = $this->policyService->normalizeLeaveTypeKey($leaveType);
            $isSick = $typeKey === 'sick leave';
            $isForce = $typeKey === 'mandatory/forced leave';
            $isAccrual = str_contains(strtolower($leaveType), 'accrual');

            $form = \App\Models\LeaveRequestForm::query()->where('leave_request_id', $row->id)->first();

            $vacEarn = 0.0; $sickEarn = 0.0;
            $vacDed = 0.0; $sickDed = 0.0;

            if ($isAccrual) {
                if ($isSick) {
                    $sickEarn = $days;
                } else {
                    $vacEarn = $days;
                }
                $statusRaw = 'earning';
            } else {
                if ($statusRaw === 'approved' || strtolower((string)$row->workflow_status) === 'finalized') {
                    if ($form && ($form->cert_vacation_less_this_application !== null || $form->cert_sick_less_this_application !== null)) {
                        $vacDed = (float) ($form->cert_vacation_less_this_application ?? 0);
                        $sickDed = (float) ($form->cert_sick_less_this_application ?? 0);
                    } elseif ($isSick) {
                        $sickDed = $days;
                    } else {
                        $vacDed = $days;
                    }
                }
            }

            $particulars = $leaveType;
            if (!$isAccrual && !str_contains(strtolower($particulars), 'leave')) {
                $particulars .= ' Leave';
            }

            $txDate = $row->start_date?->toDateString() ?: optional($row->created_at)->toDateString();
            $rows[] = [
                'date' => $txDate,
                'particulars' => $particulars,
                'vac_earned' => $vacEarn,
                'vac_deducted' => $vacDed,
                'vac_balance' => $row->snapshot_annual_balance ?? '',
                'sick_earned' => $sickEarn,
                'sick_deducted' => $sickDed,
                'sick_balance' => $row->snapshot_sick_balance ?? '',
                'status' => ucfirst($statusRaw),
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
            $vacDed = 0.0; $sickDed = 0.0;
            $vacBal = ''; $sickBal = '';
            $particulars = $leaveType !== '' ? $leaveType : 'Balance Adjustment';

            if ($action === 'undertime_paid' || $action === 'undertime_unpaid') {
                $vacDed = isset($meta['UT_DEDUCT']) ? (float) $meta['UT_DEDUCT'] : $deltaDed;
                $vacBal = isset($meta['VAC_NEW']) ? (float) $meta['VAC_NEW'] : (isset($meta['VAC']) ? (float) $meta['VAC'] : $new);
                $sickBal = isset($meta['SICK']) ? (float) $meta['SICK'] : '';
                $particulars = 'Undertime '.($action === 'undertime_paid' ? '(With pay)' : '(Without pay)');
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
                    $sickDed = $deltaDed;
                    $sickBal = $new;
                } else {
                    $vacDed = $deltaDed;
                    $vacBal = $new;
                }
            } else {
                if ($this->policyService->normalizeLeaveTypeKey($leaveType) === 'sick leave') {
                    $sickBal = $new;
                } else {
                    $vacBal = $new;
                }
            }

            $txDate = optional($row->trans_date)->toDateString() ?: optional($row->created_at)->toDateString();
            $rows[] = [
                'date' => $txDate,
                'particulars' => $particulars,
                'vac_earned' => $vacEarn,
                'vac_deducted' => $vacDed,
                'vac_balance' => $vacBal,
                'sick_earned' => $sickEarn,
                'sick_deducted' => $sickDed,
                'sick_balance' => $sickBal,
                'status' => ucfirst(str_replace('_', ' ', $action ?: 'logged')),
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
        if (preg_match_all('/([A-Z_]+)=([0-9.]+)/', $notes, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $pair) {
                $meta[$pair[1]] = $pair[2];
            }
        }
        return $meta;
    }
}
