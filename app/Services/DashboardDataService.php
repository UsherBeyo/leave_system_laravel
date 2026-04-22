<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DashboardDataService
{
    public function buildForUser(object $user): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $yearStart = now()->startOfYear()->toDateString();
        $yearEnd = now()->endOfYear()->toDateString();

        $employeeRow = DB::table('employees')->where('user_id', $user->id)->first();
        $userName = trim((string) ($employeeRow->first_name ?? '').' '.(string) ($employeeRow->last_name ?? '')) ?: $user->email;

        $data = [
            'role' => (string) $user->role,
            'userName' => $userName,
            'employeeRow' => $employeeRow,
            'today' => $today,
            'monthLabel' => now()->format('F Y'),
            'monthNames' => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
            'employeeDashboard' => [],
            'departmentHeadDashboard' => [],
            'personnelDashboard' => [],
            'managerHrDashboard' => [],
            'adminDashboard' => [],
        ];

        if ($user->role === 'employee' && $employeeRow) {
            $usageRows = DB::select(
                "SELECT leave_type, old_balance, new_balance FROM budget_history
                 WHERE employee_id = ?
                 AND COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?
                 AND action LIKE 'deduction%'",
                [(int) $employeeRow->id, $monthStart, $monthEnd]
            );

            $annualUsedThisMonth = 0.0;
            $sickUsedThisMonth = 0.0;
            foreach ($usageRows as $row) {
                $type = strtolower(trim((string) ($row->leave_type ?? '')));
                $delta = max(0.0, (float) ($row->old_balance ?? 0) - (float) ($row->new_balance ?? 0));
                if ($delta <= 0) continue;
                if (str_contains($type, 'sick')) { $sickUsedThisMonth += $delta; continue; }
                if (str_contains($type, 'force') || str_contains($type, 'mandatory')) continue;
                $annualUsedThisMonth += $delta;
            }

            $forceRows = DB::select(
                "SELECT leave_type, old_balance, new_balance FROM budget_history
                 WHERE employee_id = ?
                 AND COALESCE(trans_date, DATE(created_at)) BETWEEN ? AND ?
                 AND action LIKE 'deduction%'",
                [(int) $employeeRow->id, $yearStart, $yearEnd]
            );

            $forceUsedThisYear = 0.0;
            foreach ($forceRows as $row) {
                $type = strtolower(trim((string) ($row->leave_type ?? '')));
                $delta = max(0.0, (float) ($row->old_balance ?? 0) - (float) ($row->new_balance ?? 0));
                if ($delta > 0 && (str_contains($type, 'force') || str_contains($type, 'mandatory'))) {
                    $forceUsedThisYear += $delta;
                }
            }

            $ownRequests = DB::select(
                "SELECT lr.*, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                 FROM leave_requests lr
                 LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                 WHERE lr.employee_id = ?
                 ORDER BY COALESCE(lr.start_date, DATE(lr.created_at)) DESC, lr.id DESC",
                [(int) $employeeRow->id]
            );

            $pendingRequests = array_values(array_filter($ownRequests, fn ($r) => strtolower((string) ($r->status ?? '')) === 'pending'));
            $approvedThisMonth = 0;
            $upcomingLeaves = [];
            foreach ($ownRequests as $row) {
                $status = strtolower((string) ($row->status ?? ''));
                if (!empty($row->start_date) && $status === 'approved' && $row->start_date >= $monthStart && $row->start_date <= $monthEnd) {
                    $approvedThisMonth++;
                }
                if (count($upcomingLeaves) < 5 && !empty($row->start_date) && $row->start_date >= $today && in_array($status, ['approved', 'pending'], true)) {
                    $upcomingLeaves[] = $row;
                }
            }

            $data['employeeDashboard'] = [
                'annual' => (float) ($employeeRow->annual_balance ?? 0),
                'sick' => (float) ($employeeRow->sick_balance ?? 0),
                'force' => (float) ($employeeRow->force_balance ?? 0),
                'annual_used_this_month' => round($annualUsedThisMonth, 3),
                'sick_used_this_month' => round($sickUsedThisMonth, 3),
                'force_used_this_year' => round($forceUsedThisYear, 3),
                'pending_count' => count($pendingRequests),
                'approved_this_month' => $approvedThisMonth,
                'upcoming_count' => count($upcomingLeaves),
                'pending_requests' => array_slice($pendingRequests, 0, 6),
                'recent_requests' => array_slice($ownRequests, 0, 6),
            ];
        }

        if ($user->role === 'department_head') {
            $deptIds = DB::table('department_head_assignments as dha')
                ->join('employees as e', 'e.id', '=', 'dha.employee_id')
                ->where('e.user_id', $user->id)
                ->where('dha.is_active', 1)
                ->pluck('dha.department_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($deptIds) && !empty($employeeRow?->department_id)) {
                $deptIds = [(int) $employeeRow->department_id];
            }

            if (!empty($deptIds)) {
                $data['departmentHeadDashboard'] = [
                    'pending_count' => DB::table('leave_requests')->where('workflow_status', 'pending_department_head')->where('status', 'pending')->whereIn('department_id', $deptIds)->count(),
                    'approved_this_month' => DB::table('leave_requests')->whereNotNull('department_head_approved_at')->whereBetween(DB::raw('DATE(department_head_approved_at)'), [$monthStart, $monthEnd])->whereIn('department_id', $deptIds)->count(),
                    'returned_count' => DB::table('leave_requests')->whereIn('workflow_status', ['returned_by_personnel', 'rejected_department_head'])->whereIn('department_id', $deptIds)->count(),
                    'upcoming_count' => DB::table('leave_requests')->whereIn('status', ['pending', 'approved'])->where('start_date', '>=', $today)->whereIn('department_id', $deptIds)->count(),
                    'pending_rows' => DB::select(
                        "SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                         FROM leave_requests lr
                         JOIN employees e ON e.id = lr.employee_id
                         LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                         WHERE lr.workflow_status = 'pending_department_head' AND lr.status = 'pending' AND lr.department_id IN (".implode(',', array_fill(0, count($deptIds), '?')).")
                         ORDER BY lr.start_date ASC, lr.id ASC LIMIT 8",
                        $deptIds
                    ),
                    'upcoming_rows' => DB::select(
                        "SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                         FROM leave_requests lr
                         JOIN employees e ON e.id = lr.employee_id
                         LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                         WHERE lr.status IN ('pending','approved') AND lr.start_date >= ? AND lr.department_id IN (".implode(',', array_fill(0, count($deptIds), '?')).")
                         ORDER BY lr.start_date ASC, lr.id ASC LIMIT 8",
                        array_merge([$today], $deptIds)
                    ),
                    'recent_rows' => DB::select(
                        "SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                         FROM leave_requests lr
                         JOIN employees e ON e.id = lr.employee_id
                         LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                         WHERE lr.department_head_user_id = ? AND lr.department_head_approved_at IS NOT NULL
                         ORDER BY lr.department_head_approved_at DESC, lr.id DESC LIMIT 6",
                        [$user->id]
                    ),
                ];
            }
        }

        if ($user->role === 'personnel') {
            $data['personnelDashboard'] = [
                'pending_count' => DB::table('leave_requests')->where('workflow_status', 'pending_personnel')->where('status', 'pending')->count(),
                'print_queue_count' => DB::table('leave_requests')->where('workflow_status', 'finalized')->whereRaw("COALESCE(print_status, '') = 'pending_print'")->count(),
                'reviewed_this_month' => DB::table('leave_requests')->whereNotNull('personnel_checked_at')->whereBetween(DB::raw('DATE(personnel_checked_at)'), [$monthStart, $monthEnd])->count(),
                'upcoming_count' => DB::table('leave_requests')->where('status', 'approved')->where('start_date', '>=', $today)->count(),
                'pending_rows' => DB::select("SELECT lr.*, e.first_name, e.last_name, e.department, e.annual_balance, e.sick_balance, e.force_balance,
                        COALESCE(lt.name, lr.leave_type) AS leave_type_name
                        FROM leave_requests lr
                        JOIN employees e ON e.id = lr.employee_id
                        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                        WHERE lr.workflow_status = 'pending_personnel' AND lr.status = 'pending'
                        ORDER BY lr.start_date ASC, lr.id ASC LIMIT 8"),
                'print_queue_rows' => DB::select("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                        FROM leave_requests lr
                        JOIN employees e ON e.id = lr.employee_id
                        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                        WHERE lr.workflow_status = 'finalized' AND COALESCE(lr.print_status, '') = 'pending_print'
                        ORDER BY COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.created_at) DESC, lr.id DESC LIMIT 8"),
                'upcoming_rows' => DB::select("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                        FROM leave_requests lr
                        JOIN employees e ON e.id = lr.employee_id
                        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                        WHERE lr.status = 'approved' AND lr.start_date >= ?
                        ORDER BY lr.start_date ASC, lr.id ASC LIMIT 6", [$today]),
            ];
        }

        if (in_array($user->role, ['manager', 'hr'], true)) {
            if ($user->role === 'manager' && !empty($employeeRow?->id)) {
                $pendingCount = DB::selectOne(
                    "SELECT COUNT(*) AS aggregate FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id WHERE lr.status = 'pending' AND e.manager_id = ?",
                    [(int) $employeeRow->id]
                )->aggregate ?? 0;
                $pendingRows = DB::select(
                    "SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                     FROM leave_requests lr
                     JOIN employees e ON e.id = lr.employee_id
                     LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                     WHERE lr.status = 'pending' AND e.manager_id = ?
                     ORDER BY lr.start_date ASC, lr.id ASC LIMIT 8",
                    [(int) $employeeRow->id]
                );
            } else {
                $pendingCount = DB::table('leave_requests')->where('status', 'pending')->count();
                $pendingRows = DB::select("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                    FROM leave_requests lr
                    JOIN employees e ON e.id = lr.employee_id
                    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE lr.status = 'pending'
                    ORDER BY lr.start_date ASC, lr.id ASC LIMIT 8");
            }

            $mostAbsent = DB::selectOne("SELECT employee_id, COUNT(*) AS cnt FROM leave_requests WHERE status='approved' GROUP BY employee_id ORDER BY cnt DESC LIMIT 1");
            $mostAbsentName = '';
            if ($mostAbsent?->employee_id) {
                $emp = DB::table('employees')->select('first_name', 'last_name')->where('id', (int) $mostAbsent->employee_id)->first();
                $mostAbsentName = trim((string) ($emp->first_name ?? '').' '.(string) ($emp->last_name ?? ''));
            }

            $data['managerHrDashboard'] = [
                'pending_count' => (int) $pendingCount,
                'approved_this_month' => DB::table('leave_requests')->where('status', 'approved')->whereBetween('start_date', [$monthStart, $monthEnd])->count(),
                'most_absent_name' => $mostAbsentName,
                'most_absent_count' => (int) ($mostAbsent->cnt ?? 0),
                'monthly_data' => DB::select("SELECT MONTH(start_date) AS m, COUNT(*) AS cnt FROM leave_requests WHERE status='approved' GROUP BY MONTH(start_date) ORDER BY MONTH(start_date)"),
                'dept_chart_data' => DB::select("SELECT department, COUNT(*) AS cnt FROM employees GROUP BY department ORDER BY department"),
                'pending_rows' => $pendingRows,
            ];
        }

        if ($user->role === 'admin') {
            $data['adminDashboard'] = [
                'total_employees' => DB::table('employees')->count(),
                'pending_count' => DB::table('leave_requests')->where('status', 'pending')->count(),
                'approved_count' => DB::table('leave_requests')->where('status', 'approved')->count(),
                'print_queue_count' => DB::table('leave_requests')->where('workflow_status', 'finalized')->whereRaw("COALESCE(print_status, '') = 'pending_print'")->count(),
                'dept_data' => DB::select("SELECT department, COUNT(*) AS cnt FROM employees GROUP BY department ORDER BY department"),
                'role_data' => DB::select("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY role"),
                'recent_users' => DB::select("SELECT e.first_name, e.last_name, u.role, e.department FROM users u JOIN employees e ON e.user_id = u.id ORDER BY e.id DESC LIMIT 8"),
            ];
        }

        return $data;
    }
}
