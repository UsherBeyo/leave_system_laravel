<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function sidebarCounts(User $user): array
    {
        $role = (string) $user->role;
        $userId = (int) $user->id;
        $employeeRow = $user->employee;

        $counts = [
            'leave_requests' => 0,
            'apply_leave' => 0,
        ];

        if ($role === 'department_head') {
            $deptIds = $this->departmentIdsForUser($user);
            if (!empty($deptIds)) {
                $counts['leave_requests'] = (int) DB::table('leave_requests')
                    ->where('workflow_status', 'pending_department_head')
                    ->where('status', 'pending')
                    ->whereIn('department_id', $deptIds)
                    ->count();
            }
            return $counts;
        }

        if ($role === 'manager') {
            $empId = $this->findEmployeeId($user);
            if ($empId > 0) {
                $counts['leave_requests'] = (int) DB::table('leave_requests as lr')
                    ->join('employees as e', 'e.id', '=', 'lr.employee_id')
                    ->where('lr.status', 'pending')
                    ->where('e.manager_id', $empId)
                    ->count();
            }
            return $counts;
        }

        if (in_array($role, ['personnel', 'hr'], true)) {
            $counts['leave_requests'] = (int) DB::table('leave_requests')
                ->where('workflow_status', 'pending_personnel')
                ->where('status', 'pending')
                ->count();
            return $counts;
        }

        if ($role === 'admin') {
            $counts['leave_requests'] = (int) DB::table('leave_requests')
                ->where('status', 'pending')
                ->count();
            return $counts;
        }

        return $counts;
    }

    public function headerNotifications(User $user, int $limit = 8): array
    {
        $role = (string) $user->role;
        $userId = (int) $user->id;
        $items = [];
        $count = 0;

        if ($role === 'employee') {
            $empId = $this->findEmployeeId($user);
            if ($empId > 0) {
                $rows = DB::table('leave_requests as lr')
                    ->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
                    ->select('lr.*', DB::raw('COALESCE(lt.name, lr.leave_type) AS leave_type_name'))
                    ->where('lr.employee_id', $empId)
                    ->orderByRaw('COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC')
                    ->orderByDesc('lr.id')
                    ->limit(15)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                foreach ($rows as $row) {
                    $status = strtolower((string) ($row['status'] ?? ''));
                    $workflow = strtolower((string) ($row['workflow_status'] ?? ''));
                    if ($workflow === 'pending_department_head') {
                        $this->pushUnique($items, $this->buildItem($row, 'Submitted', (($row['leave_type_name'] ?? 'Leave request') . ' is waiting for department-head approval.'), (string) ($row['created_at'] ?? ''), $user, 'info'));
                    } elseif ($workflow === 'pending_personnel') {
                        $this->pushUnique($items, $this->buildItem($row, 'Forwarded', (($row['leave_type_name'] ?? 'Leave request') . ' is now waiting for final personnel review.'), (string) ($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'warning'));
                    } elseif ($workflow === 'finalized' || $status === 'approved') {
                        $this->pushUnique($items, $this->buildItem($row, 'Approved', (($row['leave_type_name'] ?? 'Leave request') . ' was approved and finalized.'), (string) ($row['finalized_at'] ?? $row['personnel_checked_at'] ?? $row['created_at'] ?? ''), $user, 'success'));
                    } elseif (str_contains($workflow, 'return')) {
                        $this->pushUnique($items, $this->buildItem($row, 'Returned', (($row['leave_type_name'] ?? 'Leave request') . ' was returned for follow-up.'), (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                    } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                        $this->pushUnique($items, $this->buildItem($row, 'Rejected', (($row['leave_type_name'] ?? 'Leave request') . ' was rejected.'), (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                    }
                }

                $count = (int) DB::table('leave_requests')
                    ->where('employee_id', $empId)
                    ->where('status', 'pending')
                    ->count();
            }
        } elseif ($role === 'department_head') {
            $deptIds = $this->departmentIdsForUser($user);
            if (!empty($deptIds)) {
                $rows = DB::table('leave_requests as lr')
                    ->join('employees as e', 'e.id', '=', 'lr.employee_id')
                    ->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
                    ->select('lr.*', 'e.first_name', 'e.last_name', DB::raw('COALESCE(lt.name, lr.leave_type) AS leave_type_name'))
                    ->whereIn('lr.department_id', $deptIds)
                    ->orderByRaw('COALESCE(lr.personnel_checked_at, lr.finalized_at, lr.department_head_approved_at, lr.created_at) DESC')
                    ->orderByDesc('lr.id')
                    ->limit(15)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                foreach ($rows as $row) {
                    $status = strtolower((string) ($row['status'] ?? ''));
                    $workflow = strtolower((string) ($row['workflow_status'] ?? ''));
                    $emp = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                    if ($workflow === 'pending_department_head' && $status === 'pending') {
                        $this->pushUnique($items, $this->buildItem($row, 'Needs approval', $emp . ' filed ' . ($row['leave_type_name'] ?? 'a leave request') . ' and is waiting for your approval.', (string) ($row['created_at'] ?? ''), $user, 'warning'));
                    } elseif ($workflow === 'pending_personnel') {
                        $this->pushUnique($items, $this->buildItem($row, 'Forwarded', $emp . "'s request was already forwarded to personnel.", (string) ($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'success'));
                    } elseif (str_contains($workflow, 'return')) {
                        $this->pushUnique($items, $this->buildItem($row, 'Returned', $emp . "'s request was returned and may need follow-up.", (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                    } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                        $this->pushUnique($items, $this->buildItem($row, 'Rejected', $emp . "'s request was rejected.", (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                    }
                }

                $count = (int) ($this->sidebarCounts($user)['leave_requests'] ?? 0);
            }
        } elseif ($role === 'manager') {
            $empId = $this->findEmployeeId($user);
            if ($empId > 0) {
                $rows = DB::table('leave_requests as lr')
                    ->join('employees as e', 'e.id', '=', 'lr.employee_id')
                    ->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
                    ->select('lr.*', 'e.first_name', 'e.last_name', DB::raw('COALESCE(lt.name, lr.leave_type) AS leave_type_name'))
                    ->where('e.manager_id', $empId)
                    ->orderByRaw('COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC')
                    ->orderByDesc('lr.id')
                    ->limit(15)
                    ->get()
                    ->map(fn ($row) => (array) $row)
                    ->all();

                foreach ($rows as $row) {
                    $status = strtolower((string) ($row['status'] ?? ''));
                    $emp = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                    if ($status === 'pending') {
                        $this->pushUnique($items, $this->buildItem($row, 'Team request pending', $emp . ' has a leave request still moving through workflow.', (string) ($row['created_at'] ?? ''), $user, 'warning'));
                    } elseif ($status === 'rejected') {
                        $this->pushUnique($items, $this->buildItem($row, 'Team leave rejected', $emp . "'s leave request was rejected.", (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                    }
                }

                $count = (int) ($this->sidebarCounts($user)['leave_requests'] ?? 0);
            }
        } else {
            $summary = $this->sidebarCounts($user);
            $count = (int) ($summary['leave_requests'] ?? 0);

            $query = DB::table('leave_requests as lr')
                ->join('employees as e', 'e.id', '=', 'lr.employee_id')
                ->leftJoin('leave_types as lt', 'lt.id', '=', 'lr.leave_type_id')
                ->select('lr.*', 'e.first_name', 'e.last_name', 'e.department', DB::raw('COALESCE(lt.name, lr.leave_type) AS leave_type_name'));

            if ($role !== 'admin') {
                $query->where(function ($q) {
                    $q->where('lr.workflow_status', 'pending_personnel')
                        ->orWhereIn('lr.status', ['approved', 'rejected'])
                        ->orWhere('lr.workflow_status', 'like', 'returned_%');
                });
            }

            $rows = $query
                ->orderByRaw('COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC')
                ->orderByDesc('lr.id')
                ->limit(15)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();

            foreach ($rows as $row) {
                $status = strtolower((string) ($row['status'] ?? ''));
                $workflow = strtolower((string) ($row['workflow_status'] ?? ''));
                $emp = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                if ($workflow === 'pending_personnel' && $status === 'pending') {
                    $this->pushUnique($items, $this->buildItem($row, 'Needs final review', $emp . ' has a leave request waiting for final review.', (string) ($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'warning'));
                } elseif (str_contains($workflow, 'return')) {
                    $this->pushUnique($items, $this->buildItem($row, 'Returned', $emp . "'s leave request was returned for follow-up.", (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                    $this->pushUnique($items, $this->buildItem($row, 'Rejected', $emp . "'s leave request was rejected.", (string) ($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $user, 'danger'));
                } elseif ($role === 'admin' && $workflow === 'pending_department_head' && $status === 'pending') {
                    $this->pushUnique($items, $this->buildItem($row, 'New request submitted', $emp . ' submitted ' . ($row['leave_type_name'] ?? 'a leave request') . '.', (string) ($row['created_at'] ?? ''), $user, 'info'));
                }
            }
        }

        $items = array_slice($items, 0, $limit);
        $latestTs = 0;
        foreach ($items as $item) {
            $latestTs = max($latestTs, (int) ($item['when_ts'] ?? 0));
        }

        return [
            'count' => $count,
            'items' => $items,
            'latest_ts' => $latestTs,
        ];
    }

    public function dashboardNotifications(User $user, int $limit = 8): array
    {
        return $this->headerNotifications($user, $limit);
    }

    protected function findEmployeeId(User $user): int
    {
        return (int) ($user->employee?->id ?? 0);
    }

    protected function departmentIdsForUser(User $user): array
    {
        $ids = DB::table('department_head_assignments as dha')
            ->join('employees as e', 'e.id', '=', 'dha.employee_id')
            ->where('e.user_id', (int) $user->id)
            ->where('dha.is_active', 1)
            ->pluck('dha.department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($ids) && !empty($user->employee?->department_id)) {
            $ids[] = (int) $user->employee->department_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function formatTime(?string $value): string
    {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return '';
        }
        return date('M j, Y g:i A', $ts);
    }

    protected function pushUnique(array &$items, array $item): void
    {
        foreach ($items as $existing) {
            if (($existing['key'] ?? '') === ($item['key'] ?? '')) {
                return;
            }
        }
        $items[] = $item;
    }

    protected function tabForRow(array $row): string
    {
        $status = strtolower((string) ($row['status'] ?? ''));
        $workflow = strtolower((string) ($row['workflow_status'] ?? ''));
        if (str_contains($workflow, 'archiv') || $status === 'archived') {
            return 'archived';
        }
        if (str_contains($workflow, 'reject') || str_contains($workflow, 'return') || $status === 'rejected') {
            return 'rejected';
        }
        if ($workflow === 'finalized' || $status === 'approved') {
            return 'approved';
        }
        return 'pending';
    }

    protected function hrefForRow(array $row, User $user): string
    {
        if ((string) $user->role === 'employee') {
            $empId = (int) ($row['employee_id'] ?? ($user->employee?->id ?? 0));
            return route('employee-profile', $empId > 0 ? ['employee' => $empId] : []);
        }

        $params = [
            'tab' => $this->tabForRow($row),
        ];
        $requestId = (int) ($row['id'] ?? 0);
        if ($requestId > 0) {
            $params['open_detail'] = $requestId;
        }

        return route('leave.requests', $params);
    }

    protected function buildItem(array $row, string $title, string $message, string $when, User $user, string $tone = 'info'): array
    {
        $employeeName = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
        return [
            'key' => ((string) ($row['id'] ?? uniqid('notif_', true))) . '|' . $title,
            'request_id' => (int) ($row['id'] ?? 0),
            'title' => $title,
            'message' => $message,
            'when' => $when,
            'when_text' => $this->formatTime($when),
            'when_ts' => ($when && strtotime($when)) ? (int) strtotime($when) : 0,
            'tone' => $tone,
            'employee_name' => $employeeName,
            'leave_type_name' => (string) ($row['leave_type_name'] ?? $row['leave_type'] ?? 'Leave request'),
            'href' => $this->hrefForRow($row, $user),
            'tab' => $this->tabForRow($row),
        ];
    }
}
