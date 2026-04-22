<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';

if (empty($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit();
}

require_once '../models/Leave.php';
require_once '../models/LeaveAttachment.php';
require_once '../helpers/Flash.php';
require_once '../models/LeaveType.php';
require_once '../services/Mail.php';
require_once '../helpers/Validator.php';
require_once '../helpers/ErrorHandler.php';

$db = (new Database())->connect();
$leaveModel = new Leave($db);
$leaveAttachmentModel = new LeaveAttachment($db);

$action = $_POST['action'] ?? null;
$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);


function workflowError(string $message, string $type = 'error'): void {
    flash_redirect('../views/leave_requests.php', $type, $message);
}

function sanitizeAttachmentOriginalName(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name) ?? 'attachment';
    $name = preg_replace('/\s+/', ' ', $name) ?? 'attachment';
    return substr($name !== '' ? $name : 'attachment', 0, 180);
}

function uploadLeaveAttachments(array $files, int $leaveId, int $userId, LeaveAttachment $attachmentModel): array {
    $result = ['saved' => 0, 'errors' => []];

    if ($leaveId <= 0 || empty($files) || !isset($files['name']) || !is_array($files['name'])) {
        return $result;
    }

    $allowed = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
    $maxFiles = 5;
    $maxBytes = 10 * 1024 * 1024;

    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];

    $total = min(count($names), $maxFiles);
    if (count($names) > $maxFiles) {
        $result['errors'][] = 'Only the first 5 attachments were processed.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $relativeDir = 'uploads/leave_attachments/' . date('Y') . '/' . date('m');
    $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0777, true) && !is_dir($absoluteDir)) {
        $result['errors'][] = 'Attachment upload directory could not be created.';
        return $result;
    }

    for ($i = 0; $i < $total; $i++) {
        $originalName = (string)($names[$i] ?? '');
        $tmp = (string)($tmpNames[$i] ?? '');
        $err = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        $size = (int)($sizes[$i] ?? 0);

        if ($err === UPLOAD_ERR_NO_FILE || $originalName === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $result['errors'][] = sanitizeAttachmentOriginalName($originalName) . ' could not be uploaded.';
            continue;
        }
        if ($size <= 0 || $size > $maxBytes) {
            $result['errors'][] = sanitizeAttachmentOriginalName($originalName) . ' exceeds the 10MB limit.';
            continue;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            $result['errors'][] = sanitizeAttachmentOriginalName($originalName) . ' has an unsupported file type.';
            continue;
        }

        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, $allowed[$ext], true)) {
            $result['errors'][] = sanitizeAttachmentOriginalName($originalName) . ' failed file validation.';
            continue;
        }

        $storedName = uniqid('leave_', true) . '.' . $ext;
        $dest = $absoluteDir . '/' . $storedName;
        if (!move_uploaded_file($tmp, $dest)) {
            $result['errors'][] = sanitizeAttachmentOriginalName($originalName) . ' could not be saved.';
            continue;
        }

        $attachmentModel->create([
            'leave_request_id' => $leaveId,
            'original_name' => sanitizeAttachmentOriginalName($originalName),
            'stored_name' => $storedName,
            'file_path' => $relativeDir . '/' . $storedName,
            'mime_type' => $mime,
            'file_size' => $size,
            'document_type' => 'supporting_document',
            'uploaded_by_user_id' => $userId,
        ]);
        $result['saved']++;
    }

    return $result;
}



function countUploadedFiles(array $files): int {
    if (empty($files['name']) || !is_array($files['name'])) {
        return 0;
    }
    $count = 0;
    foreach ($files['name'] as $name) {
        if (trim((string)$name) !== '') {
            $count++;
        }
    }
    return $count;
}

function isLateSickFilingForPersonnel(array $row): bool {
    $leaveType = strtolower(trim((string)($row['leave_type_name'] ?? $row['leave_type'] ?? '')));
    if (!in_array($leaveType, ['sick leave', 'sick'], true)) {
        return false;
    }

    $filingDate = trim((string)($row['filing_date'] ?? ''));
    $endDate = trim((string)($row['end_date'] ?? ''));
    if ($filingDate === '' || $endDate === '') {
        return false;
    }

    try {
        $filing = new DateTime($filingDate);
        $end = new DateTime($endDate);
        $end->modify('+1 month');
        return $filing > $end;
    } catch (Throwable $t) {
        return false;
    }
}

