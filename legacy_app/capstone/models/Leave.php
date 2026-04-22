<?php
class Leave {
    private $conn;
    private int $lastInsertedId = 0;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function normalizeDateInput($value): ?DateTime {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $dt = DateTime::createFromFormat('Y-m-d', trim($value));
        if (!$dt) {
            return null;
        }

        $errors = DateTime::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        $dt->setTime(0, 0, 0);
        return $dt;
    }

    public function calculateDaysBreakdown($start, $end): array {
        $startDT = $this->normalizeDateInput($start);
        $endDT = $this->normalizeDateInput($end);

        if (!$startDT || !$endDT) {
            return [
                'valid' => false,
                'days' => 0,
                'calendar_days' => 0,
                'weekend_days' => 0,
                'holiday_days' => 0,
                'message' => 'Please provide valid start and end dates.',
            ];
        }

        if ($endDT < $startDT) {
            return [
                'valid' => false,
                'days' => 0,
                'calendar_days' => 0,
                'weekend_days' => 0,
                'holiday_days' => 0,
                'message' => 'End date cannot be earlier than start date.',
            ];
        }

        $queryStart = $startDT->format('Y-m-d');
        $queryEnd = $endDT->format('Y-m-d');
        $stmt = $this->conn->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ?");
        $stmt->execute([$queryStart, $queryEnd]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $holidaySet = array_flip($holidays);

        $cursor = clone $startDT;
        $days = 0;
        $calendarDays = 0;
        $weekendDays = 0;
        $holidayDays = 0;

        while ($cursor <= $endDT) {
            $calendarDays++;
            $weekday = (int)$cursor->format('N');
            $today = $cursor->format('Y-m-d');
            $isWeekend = $weekday >= 6;
            $isHoliday = isset($holidaySet[$today]);

            if ($isWeekend) {
                $weekendDays++;
            }
            if ($isHoliday) {
                $holidayDays++;
            }
            if (!$isWeekend && !$isHoliday) {
                $days++;
            }

            $cursor->modify('+1 day');
        }

        return [
            'valid' => true,
            'days' => $days,
            'calendar_days' => $calendarDays,
            'weekend_days' => $weekendDays,
            'holiday_days' => $holidayDays,
            'message' => $days > 0 ? '' : 'The selected range contains no deductible working days.',
        ];
    }

    public function calculateDays($start, $end) {
        $breakdown = $this->calculateDaysBreakdown($start, $end);
        return (int)($breakdown['days'] ?? 0);
    }


    public function getLastInsertedId(): int {
        return $this->lastInsertedId > 0 ? $this->lastInsertedId : (int)$this->conn->lastInsertId();
    }

    public function checkOverlap($employee_id, $start, $end) {
        $query = "SELECT COUNT(*) FROM leave_requests
                  WHERE employee_id = :id
                  AND status IN ('approved','pending')
                  AND (start_date <= :end AND end_date >= :start)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':id' => $employee_id,
            ':start' => $start,
            ':end' => $end
        ]);
        return $stmt->fetchColumn() > 0;
    }

    private function getDepartmentHeadUserIdForEmployee(int $employeeId): ?int {
        $stmt = $this->conn->prepare("
            SELECT u.id
            FROM employees e
            JOIN departments d ON e.department_id = d.id
            LEFT JOIN department_head_assignments dha
                ON d.id = dha.department_id AND dha.is_active = 1
            LEFT JOIN employees dh ON dha.employee_id = dh.id
            LEFT JOIN users u ON dh.user_id = u.id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : null;
    }

        private function normalizeLeaveTypeKey(string $name): string {
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

    private function isDualDeductionLeaveType(string $name): bool {
        return $this->normalizeLeaveTypeKey($name) === 'mandatory/forced leave';
    }

    private function getEmployeeBalanceValue(int $employeeId, string $column): float {
        $allowed = ['annual_balance', 'sick_balance', 'force_balance'];
        if (!in_array($column, $allowed, true)) {
            return 0.0;
        }

        $stmt = $this->conn->prepare("SELECT {$column} FROM employees WHERE id = :id");
        $stmt->execute([':id' => $employeeId]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    private function mapLeaveTypeToBalanceColumn(string $name): string {
        $type = $this->normalizeLeaveTypeKey($name);

        switch ($type) {
            case 'vacation leave':
            case 'maternity leave':
            case 'paternity leave':
            case 'special privilege leave':
            case 'solo parent leave':
            case 'study leave':
            case 'vawc leave':
            case '10-day vawc leave':
            case 'rehabilitation leave':
            case 'rehabilitation privilege':
            case 'special leave benefits for women':
            case 'special emergency (calamity) leave':
            case 'monetization of leave credits':
            case 'terminal leave':
            case 'adoption leave':
                return 'annual_balance';

            case 'sick leave':
                return 'sick_balance';

            case 'mandatory/forced leave':
                return 'force_balance';

            default:
                return 'annual_balance';
        }
    }


public function getLeavePolicy($identifier): array {
    $leaveType = is_array($identifier) ? $identifier : $this->getLeaveType($identifier);
    if (!$leaveType) {
        return [];
    }

    $typeName = (string)($leaveType['name'] ?? '');
    $key = $this->normalizeLeaveTypeKey($typeName);

    $policy = [
        'name' => $typeName,
        'deduct_balance' => !empty($leaveType['deduct_balance']),
        'min_days_notice' => isset($leaveType['min_days_notice']) ? (int)$leaveType['min_days_notice'] : 0,
        'max_days' => isset($leaveType['max_days']) && $leaveType['max_days'] !== null ? (float)$leaveType['max_days'] : null,
        'max_days_per_year' => isset($leaveType['max_days_per_year']) && $leaveType['max_days_per_year'] !== null ? (float)$leaveType['max_days_per_year'] : null,
        'required_doc_count' => 0,
    ];

    switch ($key) {
        case 'vacation leave':
            $policy['min_days_notice'] = 5;
            break;
        case 'mandatory/forced leave':
            $policy['min_days_notice'] = 5;
            break;
        case 'maternity leave':
            $policy['deduct_balance'] = false;
            $policy['max_days'] = 105;
            $policy['required_doc_count'] = 1;
            break;
        case 'paternity leave':
            $policy['deduct_balance'] = false;
            $policy['max_days'] = 7;
            $policy['max_days_per_year'] = 7;
            $policy['required_doc_count'] = 2;
            break;
        case 'special privilege leave':
            $policy['deduct_balance'] = false;
            $policy['min_days_notice'] = 5;
            $policy['max_days'] = 3;
            $policy['max_days_per_year'] = 3;
            $policy['required_doc_count'] = 1;
            break;
        case 'solo parent leave':
            $policy['deduct_balance'] = false;
            $policy['min_days_notice'] = 5;
            $policy['max_days'] = 7;
            $policy['max_days_per_year'] = 7;
            $policy['required_doc_count'] = 1;
            break;
        case 'study leave':
            $policy['max_days'] = 180;
            $policy['required_doc_count'] = 1;
            break;
        case 'vawc leave':
        case '10-day vawc leave':
            $policy['deduct_balance'] = false;
            $policy['max_days'] = 10;
            $policy['max_days_per_year'] = 10;
            $policy['required_doc_count'] = 1;
            break;
        case 'rehabilitation leave':
        case 'rehabilitation privilege':
            $policy['max_days'] = 300;
            $policy['required_doc_count'] = 1;
            break;
        case 'special leave benefits for women':
            $policy['max_days'] = 60;
            $policy['required_doc_count'] = 1;
            break;
        case 'special emergency (calamity) leave':
            $policy['max_days'] = 5;
            $policy['max_days_per_year'] = 5;
            $policy['required_doc_count'] = 1;
            break;
        case 'terminal leave':
        case 'adoption leave':
            $policy['deduct_balance'] = false;
            $policy['required_doc_count'] = 1;
            break;
    }

    return $policy;
}

private function isForceBalanceOnly(array $extraData): bool {
    if (!empty($extraData['force_balance_only'])) {
        return true;
    }

    if (!empty($extraData['details_json']) && is_string($extraData['details_json'])) {
        $decoded = json_decode($extraData['details_json'], true);
        if (is_array($decoded) && !empty($decoded['force_balance_only'])) {
            return true;
        }
    }

    return false;
}

private function isLateSickFiling(string $filingDate, string $endDate): bool {
    $filing = $this->normalizeDateInput($filingDate);
    $end = $this->normalizeDateInput($endDate);
    if (!$filing || !$end) {
        return false;
    }

    $lateCutoff = clone $end;
    $lateCutoff->modify('+1 month');
    return $filing > $lateCutoff;
}

private function upsertLeaveRequestFormApprovalData(
    int $leaveId,
    float $vacTotal,
    float $vacLess,
    float $vacBalance,
    float $sickTotal,
    float $sickLess,
    float $sickBalance,
    float $daysWithPay,
    float $daysWithoutPay
): void {
    $stmt = $this->conn->prepare(
        "INSERT INTO leave_request_forms
            (leave_request_id, cert_vacation_total_earned, cert_vacation_less_this_application, cert_vacation_balance,
             cert_sick_total_earned, cert_sick_less_this_application, cert_sick_balance,
             approved_for_days_with_pay, approved_for_days_without_pay)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             cert_vacation_total_earned = VALUES(cert_vacation_total_earned),
             cert_vacation_less_this_application = VALUES(cert_vacation_less_this_application),
             cert_vacation_balance = VALUES(cert_vacation_balance),
             cert_sick_total_earned = VALUES(cert_sick_total_earned),
             cert_sick_less_this_application = VALUES(cert_sick_less_this_application),
             cert_sick_balance = VALUES(cert_sick_balance),
             approved_for_days_with_pay = VALUES(approved_for_days_with_pay),
             approved_for_days_without_pay = VALUES(approved_for_days_without_pay)"
    );
    $stmt->execute([
        $leaveId,
        $vacTotal,
        $vacLess,
        $vacBalance,
        $sickTotal,
        $sickLess,
        $sickBalance,
        $daysWithPay,
        $daysWithoutPay,
    ]);
}


public function apply(
    $employee_id,
    $typeIdentifier,
    $start,
    $end,
    $reason,
    $applicantUserId = null,
    $applicantRole = 'employee',
    $commutation = null,
    array $extraData = []
) {
    $leaveType = $this->getLeaveType($typeIdentifier);
    if (!$leaveType) {
        return "Invalid leave type.";
    }

    $policy = $this->getLeavePolicy($leaveType);
    $days = $this->calculateDays($start, $end);
    if ($days <= 0) {
        return "The selected range contains no deductible working days.";
    }

    if ($this->checkOverlap($employee_id, $start, $end)) {
        return "Overlapping leave exists.";
    }

    $filingDate = $extraData['filing_date'] ?? date('Y-m-d');
    $leaveSubtype = $extraData['leave_subtype'] ?? null;
    $detailsJson = $extraData['details_json'] ?? null;
    $supportingDocumentsJson = $extraData['supporting_documents_json'] ?? null;
    $medicalCertificateAttached = !empty($extraData['medical_certificate_attached']) ? 1 : 0;
    $affidavitAttached = !empty($extraData['affidavit_attached']) ? 1 : 0;
    $emergencyCase = !empty($extraData['emergency_case']) ? 1 : 0;
    $forceBalanceOnly = $this->isForceBalanceOnly($extraData);

    $startDT = $this->normalizeDateInput((string)$start);
    $filingDT = $this->normalizeDateInput((string)$filingDate);
    if ($startDT && $filingDT && !empty($policy['min_days_notice'])) {
        $diffDays = (int)$filingDT->diff($startDT)->format('%r%a');
        if ($diffDays < (int)$policy['min_days_notice']) {
            return "This leave must be filed at least {$policy['min_days_notice']} day(s) before the start date.";
        }
    }

    if (!empty($policy['max_days']) && $days > (float)$policy['max_days']) {
        return "This leave type allows up to {$policy['max_days']} day(s) per application.";
    }

    if (!empty($policy['max_days_per_year'])) {
        $stmt = $this->conn->prepare(
            "SELECT SUM(total_days)
             FROM leave_requests
             WHERE employee_id = ?
               AND leave_type_id = ?
               AND YEAR(start_date) = YEAR(?)
               AND status IN ('approved', 'pending')"
        );
        $stmt->execute([$employee_id, $leaveType['id'], $start]);
        $already = (float)($stmt->fetchColumn() ?: 0);
        if (($already + $days) > (float)$policy['max_days_per_year']) {
            return "Applying would exceed the annual maximum for {$leaveType['name']}.";
        }
    }

    if (!empty($policy['deduct_balance'])) {
        if ($this->isDualDeductionLeaveType((string)$leaveType['name'])) {
            $forceBal = $this->getEmployeeBalanceValue((int)$employee_id, 'force_balance');
            if ($forceBal < 0) {
                return "Cannot apply: leave balance is negative.";
            }

            if ($forceBalanceOnly) {
                if ($forceBal < $days) {
                    return "Insufficient {$leaveType['name']} leave balance.";
                }
            } else {
                $annualBal = $this->getEmployeeBalanceValue((int)$employee_id, 'annual_balance');
                if ($annualBal < 0) {
                    return "Cannot apply: leave balance is negative.";
                }
                if ($annualBal < $days || $forceBal < $days) {
                    return "Insufficient {$leaveType['name']} leave balance.";
                }
            }
        } else {
            $balance = (float)$this->getBalanceByType($employee_id, $leaveType['name']);
            if ($balance < 0) {
                return "Cannot apply: leave balance is negative.";
            }
            if ($balance < $days) {
                return "Insufficient {$leaveType['name']} leave balance.";
            }
        }
    }

    $snapshots = $this->getBalanceSnapshots($employee_id);

    $stmtDept = $this->conn->prepare("SELECT department_id FROM employees WHERE id = ?");
    $stmtDept->execute([$employee_id]);
    $departmentId = $stmtDept->fetchColumn();

    $status = 'pending';
    $workflowStatus = 'pending_department_head';
    $departmentHeadUserId = $this->getDepartmentHeadUserIdForEmployee((int)$employee_id);
    $departmentHeadApprovedAt = null;
    $personnelUserId = null;

    $isDepartmentHead = false;
    if ($departmentHeadUserId && (int)$applicantUserId === (int)$departmentHeadUserId) {
        $isDepartmentHead = true;
    }

    if (!$departmentHeadUserId || $isDepartmentHead) {
        $workflowStatus = 'pending_personnel';
        $departmentHeadApprovedAt = date('Y-m-d H:i:s');
    }

    if (in_array($applicantRole, ['manager', 'department_head', 'admin'], true)) {
        $workflowStatus = 'pending_personnel';
        $departmentHeadUserId = $applicantUserId ?: $departmentHeadUserId;
        $departmentHeadApprovedAt = date('Y-m-d H:i:s');
    }

    if (!empty($leaveType['auto_approve'])) {
        $status = 'approved';
        $workflowStatus = 'finalized';
    }

    if (!$departmentHeadUserId && !in_array($applicantRole, ['manager', 'department_head', 'admin'], true)) {
        return "Cannot submit leave: No department head assigned to your department.";
    }

    try {
        $query = "INSERT INTO leave_requests
            (
                employee_id,
                department_id,
                leave_type,
                leave_type_id,
                leave_subtype,
                details_json,
                filing_date,
                start_date,
                end_date,
                total_days,
                reason,
                status,
                workflow_status,
                department_head_user_id,
                personnel_user_id,
                department_head_approved_at,
                snapshot_annual_balance,
                snapshot_sick_balance,
                snapshot_force_balance,
                commutation,
                supporting_documents_json,
                medical_certificate_attached,
                affidavit_attached,
                emergency_case
            )
            VALUES
            (
                :eid,
                :dept_id,
                :type,
                :typeid,
                :leave_subtype,
                :details_json,
                :filing_date,
                :start,
                :end,
                :days,
                :reason,
                :status,
                :workflow_status,
                :department_head_user_id,
                :personnel_user_id,
                :department_head_approved_at,
                :snap_annual,
                :snap_sick,
                :snap_force,
                :commutation,
                :supporting_documents_json,
                :medical_certificate_attached,
                :affidavit_attached,
                :emergency_case
            )";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':eid' => $employee_id,
            ':dept_id' => $departmentId,
            ':type' => $leaveType['name'],
            ':typeid' => $leaveType['id'],
            ':leave_subtype' => $leaveSubtype,
            ':details_json' => $detailsJson,
            ':filing_date' => $filingDate,
            ':start' => $start,
            ':end' => $end,
            ':days' => $days,
            ':reason' => $reason,
            ':status' => $status,
            ':workflow_status' => $workflowStatus,
            ':department_head_user_id' => $departmentHeadUserId,
            ':personnel_user_id' => $personnelUserId,
            ':department_head_approved_at' => $departmentHeadApprovedAt,
            ':snap_annual' => $snapshots['annual_balance'],
            ':snap_sick' => $snapshots['sick_balance'],
            ':snap_force' => $snapshots['force_balance'],
            ':commutation' => $commutation,
            ':supporting_documents_json' => $supportingDocumentsJson,
            ':medical_certificate_attached' => $medicalCertificateAttached,
            ':affidavit_attached' => $affidavitAttached,
            ':emergency_case' => $emergencyCase
        ]);
        $this->lastInsertedId = (int)$this->conn->lastInsertId();
    } catch (\Throwable $e) {
        try {
            $query = "INSERT INTO leave_requests
                (
                    employee_id,
                    department_id,
                    leave_type,
                    leave_type_id,
                    leave_subtype,
                    details_json,
                    filing_date,
                    start_date,
                    end_date,
                    total_days,
                    reason,
                    status,
                    workflow_status,
                    department_head_user_id,
                    personnel_user_id,
                    department_head_approved_at,
                    snapshot_annual_balance,
                    snapshot_sick_balance,
                    snapshot_force_balance,
                    commutation
                )
                VALUES
                (
                    :eid,
                    :dept_id,
                    :type,
                    :typeid,
                    :leave_subtype,
                    :details_json,
                    :filing_date,
                    :start,
                    :end,
                    :days,
                    :reason,
                    :status,
                    :workflow_status,
                    :department_head_user_id,
                    :personnel_user_id,
                    :department_head_approved_at,
                    :snap_annual,
                    :snap_sick,
                    :snap_force,
                    :commutation
                )";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':eid' => $employee_id,
                ':dept_id' => $departmentId,
                ':type' => $leaveType['name'],
                ':typeid' => $leaveType['id'],
                ':leave_subtype' => $leaveSubtype,
                ':details_json' => $detailsJson,
                ':filing_date' => $filingDate,
                ':start' => $start,
                ':end' => $end,
                ':days' => $days,
                ':reason' => $reason,
                ':status' => $status,
                ':workflow_status' => $workflowStatus,
                ':department_head_user_id' => $departmentHeadUserId,
                ':personnel_user_id' => $personnelUserId,
                ':department_head_approved_at' => $departmentHeadApprovedAt,
                ':snap_annual' => $snapshots['annual_balance'],
                ':snap_sick' => $snapshots['sick_balance'],
                ':snap_force' => $snapshots['force_balance'],
                ':commutation' => $commutation
            ]);
            $this->lastInsertedId = (int)$this->conn->lastInsertId();
        } catch (\Throwable $e2) {
            return "Failed to save leave request.";
        }
    }

    if ($status === 'approved' && !empty($policy['deduct_balance'])) {
        $newId = $this->lastInsertedId > 0 ? $this->lastInsertedId : (int)$this->conn->lastInsertId();
        $this->respondToLeave($newId, null, 'approve');
    }

    return "Leave submitted successfully.";
}

    public function getBalanceSnapshots($employee_id) {
        try {
            $stmt = $this->conn->prepare("SELECT annual_balance, sick_balance, force_balance, leave_balance FROM employees WHERE id = :id");
            $stmt->execute([':id' => $employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: ['annual_balance' => 0, 'sick_balance' => 0, 'force_balance' => 0, 'leave_balance' => 0];
        } catch (\Throwable $e) {
            $stmt = $this->conn->prepare("SELECT annual_balance, sick_balance, force_balance FROM employees WHERE id = :id");
            $stmt->execute([':id' => $employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $row = $row ?: ['annual_balance' => 0, 'sick_balance' => 0, 'force_balance' => 0];
            $row['leave_balance'] = 0;
            return $row;
        }
    }

    public function getLeaveType($identifier) {
        if (is_numeric($identifier)) {
            $stmt = $this->conn->prepare("SELECT * FROM leave_types WHERE id = ?");
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM leave_types WHERE name = ?");
        }
        $stmt->execute([$identifier]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getBalanceByType($employee_id, $type) {
        if (is_numeric($type)) {
            $typeInfo = $this->getLeaveType($type);
            $name = $typeInfo ? $typeInfo['name'] : null;
        } else {
            $name = $type;
        }

        $col = $this->mapLeaveTypeToBalanceColumn((string)$name);

        $stmt = $this->conn->prepare("SELECT $col FROM employees WHERE id = :id");
        $stmt->execute([':id' => $employee_id]);
        return $stmt->fetchColumn();
    }

    private function recordAccrualLogsForEmployee(
        int $employeeId,
        float $amount,
        string $monthRef,
        string $transDate,
        string $notePrefix = 'Accrual recorded'
    ): void {
        $insert = $this->conn->prepare("
            INSERT INTO accrual_history (employee_id, amount, date_accrued, month_reference)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$employeeId, $amount, $transDate . ' 00:00:00', $monthRef]);

        $stmt = $this->conn->prepare("
            SELECT annual_balance, sick_balance
            FROM employees
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['annual_balance' => 0, 'sick_balance' => 0];

        $newAnnual = (float)$row['annual_balance'];
        $newSick   = (float)$row['sick_balance'];
        $oldAnnual = $newAnnual - $amount;
        $oldSick   = $newSick - $amount;

        $note = $notePrefix . ' for ' . $monthRef;

        $this->logBudgetChange(
            $employeeId,
            'Vacational',
            $oldAnnual,
            $newAnnual,
            'accrual',
            null,
            $note,
            $transDate
        );

        $this->logBudgetChange(
            $employeeId,
            'Sick',
            $oldSick,
            $newSick,
            'accrual',
            null,
            $note,
            $transDate
        );
    }

    public function accrueSingleEmployee(
        int $employeeId,
        float $amount = 1.25,
        ?string $monthRef = null,
        ?string $transDate = null,
        string $notePrefix = 'Manual accrual recorded'
    ): bool {
        $monthRef = $monthRef ?: date('Y-m');
        $transDate = $transDate ?: date('Y-m-d');

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("
                UPDATE employees
                SET annual_balance = annual_balance + ?,
                    sick_balance = sick_balance + ?
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $employeeId]);

            if ($stmt->rowCount() < 1) {
                throw new Exception('Employee not found.');
            }

            $this->recordAccrualLogsForEmployee($employeeId, $amount, $monthRef, $transDate, $notePrefix);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function accrueAllEmployees(
        float $amount = 1.25,
        ?string $monthRef = null,
        ?string $transDate = null,
        string $notePrefix = 'Bulk accrual recorded'
    ): array {
        $monthRef = $monthRef ?: date('Y-m');
        $transDate = $transDate ?: date('Y-m-d');

        try {
            $this->conn->beginTransaction();

            $empStmt = $this->conn->query("SELECT id FROM employees ORDER BY id ASC");
            $employeeIds = $empStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($employeeIds)) {
                $this->conn->commit();
                return [
                    'success' => true,
                    'count' => 0,
                    'message' => 'No employees found.'
                ];
            }

            $updateStmt = $this->conn->prepare("
                UPDATE employees
                SET annual_balance = annual_balance + ?,
                    sick_balance = sick_balance + ?
                WHERE id = ?
            ");

            foreach ($employeeIds as $employeeId) {
                $updateStmt->execute([$amount, $amount, $employeeId]);
                $this->recordAccrualLogsForEmployee((int)$employeeId, $amount, $monthRef, $transDate, $notePrefix);
            }

            $this->conn->commit();

            return [
                'success' => true,
                'count' => count($employeeIds),
                'message' => 'Bulk accrual completed successfully.'
            ];
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            return [
                'success' => false,
                'count' => 0,
                'message' => 'Failed to perform bulk accrual.'
            ];
        }
    }

    public function accrueMonthly(): bool {
        $result = $this->accrueAllEmployees(
            1.25,
            date('Y-m'),
            date('Y-m-t'),
            'Monthly accrual recorded'
        );

        return !empty($result['success']);
    }

    public function logBudgetChange(
        $employee_id,
        $leave_type,
        $old_balance,
        $new_balance,
        $action,
        $leave_request_id = null,
        $notes = null,
        $trans_date = null
    ) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO budget_history (employee_id, trans_date, leave_type, old_balance, new_balance, action, leave_request_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                $employee_id,
                $trans_date,
                $leave_type,
                $old_balance,
                $new_balance,
                $action,
                $leave_request_id,
                $notes
            ]);
        } catch (\Throwable $e) {
            $stmt = $this->conn->prepare(
                "INSERT INTO budget_history (employee_id, leave_type, old_balance, new_balance, action, leave_request_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            return $stmt->execute([
                $employee_id,
                $leave_type,
                $old_balance,
                $new_balance,
                $action,
                $leave_request_id,
                $notes
            ]);
        }
    }


public function respondToLeave($leave_id, $manager_id, $action, $comments = '', array $options = []) {
    if (!in_array($action, ['approve', 'reject'], true)) {
        return false;
    }

    try {
        $this->conn->beginTransaction();

        $stmt = $this->conn->prepare(
            "SELECT employee_id, total_days, leave_type, leave_type_id, filing_date, end_date, details_json
             FROM leave_requests
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([':id' => $leave_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            return false;
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';
        $this->conn->prepare(
            "UPDATE leave_requests
             SET status = :status, approved_by = :manager, manager_comments = :comments
             WHERE id = :id"
        )->execute([
            ':status' => $status,
            ':manager' => $manager_id,
            ':comments' => $comments,
            ':id' => $leave_id
        ]);

        if ($action === 'approve') {
            $typeInfo = $this->getLeaveType($leave['leave_type_id'] ?? $leave['leave_type']);
            $policy = $this->getLeavePolicy($typeInfo ?: ($leave['leave_type'] ?? ''));
            $days = (float)$leave['total_days'];
            $employeeId = (int)$leave['employee_id'];
            $forceBalanceOnly = $this->isForceBalanceOnly(['details_json' => $leave['details_json'] ?? null]);
            $typeKey = $this->normalizeLeaveTypeKey((string)($typeInfo['name'] ?? $leave['leave_type'] ?? ''));
            $isLateSick = ($typeKey === 'sick leave') && $this->isLateSickFiling((string)($leave['filing_date'] ?? ''), (string)($leave['end_date'] ?? ''));

            $daysWithPay = array_key_exists('approved_with_pay', $options) ? (float)$options['approved_with_pay'] : $days;
            $daysWithoutPay = array_key_exists('approved_without_pay', $options) ? (float)$options['approved_without_pay'] : 0.0;
            $deductDays = array_key_exists('deduct_days', $options) ? (float)$options['deduct_days'] : $days;

            if ($isLateSick && !array_key_exists('deduct_days', $options)) {
                $daysWithPay = 0.0;
                $daysWithoutPay = $days;
                $deductDays = 0.0;
            }

            if (empty($policy['deduct_balance'])) {
                $daysWithPay = $days;
                $daysWithoutPay = 0.0;
                $deductDays = 0.0;
            }

            $snapBefore = $this->getBalanceSnapshots($employeeId);
            $oldAnnual = (float)$snapBefore['annual_balance'];
            $oldSick = (float)$snapBefore['sick_balance'];
            $oldForce = (float)$snapBefore['force_balance'];

            $vacLess = 0.0;
            $sickLess = 0.0;

            if (!empty($policy['deduct_balance']) && $deductDays > 0) {
                if ($this->isDualDeductionLeaveType((string)$typeInfo['name'])) {
                    if ($forceBalanceOnly) {
                        $stmt = $this->conn->prepare(
                            "UPDATE employees
                             SET force_balance = force_balance - :days
                             WHERE id = :employee_id"
                        );
                        $stmt->execute([
                            ':days' => $deductDays,
                            ':employee_id' => $employeeId
                        ]);

                        $newForce = max(0, $oldForce - $deductDays);
                        $this->logBudgetChange(
                            $employeeId,
                            $typeInfo['name'],
                            $oldForce,
                            $newForce,
                            'deduction',
                            $leave_id,
                            'Leave approved (force balance only deduction)'
                        );

                        $stmt = $this->conn->prepare(
                            "INSERT INTO leave_balance_logs (employee_id, change_amount, reason, leave_id)
                             VALUES (?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $employeeId,
                            -1 * $deductDays,
                            'deduction_force_leave_only',
                            $leave_id
                        ]);
                    } else {
                        $stmt = $this->conn->prepare(
                            "UPDATE employees
                             SET annual_balance = annual_balance - :days,
                                 force_balance = force_balance - :days
                             WHERE id = :employee_id"
                        );
                        $stmt->execute([
                            ':days' => $deductDays,
                            ':employee_id' => $employeeId
                        ]);

                        $newAnnual = max(0, $oldAnnual - $deductDays);
                        $newForce = max(0, $oldForce - $deductDays);
                        $vacLess = $deductDays;

                        $this->logBudgetChange(
                            $employeeId,
                            'Vacational',
                            $oldAnnual,
                            $newAnnual,
                            'deduction',
                            $leave_id,
                            'Leave approved (mandatory/forced leave dual deduction - annual side)'
                        );

                        $this->logBudgetChange(
                            $employeeId,
                            $typeInfo['name'],
                            $oldForce,
                            $newForce,
                            'deduction',
                            $leave_id,
                            'Leave approved (mandatory/forced leave dual deduction - force side)'
                        );

                        $stmt = $this->conn->prepare(
                            "INSERT INTO leave_balance_logs (employee_id, change_amount, reason, leave_id)
                             VALUES (?, ?, ?, ?), (?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $employeeId,
                            -1 * $deductDays,
                            'deduction_annual_force_leave',
                            $leave_id,
                            $employeeId,
                            -1 * $deductDays,
                            'deduction_force_leave',
                            $leave_id
                        ]);
                    }
                } else {
                    $col = $this->mapLeaveTypeToBalanceColumn($typeInfo['name']);
                    $oldBalance = ($col === 'sick_balance') ? $oldSick : $oldAnnual;
                    $stmt = $this->conn->prepare(
                        "UPDATE employees
                         SET $col = $col - :days
                         WHERE id = :employee_id"
                    );
                    $stmt->execute([
                        ':days' => $deductDays,
                        ':employee_id' => $employeeId
                    ]);

                    $newBalance = max(0, $oldBalance - $deductDays);
                    if ($col === 'sick_balance') {
                        $sickLess = $deductDays;
                    } else {
                        $vacLess = $deductDays;
                    }

                    $this->logBudgetChange(
                        $employeeId,
                        $typeInfo['name'],
                        $oldBalance,
                        $newBalance,
                        'deduction',
                        $leave_id,
                        'Leave approved'
                    );

                    $stmt = $this->conn->prepare(
                        "INSERT INTO leave_balance_logs (employee_id, change_amount, reason, leave_id)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $employeeId,
                        -1 * $deductDays,
                        'deduction',
                        $leave_id
                    ]);
                }
            }

            $snapshots = $this->getBalanceSnapshots($employeeId);

            $this->conn->prepare(
                "UPDATE leave_requests
                 SET snapshot_annual_balance = ?, snapshot_sick_balance = ?, snapshot_force_balance = ?
                 WHERE id = ?"
            )->execute([
                $snapshots['annual_balance'],
                $snapshots['sick_balance'],
                $snapshots['force_balance'],
                $leave_id
            ]);

            if ($isLateSick && $deductDays <= 0) {
                $sickLess = 0.0;
            }
            if ($forceBalanceOnly) {
                $vacLess = 0.0;
            }

            $this->upsertLeaveRequestFormApprovalData(
                $leave_id,
                $oldAnnual,
                $vacLess,
                (float)$snapshots['annual_balance'],
                $oldSick,
                $sickLess,
                (float)$snapshots['sick_balance'],
                $daysWithPay,
                $daysWithoutPay
            );
        }

        $this->conn->commit();
        return true;
    } catch (Exception $e) {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        return false;
    }
}

    public function approveLeave($leave_id, $manager_id) {
        return $this->respondToLeave($leave_id, $manager_id, 'approve');
    }
}