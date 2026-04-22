<?php
if (!function_exists('app_notifications_find_employee_id')) {
    function app_notifications_find_employee_id(PDO $db, array $session): int {
        $empId = (int)($session['emp_id'] ?? 0);
        if ($empId > 0) {
            return $empId;
        }
        $userId = (int)($session['user_id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $db->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('app_notifications_department_ids_for_user')) {
    function app_notifications_department_ids_for_user(PDO $db, int $userId, ?array $employeeRow = null): array {
        $stmt = $db->prepare("SELECT dha.department_id FROM department_head_assignments dha JOIN employees e ON e.id = dha.employee_id WHERE e.user_id = ? AND dha.is_active = 1");
        $stmt->execute([$userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (empty($ids) && !empty($employeeRow['department_id'])) {
            $ids[] = (int)$employeeRow['department_id'];
        }
        return array_values(array_unique(array_filter($ids)));
    }
}

if (!function_exists('app_notification_sidebar_counts')) {
    function app_notification_sidebar_counts(PDO $db, array $session, ?array $employeeRow = null): array {
        $role = (string)($session['role'] ?? '');
        $userId = (int)($session['user_id'] ?? 0);
        $counts = [
            'leave_requests' => 0,
            'apply_leave' => 0,
        ];

        if ($role === 'department_head') {
            $deptIds = app_notifications_department_ids_for_user($db, $userId, $employeeRow);
            if (!empty($deptIds)) {
                $in = implode(',', array_fill(0, count($deptIds), '?'));
                $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'pending_department_head' AND status = 'pending' AND department_id IN ($in)");
                $stmt->execute($deptIds);
                $counts['leave_requests'] = (int)$stmt->fetchColumn();
            }
            return $counts;
        }

        if ($role === 'manager') {
            $empId = app_notifications_find_employee_id($db, $session);
            if ($empId > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN employees e ON e.id = lr.employee_id WHERE lr.status = 'pending' AND e.manager_id = ?");
                $stmt->execute([$empId]);
                $counts['leave_requests'] = (int)$stmt->fetchColumn();
            }
            return $counts;
        }

        if (in_array($role, ['personnel', 'hr'], true)) {
            $stmt = $db->query("SELECT COUNT(*) FROM leave_requests WHERE workflow_status = 'pending_personnel' AND status = 'pending'");
            $counts['leave_requests'] = (int)$stmt->fetchColumn();
            return $counts;
        }

        if ($role === 'admin') {
            $stmt = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
            $counts['leave_requests'] = (int)$stmt->fetchColumn();
            return $counts;
        }

        // Employees should no longer see a badge on Apply Leave.
        return $counts;
    }
}

if (!function_exists('app_notification_format_time')) {
    function app_notification_format_time(?string $value): string {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($value);
        if (!$ts) {
            return '';
        }
        return date('M j, Y g:i A', $ts);
    }
}

if (!function_exists('app_notification_push_unique')) {
    function app_notification_push_unique(array &$items, array $item): void {
        foreach ($items as $existing) {
            if (($existing['key'] ?? '') === ($item['key'] ?? '')) {
                return;
            }
        }
        $items[] = $item;
    }
}

if (!function_exists('app_notification_tab_for_row')) {
    function app_notification_tab_for_row(array $row): string {
        $status = strtolower((string)($row['status'] ?? ''));
        $workflow = strtolower((string)($row['workflow_status'] ?? ''));
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
}

if (!function_exists('app_notification_href_for_row')) {
    function app_notification_href_for_row(array $row, array $session): string {
        $role = (string)($session['role'] ?? '');
        if ($role === 'employee') {
            $empId = (int)($row['employee_id'] ?? ($session['emp_id'] ?? 0));
            return 'employee_profile.php' . ($empId > 0 ? ('?id=' . $empId) : '');
        }
        $tab = app_notification_tab_for_row($row);
        $requestId = (int)($row['id'] ?? 0);
        $query = http_build_query([
            'tab' => $tab,
            'open_detail' => $requestId > 0 ? $requestId : null,
        ]);
        return 'leave_requests.php' . ($query !== '' ? ('?' . $query) : '');
    }
}

if (!function_exists('app_notification_build_item')) {
    function app_notification_build_item(array $row, string $title, string $message, string $when, array $session, string $tone = 'info'): array {
        $employeeName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        return [
            'key' => ((string)($row['id'] ?? uniqid('notif_', true))) . '|' . $title,
            'request_id' => (int)($row['id'] ?? 0),
            'title' => $title,
            'message' => $message,
            'when' => $when,
            'when_text' => app_notification_format_time($when),
            'when_ts' => ($when && strtotime($when)) ? (int)strtotime($when) : 0,
            'tone' => $tone,
            'employee_name' => $employeeName,
            'leave_type_name' => (string)($row['leave_type_name'] ?? $row['leave_type'] ?? 'Leave request'),
            'href' => app_notification_href_for_row($row, $session),
            'tab' => app_notification_tab_for_row($row),
        ];
    }
}

if (!function_exists('app_header_notifications')) {
    function app_header_notifications(PDO $db, array $session, ?array $employeeRow = null, int $limit = 8): array {
        $role = (string)($session['role'] ?? '');
        $userId = (int)($session['user_id'] ?? 0);
        $items = [];
        $count = 0;

        if ($role === 'employee') {
            $empId = app_notifications_find_employee_id($db, $session);
            if ($empId > 0) {
                $stmt = $db->prepare("SELECT lr.*, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                    FROM leave_requests lr
                    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE lr.employee_id = ?
                    ORDER BY COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC, lr.id DESC
                    LIMIT 15");
                $stmt->execute([$empId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $status = strtolower((string)($row['status'] ?? ''));
                    $workflow = strtolower((string)($row['workflow_status'] ?? ''));
                    if ($workflow === 'pending_department_head') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Submitted', ($row['leave_type_name'] ?? 'Leave request') . ' is waiting for department-head approval.', (string)($row['created_at'] ?? ''), $session, 'info'));
                    } elseif ($workflow === 'pending_personnel') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Forwarded', ($row['leave_type_name'] ?? 'Leave request') . ' is now waiting for final personnel review.', (string)($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'warning'));
                    } elseif ($workflow === 'finalized' || $status === 'approved') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Approved', ($row['leave_type_name'] ?? 'Leave request') . ' was approved and finalized.', (string)($row['finalized_at'] ?? $row['personnel_checked_at'] ?? $row['created_at'] ?? ''), $session, 'success'));
                    } elseif (str_contains($workflow, 'return')) {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Returned', ($row['leave_type_name'] ?? 'Leave request') . ' was returned for follow-up.', (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                    } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Rejected', ($row['leave_type_name'] ?? 'Leave request') . ' was rejected.', (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                    }
                }
                $pendingStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
                $pendingStmt->execute([$empId]);
                $count = (int)$pendingStmt->fetchColumn();
            }
        } elseif ($role === 'department_head') {
            $deptIds = app_notifications_department_ids_for_user($db, $userId, $employeeRow);
            if (!empty($deptIds)) {
                $in = implode(',', array_fill(0, count($deptIds), '?'));
                $stmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                    FROM leave_requests lr
                    JOIN employees e ON e.id = lr.employee_id
                    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE lr.department_id IN ($in)
                    ORDER BY COALESCE(lr.personnel_checked_at, lr.finalized_at, lr.department_head_approved_at, lr.created_at) DESC, lr.id DESC
                    LIMIT 15");
                $stmt->execute($deptIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $status = strtolower((string)($row['status'] ?? ''));
                    $workflow = strtolower((string)($row['workflow_status'] ?? ''));
                    $emp = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                    if ($workflow === 'pending_department_head' && $status === 'pending') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Needs approval', $emp . ' filed ' . ($row['leave_type_name'] ?? 'a leave request') . ' and is waiting for your approval.', (string)($row['created_at'] ?? ''), $session, 'warning'));
                    } elseif ($workflow === 'pending_personnel') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Forwarded', $emp . "'s request was already forwarded to personnel.", (string)($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'success'));
                    } elseif (str_contains($workflow, 'return')) {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Returned', $emp . "'s request was returned and may need follow-up.", (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                    } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Rejected', $emp . "'s request was rejected.", (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                    }
                }
                $count = app_notification_sidebar_counts($db, $session, $employeeRow)['leave_requests'] ?? 0;
            }
        } elseif ($role === 'manager') {
            $empId = app_notifications_find_employee_id($db, $session);
            if ($empId > 0) {
                $stmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                    FROM leave_requests lr
                    JOIN employees e ON e.id = lr.employee_id
                    LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                    WHERE e.manager_id = ?
                    ORDER BY COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC, lr.id DESC
                    LIMIT 15");
                $stmt->execute([$empId]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $status = strtolower((string)($row['status'] ?? ''));
                    $emp = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                    if ($status === 'pending') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Team request pending', $emp . ' has a leave request still moving through workflow.', (string)($row['created_at'] ?? ''), $session, 'warning'));
                    } elseif ($status === 'rejected') {
                        app_notification_push_unique($items, app_notification_build_item($row, 'Team leave rejected', $emp . "'s leave request was rejected.", (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                    }
                }
                $count = app_notification_sidebar_counts($db, $session, $employeeRow)['leave_requests'] ?? 0;
            }
        } else {
            $summary = app_notification_sidebar_counts($db, $session, $employeeRow);
            $count = (int)($summary['leave_requests'] ?? 0);

            $where = $role === 'admin'
                ? "1=1"
                : "lr.workflow_status = 'pending_personnel' OR lr.status IN ('approved','rejected') OR lr.workflow_status LIKE 'returned_%'";
            $stmt = $db->prepare("SELECT lr.*, e.first_name, e.last_name, e.department, COALESCE(lt.name, lr.leave_type) AS leave_type_name
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
                WHERE $where
                ORDER BY COALESCE(lr.finalized_at, lr.personnel_checked_at, lr.department_head_approved_at, lr.created_at) DESC, lr.id DESC
                LIMIT 15");
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $status = strtolower((string)($row['status'] ?? ''));
                $workflow = strtolower((string)($row['workflow_status'] ?? ''));
                $emp = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
                if ($workflow === 'pending_personnel' && $status === 'pending') {
                    app_notification_push_unique($items, app_notification_build_item($row, 'Needs final review', $emp . ' has a leave request waiting for final review.', (string)($row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'warning'));
                } elseif (str_contains($workflow, 'return')) {
                    app_notification_push_unique($items, app_notification_build_item($row, 'Returned', $emp . "'s leave request was returned for follow-up.", (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                } elseif (str_contains($workflow, 'reject') || $status === 'rejected') {
                    app_notification_push_unique($items, app_notification_build_item($row, 'Rejected', $emp . "'s leave request was rejected.", (string)($row['personnel_checked_at'] ?? $row['department_head_approved_at'] ?? $row['created_at'] ?? ''), $session, 'danger'));
                } elseif ($role === 'admin' && $workflow === 'pending_department_head' && $status === 'pending') {
                    app_notification_push_unique($items, app_notification_build_item($row, 'New request submitted', $emp . ' submitted ' . ($row['leave_type_name'] ?? 'a leave request') . '.', (string)($row['created_at'] ?? ''), $session, 'info'));
                }
            }
        }

        $items = array_slice($items, 0, $limit);
        return [
            'count' => $count,
            'items' => $items,
        ];
    }
}

if (!function_exists('app_dashboard_notifications')) {
    function app_dashboard_notifications(PDO $db, array $session, ?array $employeeRow = null, int $limit = 8): array {
        return app_header_notifications($db, $session, $employeeRow, $limit);
    }
}