function fetchLeaveForWorkflow(PDO $db, int $leaveId): ?array {
    $stmt = $db->prepare("
        SELECT lr.*, e.user_id AS employee_user_id, e.first_name, e.last_name, u.email,
               COALESCE(lt.name, lr.leave_type) AS leave_type_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        WHERE lr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$leaveId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}


if ($action === 'mark_printed') {
    if (!in_array($role, ['personnel','hr','admin'], true)) {
        workflowError('Unauthorized access');
    }
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        workflowError('CSRF validation failed.');
    }

    $leave_id = (int)($_POST['leave_id'] ?? 0);
    $row = fetchLeaveForWorkflow($db, $leave_id);
    if (!$row) {
        workflowError('Leave request not found');
    }

    $workflow = trim((string)($row['workflow_status'] ?? ''));
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($workflow !== 'finalized' && $status !== 'approved') {
        workflowError('Only finalized or approved requests can be marked as printed.');
    }

    $stmt = $db->prepare("UPDATE leave_requests SET print_status = 'printed' WHERE id = ?");
    $stmt->execute([$leave_id]);
    flash_redirect('../views/leave_requests.php?tab=approved', 'success', 'Leave request marked as printed');
}

if ($action === 'approve') {
    if (!in_array($role, ['manager','department_head','personnel','hr','admin'], true)) {
        workflowError("Unauthorized access");
    }
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        workflowError("CSRF validation failed.");
    }

    $leave_id = (int)($_POST['leave_id'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');
    $row = fetchLeaveForWorkflow($db, $leave_id);
    if (!$row) {
        flash_redirect('../views/leave_requests.php', 'error', 'Leave request not found');
    }

    $workflow = trim((string)($row['workflow_status'] ?? ''));

    // Stage 1: Department Head approval -> forward to personnel
    if ($workflow === '' || $workflow === 'pending_department_head') {
        if (!in_array($role, ['manager','department_head','admin'], true)) {
            workflowError("Unauthorized access");
        }

        if ($role === 'department_head') {
            // Check if this user is the department head for the employee's department
            $stmt = $db->prepare("SELECT e.department_id FROM employees e WHERE e.id = ?");
            $stmt->execute([$row['employee_id']]);
            $deptId = $stmt->fetchColumn();
            if (!$deptId) {
                workflowError("Employee has no department assigned");
            }
            $stmt2 = $db->prepare("SELECT 1 FROM department_head_assignments WHERE department_id = ? AND employee_id = (SELECT id FROM employees WHERE user_id = ?) AND is_active = 1");
            $stmt2->execute([$deptId, $userId]);
            if (!$stmt2->fetch()) {
                workflowError("Unauthorized: You are not the department head for this employee's department");
            }
        } elseif ($role !== 'admin' && !empty($row['department_head_user_id']) && (int)$row['department_head_user_id'] !== $userId) {
            workflowError("Unauthorized: Not assigned as this request's Department Head");
        }

        $stmt = $db->prepare("
            UPDATE leave_requests
            SET workflow_status = 'pending_personnel',
                department_head_user_id = COALESCE(department_head_user_id, ?),
                department_head_comments = ?,
                department_head_approved_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId, $comments, $leave_id]);

        if (!empty($row['email'])) {
            Mail::send(
                $row['email'],
                "Your leave request moved to personnel review",
                "Your {$row['leave_type_name']} leave request from {$row['start_date']} to {$row['end_date']} was approved by the Department Head and is now pending personnel review."
            );
        }

        flash_redirect('../views/leave_requests.php', 'success', 'Leave approved by Department Head and forwarded to Personnel');
    }


    // Stage 2: Personnel final approval
    if ($workflow === 'pending_personnel') {
        if (!in_array($role, ['personnel','hr','admin'], true)) {
            workflowError("Unauthorized access");
        }

        $approvalOptions = [];
        if (isLateSickFilingForPersonnel($row)) {
            $payStatus = trim((string)($_POST['approval_pay_status'] ?? 'without_pay'));
            $days = (float)($row['total_days'] ?? 0);
            if ($payStatus === 'with_pay') {
                $approvalOptions['approved_with_pay'] = $days;
                $approvalOptions['approved_without_pay'] = 0.0;
                $approvalOptions['deduct_days'] = $days;
            } else {
                $approvalOptions['approved_with_pay'] = 0.0;
                $approvalOptions['approved_without_pay'] = $days;
                $approvalOptions['deduct_days'] = 0.0;
            }
        }

        $policy = $leaveModel->getLeavePolicy($row['leave_type_id'] ?? $row['leave_type_name'] ?? $row['leave_type'] ?? '');
        if (!empty($policy) && empty($policy['deduct_balance'])) {
            $approvalOptions['approved_with_pay'] = (float)($row['total_days'] ?? 0);
            $approvalOptions['approved_without_pay'] = 0.0;
            $approvalOptions['deduct_days'] = 0.0;
        }

        $ok = $leaveModel->respondToLeave($leave_id, $userId, 'approve', $comments, $approvalOptions);
        if (!$ok) {
            flash_redirect('../views/leave_requests.php', 'error', 'Unable to finalize approval');
        }

        $stmt = $db->prepare("
            UPDATE leave_requests
            SET workflow_status = 'finalized',
                personnel_user_id = ?,
                personnel_comments = ?,
                personnel_checked_at = NOW(),
                finalized_at = NOW(),
                print_status = 'pending_print'
            WHERE id = ?
        ");
        $stmt->execute([$userId, $comments, $leave_id]);

        if (!empty($row['email'])) {
            Mail::send(
                $row['email'],
                "Your leave request approved",
                "Your {$row['leave_type_name']} leave from {$row['start_date']} to {$row['end_date']} has been fully approved."
            );
        }

flash_redirect('../views/leave_requests.php', 'success', 'Leave fully approved by Personnel');
    }

flash_redirect('../views/leave_requests.php', 'warning', 'This request is not in an approvable workflow stage');
}

if ($action === 'reject') {
    if (!in_array($role, ['manager','department_head','personnel','hr','admin'], true)) {
        workflowError("Unauthorized access");
    }
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        workflowError("CSRF validation failed.");
    }

    $leave_id = (int)($_POST['leave_id'] ?? 0);
    $comments = trim($_POST['comments'] ?? '');
    $row = fetchLeaveForWorkflow($db, $leave_id);
    if (!$row) {
        flash_redirect('../views/leave_requests.php', 'error', 'Leave request not found');
    }

    $workflow = trim((string)($row['workflow_status'] ?? ''));

    if ($workflow === '' || $workflow === 'pending_department_head') {
        if (!in_array($role, ['manager','department_head','admin'], true)) {
            workflowError("Unauthorized access");
        }

        if ($role === 'department_head') {
            // Check if this user is the department head for the employee's department
            $stmt = $db->prepare("SELECT e.department_id FROM employees e WHERE e.id = ?");
            $stmt->execute([$row['employee_id']]);
            $deptId = $stmt->fetchColumn();
            if (!$deptId) {
                workflowError("Employee has no department assigned");
            }
            $stmt2 = $db->prepare("SELECT 1 FROM department_head_assignments WHERE department_id = ? AND employee_id = (SELECT id FROM employees WHERE user_id = ?) AND is_active = 1");
            $stmt2->execute([$deptId, $userId]);
            if (!$stmt2->fetch()) {
                workflowError("Unauthorized: You are not the department head for this employee's department");
            }
        } elseif ($role !== 'admin' && !empty($row['department_head_user_id']) && (int)$row['department_head_user_id'] !== $userId) {
            workflowError("Unauthorized: Not assigned as this request's Department Head");
        }

        $stmt = $db->prepare("
            UPDATE leave_requests
            SET status = 'rejected',
                workflow_status = 'rejected_department_head',
                approved_by = ?,
                manager_comments = ?,
                department_head_user_id = COALESCE(department_head_user_id, ?),
                department_head_comments = ?,
                department_head_approved_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId, $comments, $userId, $comments, $leave_id]);

        if (!empty($row['email'])) {
            Mail::send(
                $row['email'],
                "Your leave request was rejected",
                "Your {$row['leave_type_name']} leave from {$row['start_date']} to {$row['end_date']} was rejected by the Department Head. Reason: {$comments}"
            );
        }

flash_redirect('../views/leave_requests.php', 'warning', 'Leave rejected by Department Head');
    }

    if ($workflow === 'pending_personnel') {
        if (!in_array($role, ['personnel','hr','admin'], true)) {
            workflowError("Unauthorized access");
        }

        $stmt = $db->prepare("
            UPDATE leave_requests
            SET status = 'rejected',
                workflow_status = 'returned_by_personnel',
                approved_by = ?,
                manager_comments = ?,
                personnel_user_id = ?,
                personnel_comments = ?,
                personnel_checked_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId, $comments, $userId, $comments, $leave_id]);

        if (!empty($row['email'])) {
            Mail::send(
                $row['email'],
                "Your leave request was returned by personnel",
                "Your {$row['leave_type_name']} leave from {$row['start_date']} to {$row['end_date']} was not finalized by personnel. Reason: {$comments}"
            );
        }

