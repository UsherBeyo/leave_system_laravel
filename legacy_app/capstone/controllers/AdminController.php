<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Leave.php';
require_once '../helpers/Flash.php';

if (empty($_SESSION['role'])) {
    die("Access denied");
}

$db = (new Database())->connect();
$userModel = new User($db);
$leaveModel = new Leave($db);

function undertimeDaysFromChart(int $hours, int $minutes): float {
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
        56=>117, 57=>119, 58=>121, 59=>123, 60=>125
    ];

    $hoursTh = max(0, $hours) * 125;
    $minsTh  = $minMapTh[$minutes] ?? 0;

    $totalTh = $hoursTh + $minsTh;
    return $totalTh / 1000;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }

    if (isset($_POST['record_undertime'])) {
        $empId = intval($_POST['employee_id']);
        $date = $_POST['date'];
        $hours = intval($_POST['hours'] ?? 0);
        $minutes = intval($_POST['undertime_minutes'] ?? 0);
        $withPay = isset($_POST['with_pay']) ? 1 : 0;

        if (!in_array($_SESSION['role'], ['admin','hr','personnel'], true)) {
            die("Access denied");
        }

        $deduct = undertimeDaysFromChart($hours, $minutes);

                $stmt = $db->prepare("SELECT annual_balance, sick_balance, force_balance FROM employees WHERE id = ?");
        $stmt->execute([$empId]);
        $balRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'annual_balance' => 0,
            'sick_balance' => 0,
            'force_balance' => 0,
        ];

        $oldBal = floatval($balRow['annual_balance']);
        $sickBalMeta = floatval($balRow['sick_balance']);
        $forceBalMeta = floatval($balRow['force_balance']);

        $newBal = max(0, $oldBal - $deduct);
        $db->prepare("UPDATE employees SET annual_balance = ? WHERE id = ?")->execute([$newBal, $empId]);

        $utMeta =
            'UT_DEDUCT=' . number_format($deduct, 3, '.', '') .
            ';VAC_OLD=' . number_format($oldBal, 3, '.', '') .
            ';VAC_NEW=' . number_format($newBal, 3, '.', '') .
            ';VAC=' . number_format($newBal, 3, '.', '') .
            ';SICK=' . number_format($sickBalMeta, 3, '.', '') .
            ';FORCE=' . number_format($forceBalMeta, 3, '.', '') .
            ';H=' . $hours .
            ';M=' . $minutes;

        $leaveModel->logBudgetChange(
            $empId,
            'Vacational',
            $oldBal,
            $newBal,
            $withPay ? 'undertime_paid' : 'undertime_unpaid',
            null,
            'Undertime ' . $hours . 'h ' . $minutes . 'm | ' . $utMeta,
            $date
        );

        $stmt2 = $db->prepare("INSERT INTO leave_balance_logs (employee_id, change_amount, reason) VALUES (?, ?, ?)");
        $stmt2->execute([$empId, -1 * $deduct, $withPay ? 'undertime_paid' : 'undertime_unpaid']);

        flash_redirect("../views/employee_profile.php?id=$empId", 'success', 'Undertime recorded successfully');
    }

    if (isset($_POST['update_employee'])) {
        $empId = intval($_POST['employee_id']);
        $role = $_SESSION['role'] ?? '';
        $sessionEmpId = intval($_SESSION['emp_id'] ?? 0);

        if ($role === 'employee') {
            if ($sessionEmpId !== $empId) {
                die("You can only update your own profile");
            }
        } elseif (!in_array($role, ['admin','hr','personnel','manager','department_head'], true)) {
            die("Access denied");
        }

        $rowStmt = $db->prepare("SELECT e.*, u.role AS user_role FROM employees e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
        $rowStmt->execute([$empId]);
        $existing = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            die("Employee not found");
        }

        $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : ($existing['first_name'] ?? '');
        $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : ($existing['middle_name'] ?? null);
        $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : ($existing['last_name'] ?? '');
        $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : ($existing['department_id'] ?? 0);
        $department_name = '';
        if ($department_id) {
            $deptStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
            $deptStmt->execute([$department_id]);
            $department_name = $deptStmt->fetchColumn() ?: '';
        } else {
            $department_name = isset($_POST['department']) ? trim($_POST['department']) : ($existing['department'] ?? '');
        }
        $position = isset($_POST['position']) ? trim($_POST['position']) : ($existing['position'] ?? null);
        $salary = (isset($_POST['salary']) && $_POST['salary'] !== '') ? floatval($_POST['salary']) : ($existing['salary'] ?? null);
        $statusField = isset($_POST['status']) ? trim($_POST['status']) : ($existing['status'] ?? null);
        $civil_status = isset($_POST['civil_status']) ? trim($_POST['civil_status']) : ($existing['civil_status'] ?? null);
        $entrance_to_duty = isset($_POST['entrance_to_duty']) ? trim($_POST['entrance_to_duty']) : ($existing['entrance_to_duty'] ?? null);
        $unit = isset($_POST['unit']) ? trim($_POST['unit']) : ($existing['unit'] ?? null);
        $gsis_policy_no = isset($_POST['gsis_policy_no']) ? trim($_POST['gsis_policy_no']) : ($existing['gsis_policy_no'] ?? null);
        $national_ref = isset($_POST['national_reference_card_no']) ? trim($_POST['national_reference_card_no']) : ($existing['national_reference_card_no'] ?? null);

        $picPath = null;
        if (!empty($_FILES['profile_pic']['name'])) {
            $dest = '../uploads/' . uniqid() . '_' . basename($_FILES['profile_pic']['name']);
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest);
            $picPath = $dest;
        }

        $isAdminHrPersonnel = in_array($role, ['admin','hr','personnel'], true);

        $manager_id = $existing['manager_id'] ?? null;
        $annual = $existing['annual_balance'];
        $sick  = $existing['sick_balance'];
        $force = $existing['force_balance'];

        if ($isAdminHrPersonnel) {
            $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : NULL;

            if (isset($_POST['annual_balance']) && $_POST['annual_balance'] !== '') $annual = floatval($_POST['annual_balance']);
            if (isset($_POST['sick_balance']) && $_POST['sick_balance'] !== '') $sick = floatval($_POST['sick_balance']);
            if (isset($_POST['force_balance']) && $_POST['force_balance'] !== '') $force = intval($_POST['force_balance']);
        }

        $oldBalances = [
            'annual_balance' => floatval($existing['annual_balance']),
            'sick_balance'   => floatval($existing['sick_balance']),
            'force_balance'  => intval($existing['force_balance']),
        ];

        if ($isAdminHrPersonnel) {
            if ($picPath) {
                $stmt = $db->prepare("UPDATE employees SET first_name=?, middle_name=?, last_name=?, department=?, department_id=?, position=?, salary=?, status=?, civil_status=?, entrance_to_duty=?, unit=?, gsis_policy_no=?, national_reference_card_no=?, manager_id=?, annual_balance=?, sick_balance=?, force_balance=?, profile_pic=? WHERE id=?");
                $stmt->execute([$first_name,$middle_name,$last_name,$department_name,$department_id,$position,$salary,$statusField,$civil_status,$entrance_to_duty,$unit,$gsis_policy_no,$national_ref,$manager_id,$annual,$sick,$force,$picPath,$empId]);
            } else {
                $stmt = $db->prepare("UPDATE employees SET first_name=?, middle_name=?, last_name=?, department=?, department_id=?, position=?, salary=?, status=?, civil_status=?, entrance_to_duty=?, unit=?, gsis_policy_no=?, national_reference_card_no=?, manager_id=?, annual_balance=?, sick_balance=?, force_balance=? WHERE id=?");
                $stmt->execute([$first_name,$middle_name,$last_name,$department_name,$department_id,$position,$salary,$statusField,$civil_status,$entrance_to_duty,$unit,$gsis_policy_no,$national_ref,$manager_id,$annual,$sick,$force,$empId]);
            }

            // Handle department_head assignment
            if ($existing['user_role'] === 'department_head') {
                if ($department_id) {
                    $db->prepare("INSERT INTO department_head_assignments (department_id, employee_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE department_id = VALUES(department_id), is_active = 1")->execute([$department_id, $empId]);
                } else {
                    // Remove assignment if no department
                    $db->prepare("DELETE FROM department_head_assignments WHERE employee_id = ?")->execute([$empId]);
                }
            }

            if (floatval($oldBalances['annual_balance']) != floatval($annual)) {
                $leaveModel->logBudgetChange($empId, 'Annual', $oldBalances['annual_balance'], $annual, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if (floatval($oldBalances['sick_balance']) != floatval($sick)) {
                $leaveModel->logBudgetChange($empId, 'Sick', $oldBalances['sick_balance'], $sick, 'adjustment', null, 'Admin/personnel manual adjustment');
            }
            if (intval($oldBalances['force_balance']) != intval($force)) {
                $leaveModel->logBudgetChange($empId, 'Force', $oldBalances['force_balance'], $force, 'adjustment', null, 'Admin/personnel manual adjustment');
            }

            flash_redirect('../views/manage_employees.php', 'success', 'Employee record updated');
        }

        if ($picPath) {
            $stmt = $db->prepare("UPDATE employees SET first_name=?, middle_name=?, last_name=?, department=?, department_id=?, position=?, salary=?, status=?, civil_status=?, entrance_to_duty=?, unit=?, gsis_policy_no=?, national_reference_card_no=?, profile_pic=? WHERE id=?");
            $stmt->execute([$first_name,$middle_name,$last_name,$department_name,$department_id,$position,$salary,$statusField,$civil_status,$entrance_to_duty,$unit,$gsis_policy_no,$national_ref,$picPath,$empId]);
        } else {
            $stmt = $db->prepare("UPDATE employees SET first_name=?, middle_name=?, last_name=?, department=?, department_id=?, position=?, salary=?, status=?, civil_status=?, entrance_to_duty=?, unit=?, gsis_policy_no=?, national_reference_card_no=? WHERE id=?");
            $stmt->execute([$first_name,$middle_name,$last_name,$department_name,$department_id,$position,$salary,$statusField,$civil_status,$entrance_to_duty,$unit,$gsis_policy_no,$national_ref,$empId]);
        }

        flash_redirect("../views/employee_profile.php?id=$empId", 'success', 'Profile updated');
    }

    if (isset($_POST['add_history'])) {
        $empId = intval($_POST['employee_id']);
        $typeId = intval($_POST['leave_type_id']);
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $days = isset($_POST['total_days']) && $_POST['total_days'] !== '' ? floatval($_POST['total_days']) : 0;
        $reason = trim($_POST['reason'] ?? '');
        $earningAmount = isset($_POST['earning_amount']) && $_POST['earning_amount'] !== '' ? floatval($_POST['earning_amount']) : 0;
        $status = 'approved';
        $approved_by = $_SESSION['user_id'];

        $typeName = '';
        $ltInfo = null;
        if ($typeId === 0) {
            $typeName = 'Vacational Accrual Earned';
        } else {
            $ltStmt = $db->prepare("SELECT * FROM leave_types WHERE id = ?");
            $ltStmt->execute([$typeId]);
            $ltInfo = $ltStmt->fetch(PDO::FETCH_ASSOC);
            if ($ltInfo) {
                $typeName = $ltInfo['name'];
            }
        }

        $snapshots = $leaveModel->getBalanceSnapshots($empId);
        if (isset($_POST['snapshot_annual_balance']) && $_POST['snapshot_annual_balance'] !== '') {
            $snapshots['annual_balance'] = floatval($_POST['snapshot_annual_balance']);
        }
        if (isset($_POST['snapshot_sick_balance']) && $_POST['snapshot_sick_balance'] !== '') {
            $snapshots['sick_balance'] = floatval($_POST['snapshot_sick_balance']);
        }
        if (isset($_POST['snapshot_force_balance']) && $_POST['snapshot_force_balance'] !== '') {
            $snapshots['force_balance'] = floatval($_POST['snapshot_force_balance']);
        }

        $effectiveSnapshot = $snapshots;

        if ($typeId === 0) {
            if ($earningAmount <= 0) {
                flash_redirect("../views/employee_profile.php?id=$empId", 'error', 'Earning amount required for accrual');
            }

            $stmt = $db->prepare("
                INSERT INTO leave_requests
                    (employee_id, leave_type, leave_type_id, start_date, end_date, total_days, reason, status, approved_by,
                     workflow_status, snapshot_annual_balance, snapshot_sick_balance, snapshot_force_balance)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $leaveTypeName = 'Vacational Accrual Earned';
            $stmt->execute([
                $empId,
                $leaveTypeName,
                0,
                $start,
                $end,
                $earningAmount,
                $reason,
                'approved',
                $approved_by,
                'finalized',
                $snapshots['annual_balance'],
                $snapshots['sick_balance'],
                $snapshots['force_balance']
            ]);

            $leave_id = $db->lastInsertId();

            $leaveModel->logBudgetChange(
                $empId,
                $leaveTypeName,
                0,
                0,
                'earning',
                $leave_id,
                'Historical accrual earning (history only)',
                $start
            );

            flash_redirect("../views/employee_profile.php?id=$empId", 'success', 'Historical entry added successfully');
        }

        if ($typeId === -1) {
            $undertimeHours = intval($_POST['undertime_hours'] ?? 0);
            $undertimeMinutes = intval($_POST['undertime_minutes'] ?? 0);
            $withPayUT = isset($_POST['undertime_with_pay']) ? 1 : 0;

            $totalUTMin = ($undertimeHours * 60) + $undertimeMinutes;

            if ($totalUTMin <= 0) {
                flash_redirect("../views/employee_profile.php?id=$empId", 'error', 'Undertime minutes required');
            }

            $deductUT = undertimeDaysFromChart($undertimeHours, $undertimeMinutes);

            $vacTyped  = floatval($snapshots['annual_balance'] ?? 0);
            $sickTyped = floatval($snapshots['sick_balance'] ?? 0);
            $forceTyped = floatval($snapshots['force_balance'] ?? 0);

            $oldBalUT = $vacTyped;
            $newBalUT = $vacTyped;

            $meta = "UT_DEDUCT=" . number_format($deductUT, 3, '.', '') .
                    ";VAC=" . number_format($vacTyped, 3, '.', '') .
                    ";SICK=" . number_format($sickTyped, 3, '.', '') .
                    ";FORCE=" . number_format($forceTyped, 3, '.', '') .
                    ";H=" . $undertimeHours . ";M=" . $undertimeMinutes;

            $leaveModel->logBudgetChange(
                $empId,
                'Vacational',
                $oldBalUT,
                $newBalUT,
                $withPayUT ? 'undertime_paid' : 'undertime_unpaid',
                null,
                'Historical undertime (no current balance affected) | ' . $meta,
                $start
            );

            flash_redirect("../views/employee_profile.php?id=$empId", 'success', 'Historical undertime recorded successfully');
        }

        $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, leave_type, leave_type_id, start_date, end_date, total_days, reason, status, approved_by, workflow_status, snapshot_annual_balance, snapshot_sick_balance, snapshot_force_balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$empId, $typeName, $typeId, $start, $end, $days, $reason, $status, $approved_by, 'finalized', $snapshots['annual_balance'], $snapshots['sick_balance'], $snapshots['force_balance']]);
        $leave_id = $db->lastInsertId();

        if ($ltInfo && $ltInfo['deduct_balance']) {
            $bucket = historyBalanceBucketForLeaveName((string)($ltInfo['name'] ?? ''));

            $oldBalance = floatval($effectiveSnapshot[$bucket] ?? 0);
            $newBalance = max(0, $oldBalance - $days);
            $effectiveSnapshot[$bucket] = $newBalance;

            $leaveModel->logBudgetChange(
                $empId,
                $ltInfo['name'],
                $oldBalance,
                $newBalance,
                'deduction',
                $leave_id,
                'Historical leave entry (no current balance affected)',
                $start
            );

            $stmtLog = $db->prepare("INSERT INTO leave_balance_logs (employee_id, change_amount, reason, leave_id) VALUES (?, ?, ?, ?)");
            $stmtLog->execute([$empId, -1 * $days, 'historical_deduction', $leave_id]);
        }

        flash_redirect("../views/employee_profile.php?id=$empId", 'success', 'Historical entry added successfully');
    }

    if ($_SESSION['role'] !== 'admin') {
        die("Access denied");
    }

    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name']);
    $department_id = intval($_POST['department_id'] ?? 0);
    $department_name = '';
    if ($department_id) {
        $deptStmt = $db->prepare("SELECT name FROM departments WHERE id = ?");
        $deptStmt->execute([$department_id]);
        $department_name = $deptStmt->fetchColumn() ?: '';
    }
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : NULL;
    $role = isset($_POST['role']) ? $_POST['role'] : 'employee';
    $password = trim($_POST['password']);
    $salary = (isset($_POST['salary']) && $_POST['salary'] !== '') ? floatval($_POST['salary']) : null;
    $activation_token = bin2hex(random_bytes(32));

    $allowedRoles = ['admin','employee','manager','hr','department_head','personnel'];
    if (!in_array($role, $allowedRoles, true)) {
        die("Invalid role selected");
    }

    try {
        $db->beginTransaction();

        $userModel->create($email, $password, $role, $activation_token);
        $user_id = $db->lastInsertId();

        $db->prepare("UPDATE users SET is_active=1, activation_token=NULL WHERE id = ?")->execute([$user_id]);

        $picPath = null;
        if (!empty($_FILES['profile_pic']['name'])) {
            $dest = '../uploads/' . uniqid() . '_' . basename($_FILES['profile_pic']['name']);
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest);
            $picPath = $dest;
        }

        $stmt = $db->prepare("INSERT INTO employees 
            (user_id, first_name, middle_name, last_name, department, department_id, position, salary, status, civil_status, entrance_to_duty, unit, gsis_policy_no, national_reference_card_no, manager_id, annual_balance, sick_balance, force_balance, profile_pic) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $first_name,
            ($middle_name !== '' ? $middle_name : null),
            $last_name,
            $department_name, // store name for backward compatibility
            $department_id,
            $_POST['position'] ?? null,
            $salary,
            $_POST['status'] ?? null,
            $_POST['civil_status'] ?? null,
            $_POST['entrance_to_duty'] ?? null,
            $_POST['unit'] ?? null,
            $_POST['gsis_policy_no'] ?? null,
            $_POST['national_reference_card_no'] ?? null,
            $manager_id,
            0,
            0,
            5,
            $picPath
        ]);

        $employee_id = $db->lastInsertId();

        // If role is department_head, assign to department
        if ($role === 'department_head' && $department_id) {
            $db->prepare("INSERT INTO department_head_assignments (department_id, employee_id, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE employee_id = VALUES(employee_id), is_active = 1")->execute([$department_id, $employee_id]);
        }

        $db->commit();
        flash_redirect('../views/manage_employees.php', 'success', 'Employee created successfully');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "Error: " . $e->getMessage();
    }
}

function normalizeLeaveTypeKey(string $name): string {
    $key = strtolower(trim($name));
    $key = preg_replace('/\s+/', ' ', $key);
    $key = str_replace([' / ', ' /', '/ '], '/', $key);

    $aliases = [
        'vacation' => 'vacation leave',
        'vacational' => 'vacation leave',
        'annual' => 'vacation leave',

        'sick' => 'sick leave',

        'mandatory/force leave' => 'mandatory/forced leave',
        'mandatory force leave' => 'mandatory/forced leave',
        'mandatory/forced leave' => 'mandatory/forced leave',
        'force' => 'mandatory/forced leave',
        'force leave' => 'mandatory/forced leave',
        'forced' => 'mandatory/forced leave',
        'forced leave' => 'mandatory/forced leave',
        'mandatory leave' => 'mandatory/forced leave',
        'mandatory' => 'mandatory/forced leave',
    ];

    return $aliases[$key] ?? $key;
}

function historyBalanceBucketForLeaveName(string $name): string {
    $type = normalizeLeaveTypeKey($name);

    switch ($type) {
        case 'sick leave':
            return 'sick_balance';

        case 'mandatory/forced leave':
            return 'force_balance';

        default:
            return 'annual_balance';
    }
}