flash_redirect('../views/leave_requests.php', 'warning', 'Leave returned by Personnel');
    }

flash_redirect('../views/leave_requests.php', 'warning', 'This request is not in a rejectable workflow stage');
}

if ($action === 'cancel') {
    if (!in_array($role, ['employee','manager','department_head','admin'], true)) {
        workflowError("Unauthorized access");
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        workflowError("CSRF validation failed.");
    }

    $leave_id = (int)($_POST['leave_id'] ?? 0);
    $employee_id = (int)($_SESSION['emp_id'] ?? 0);

    if (!$employee_id) {
        flash_redirect('../views/apply_leave.php', 'error', 'Employee record not found');
    }

    $stmt = $db->prepare("
        SELECT employee_id, status, workflow_status
        FROM leave_requests
        WHERE id = ?
    ");
    $stmt->execute([$leave_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['employee_id'] !== $employee_id) {
        die("Unauthorized: Cannot cancel this request");
    }

    if (strtolower((string)$row['status']) !== 'pending') {
        die("Only pending requests can be cancelled");
    }

    $stmt = $db->prepare("DELETE FROM leave_requests WHERE id = ?");
    $stmt->execute([$leave_id]);

flash_redirect('../views/dashboard.php', 'success', 'Leave request cancelled');
}

if ($action === 'apply') {
    if (!in_array($role, ['employee','manager','department_head','admin'], true)) {
        workflowError("Unauthorized access");
    }

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        workflowError("CSRF validation failed.");
    }

    $employee_id = $_SESSION['emp_id'] ?? null;
    if (!$employee_id) {
        flash_redirect('../views/apply_leave.php', 'error', 'Employee record not found');
    }

    $typeId = $_POST['leave_type_id'] ?? null;
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $commutation = trim($_POST['commutation'] ?? '');

    // NEW FIELDS
    $filingDate = trim($_POST['filing_date'] ?? date('Y-m-d'));
    $leaveSubtype = trim($_POST['leave_subtype'] ?? '');

    $details = $_POST['details'] ?? [];
    if (!is_array($details)) {
        $details = [];
    }
    $details['force_balance_only'] = !empty($details['force_balance_only']) ? 1 : 0;

    $supportingDocuments = $_POST['supporting_documents'] ?? [];
    if (!is_array($supportingDocuments)) {
        $supportingDocuments = [];
    }

    $medicalCertificateAttached = !empty($_POST['medical_certificate_attached']) ? 1 : 0;
    $affidavitAttached = !empty($_POST['affidavit_attached']) ? 1 : 0;
    $emergencyCase = !empty($_POST['emergency_case']) ? 1 : 0;

    $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
    $supportingDocumentsJson = !empty($supportingDocuments) ? json_encode(array_values($supportingDocuments), JSON_UNESCAPED_UNICODE) : null;

    $v = new Validator();
    $v->required('leave_type_id', $typeId)
      ->required('filing_date', $filingDate)
      ->date('filing_date', $filingDate)
      ->required('start_date', $start)
      ->date('start_date', $start)
      ->required('end_date', $end)
      ->date('end_date', $end)
      ->required('reason', $reason);

    if ($v->fails()) {
        $err = implode(' ', array_map('implode', $v->getErrors()));
flash_redirect('../views/apply_leave.php', 'error', $err);
    }

    $typeLookup = new LeaveType($db);
    $typeInfo = $typeLookup->get($typeId);
    $policy = $leaveModel->getLeavePolicy($typeInfo ?: $typeId);
    $days = $leaveModel->calculateDays($start, $end);
    $uploadedFileCount = countUploadedFiles($_FILES['attachments'] ?? []);
    $selectedDocCount = count(array_filter($supportingDocuments, static function ($value) {
        return trim((string)$value) !== '';
    }));

    if (!empty($policy['required_doc_count'])) {
        if ($selectedDocCount < (int)$policy['required_doc_count']) {
flash_redirect('../views/apply_leave.php', 'error', 'Please select the required supporting document type(s) for this leave.');
        }
        if ($uploadedFileCount < (int)$policy['required_doc_count']) {
flash_redirect('../views/apply_leave.php', 'error', 'Please upload the required supporting file(s) for this leave type.');
        }
    }

    $typeNameForValidation = strtolower(trim((string)($typeInfo['name'] ?? '')));
    if (in_array($typeNameForValidation, ['sick leave', 'sick'], true) && $days > 5) {
        if (!$medicalCertificateAttached) {
flash_redirect('../views/apply_leave.php', 'error', 'Medical certificate is required for sick leave beyond five (5) continuous working days.');
        }
        if ($uploadedFileCount < 1) {
flash_redirect('../views/apply_leave.php', 'error', 'Please upload the medical certificate file for this sick leave request.');
        }
    }
    $extraData = [
        'filing_date' => $filingDate,
        'leave_subtype' => $leaveSubtype !== '' ? $leaveSubtype : null,
        'details_json' => $detailsJson,
        'supporting_documents_json' => $supportingDocumentsJson,
        'medical_certificate_attached' => $medicalCertificateAttached,
        'affidavit_attached' => $affidavitAttached,
        'emergency_case' => $emergencyCase,
        'force_balance_only' => !empty($details['force_balance_only']) ? 1 : 0,
    ];

    $result = $leaveModel->apply(
        (int)$employee_id,
        $typeId,
        $start,
        $end,
        $reason,
        $userId,
        $role,
        $commutation,
        $extraData
    );

    $attachmentNotice = '';
    if (strpos($result, 'successfully') !== false) {
        $newLeaveId = $leaveModel->getLastInsertedId();
        if ($newLeaveId > 0 && !empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'] ?? null)) {
            $uploadResult = uploadLeaveAttachments($_FILES['attachments'], $newLeaveId, $userId, $leaveAttachmentModel);
            if (($uploadResult['saved'] ?? 0) > 0) {
                $attachmentNotice .= ' ' . (int)$uploadResult['saved'] . ' attachment(s) uploaded.';
            }
            if (!empty($uploadResult['errors'])) {
                $attachmentNotice .= ' Some attachments were skipped: ' . implode(' ', array_slice($uploadResult['errors'], 0, 3));
            }
        }

        $dbType = new LeaveType($db);
        $typeInfo = $dbType->get($typeId);

        $subject = "New leave request from employee {$employee_id}";
        $body = "Employee {$employee_id} has applied for {$typeInfo['name']} leave from {$start} to {$end}.";
        Mail::send('hr@example.com', $subject, $body);

        if ($typeInfo && !empty($typeInfo['auto_approve'])) {
            $userEmail = $_SESSION['user_email'] ?? null;
            if ($userEmail) {
                Mail::send($userEmail, "Your leave has been approved", "Your {$typeInfo['name']} leave from {$start} to {$end} was auto-approved.");
            }
        }
    }

    if (strpos($result, 'successfully') !== false) {
        flash_redirect('../views/dashboard.php', 'success', $result . $attachmentNotice);
    } else {
        flash_redirect('../views/apply_leave.php', 'error', $result);
    }
}