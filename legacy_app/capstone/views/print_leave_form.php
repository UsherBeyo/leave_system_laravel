<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../helpers/Auth.php';
require_once '../helpers/Flash.php';
Auth::requireLogin('login.php');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['personnel', 'hr', 'admin'], true)) {
    flash_redirect('leave_requests.php?tab=approved', 'error', 'Access Denied');
}

$db = (new Database())->connect();
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    flash_redirect('leave_requests.php?tab=approved', 'error', 'Invalid request ID');
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function arr(array $row, string $key, $default = null)
{
    return array_key_exists($key, $row) ? $row[$key] : $default;
}

function safeFloat($value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

function normalizeLeaveTypeKey(string $name): string
{
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

function fmtDisplayDate(?string $date): string
{
    if (!$date) return '';
    $date = trim((string)$date);
    if ($date === '') return '';

    $ts = strtotime($date);
    if ($ts === false) return $date;

    return date('F j, Y', $ts);
}

function checkbox(bool $checked): string
{
    return $checked ? '☑' : '☐';
}

function firstExistingPath(array $paths): ?string
{
    foreach ($paths as $p) {
        $abs = realpath(__DIR__ . '/' . $p);
        if ($abs && file_exists($abs)) {
            return $p;
        }
    }
    return null;
}

try {
    $stmt = $db->prepare("
        SELECT 
            lr.*,
            e.*,
            u.email,
            COALESCE(lt.name, lr.leave_type) AS leave_type_name,
            lt.law_title,
            lt.law_text,

            lf.office_department,
            lf.employee_last_name,
            lf.employee_first_name,
            lf.employee_middle_name,
            lf.date_of_filing,
            lf.position_title,
            lf.salary AS form_salary,
            lf.details_of_leave_json,
            lf.commutation_requested,
            lf.certification_as_of,
            lf.cert_vacation_total_earned,
            lf.cert_vacation_less_this_application,
            lf.cert_vacation_balance,
            lf.cert_sick_total_earned,
            lf.cert_sick_less_this_application,
            lf.cert_sick_balance,
            lf.recommendation_status,
            lf.recommendation_reason,
            lf.approved_for_days_with_pay,
            lf.approved_for_days_without_pay,
            lf.approved_for_others,
            lf.personnel_signatory_name_a,
            lf.personnel_signatory_position_a,
            lf.personnel_signatory_name_c,
            lf.personnel_signatory_position_c,

            udh.email AS department_head_email,
            edh.first_name AS department_head_first_name,
            edh.middle_name AS department_head_middle_name,
            edh.last_name AS department_head_last_name

        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
        LEFT JOIN leave_request_forms lf ON lf.leave_request_id = lr.id

        LEFT JOIN users udh ON udh.id = lr.department_head_user_id
        LEFT JOIN employees edh ON edh.user_id = udh.id

        WHERE lr.id = ?
          AND (lr.workflow_status = 'finalized' OR lr.status = 'approved')
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $t) {
    $stmt = $db->prepare("
        SELECT 
            lr.*,
            e.*,
            u.email,
            COALESCE(lt.name, lr.leave_type) AS leave_type_name,
            lt.law_title,
            lt.law_text,

            udh.email AS department_head_email,
            edh.first_name AS department_head_first_name,
            edh.middle_name AS department_head_middle_name,
            edh.last_name AS department_head_last_name

        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN users u ON e.user_id = u.id
        LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id

        LEFT JOIN users udh ON udh.id = lr.department_head_user_id
        LEFT JOIN employees edh ON edh.user_id = udh.id

        WHERE lr.id = ?
          AND (lr.workflow_status = 'finalized' OR lr.status = 'approved')
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$request) {
    flash_redirect('leave_requests.php?tab=approved', 'error', 'Request not found or not finalized');
}

$selectedLeaveType = normalizeLeaveTypeKey((string)arr($request, 'leave_type_name', ''));
$deduct = safeFloat(arr($request, 'total_days', 0));

$leaveSubtype = strtolower(trim((string)arr($request, 'leave_subtype', '')));
$detailsJsonRaw = arr($request, 'details_json', '');
$detailsData = [];

if (!empty($detailsJsonRaw)) {
    $decoded = json_decode($detailsJsonRaw, true);
    if (is_array($decoded)) {
        $detailsData = $decoded;
    }
}

$supportingDocumentsRaw = arr($request, 'supporting_documents_json', '');
$supportingDocuments = [];
if (!empty($supportingDocumentsRaw)) {
    $decodedDocs = json_decode($supportingDocumentsRaw, true);
    if (is_array($decodedDocs)) {
        $supportingDocuments = $decodedDocs;
    }
}

$medicalCertificateAttached = !empty(arr($request, 'medical_certificate_attached', 0));
$affidavitAttached = !empty(arr($request, 'affidavit_attached', 0));
$emergencyCase = !empty(arr($request, 'emergency_case', 0));

$uploadedAttachments = [];
try {
    $stmtAtt = $db->prepare("SELECT original_name, file_path, file_size FROM leave_attachments WHERE leave_request_id = ? ORDER BY created_at ASC, id ASC");
    $stmtAtt->execute([$id]);
    $uploadedAttachments = $stmtAtt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $t) {
    $uploadedAttachments = [];
}

$isVacationBucket = ($selectedLeaveType === 'vacation leave');
$isForceBucket = ($selectedLeaveType === 'mandatory/forced leave');
$isSickBucket = ($selectedLeaveType === 'sick leave');

$vacBalanceAfter = safeFloat(arr($request, 'snapshot_annual_balance', 0));
$sickBalanceAfter = safeFloat(arr($request, 'snapshot_sick_balance', 0));
$forceBalanceAfter = safeFloat(arr($request, 'snapshot_force_balance', 0));

$vacLess = (arr($request, 'cert_vacation_less_this_application') !== null && arr($request, 'cert_vacation_less_this_application') !== '')
    ? safeFloat(arr($request, 'cert_vacation_less_this_application'))
    : (($isVacationBucket || $isForceBucket) ? $deduct : 0.0);

$sickLess = (arr($request, 'cert_sick_less_this_application') !== null && arr($request, 'cert_sick_less_this_application') !== '')
    ? safeFloat(arr($request, 'cert_sick_less_this_application'))
    : ($isSickBucket ? $deduct : 0.0);

$vacTotalEarned = (arr($request, 'cert_vacation_total_earned') !== null && arr($request, 'cert_vacation_total_earned') !== '')
    ? safeFloat(arr($request, 'cert_vacation_total_earned'))
    : ($vacBalanceAfter + $vacLess);

$sickTotalEarned = (arr($request, 'cert_sick_total_earned') !== null && arr($request, 'cert_sick_total_earned') !== '')
    ? safeFloat(arr($request, 'cert_sick_total_earned'))
    : ($sickBalanceAfter + $sickLess);

$vacBalance = (arr($request, 'cert_vacation_balance') !== null && arr($request, 'cert_vacation_balance') !== '')
    ? safeFloat(arr($request, 'cert_vacation_balance'))
    : $vacBalanceAfter;

$sickBalance = (arr($request, 'cert_sick_balance') !== null && arr($request, 'cert_sick_balance') !== '')
    ? safeFloat(arr($request, 'cert_sick_balance'))
    : $sickBalanceAfter;

if ($isSickBucket) {
    $availableForPay = $sickTotalEarned;
} elseif ($isForceBucket) {
    $availableForPay = min($vacTotalEarned, $forceBalanceAfter + $deduct);
} else {
    $availableForPay = $vacTotalEarned;
}

$daysWithPay = (arr($request, 'approved_for_days_with_pay') !== null && arr($request, 'approved_for_days_with_pay') !== '')
    ? safeFloat(arr($request, 'approved_for_days_with_pay'))
    : min($deduct, $availableForPay);

$daysWithoutPay = (arr($request, 'approved_for_days_without_pay') !== null && arr($request, 'approved_for_days_without_pay') !== '')
    ? safeFloat(arr($request, 'approved_for_days_without_pay'))
    : max(0, $deduct - $daysWithPay);

$approvedOthers = trim((string)arr($request, 'approved_for_others', ''));

$department = trim((string)(arr($request, 'office_department') ?: arr($request, 'department', '')));
$lastName = trim((string)(arr($request, 'employee_last_name') ?: arr($request, 'last_name', '')));
$firstName = trim((string)(arr($request, 'employee_first_name') ?: arr($request, 'first_name', '')));
$middleName = trim((string)(arr($request, 'employee_middle_name') ?: arr($request, 'middle_name', '')));
$dateOfFiling = arr($request, 'date_of_filing') ?: arr($request, 'filing_date') ?: arr($request, 'created_at', date('Y-m-d'));
$formPosition = trim((string)arr($request, 'position_title', ''));
$employeePosition = trim((string)arr($request, 'position', ''));
$position = $formPosition !== '' ? $formPosition : $employeePosition;
$formSalaryRaw = arr($request, 'form_salary', null);
$employeeSalaryRaw = arr($request, 'salary', 0);

$formSalary = safeFloat($formSalaryRaw);
$employeeSalary = safeFloat($employeeSalaryRaw);

// Use form salary only when it is actually filled with a positive value.
// Otherwise fallback to employee salary.
$salary = $formSalary > 0 ? $formSalary : $employeeSalary;

$recommendationStatus = strtolower(trim((string)arr($request, 'recommendation_status', '')));
$recommendationReason = trim((string)(
    arr($request, 'recommendation_reason')
    ?: arr($request, 'personnel_comments')
    ?: arr($request, 'department_head_comments')
    ?: arr($request, 'manager_comments', '')
));

$isDisapproved = strtolower((string)arr($request, 'status', '')) === 'rejected' || $recommendationStatus === 'for_disapproval';

$commutationRequested = strtolower(trim((string)(
    arr($request, 'commutation_requested')
    ?: arr($request, 'commutation', '')
)));
$commNotRequested = in_array($commutationRequested, ['', 'not_requested', 'not requested', 'no'], true);
$commRequested = in_array($commutationRequested, ['requested', 'yes'], true);

$signatoryAName = trim((string)arr($request, 'personnel_signatory_name_a', ''));
$signatoryAPosition = trim((string)arr($request, 'personnel_signatory_position_a', ''));
$signatoryCName = trim((string)arr($request, 'personnel_signatory_name_c', ''));
$signatoryCPosition = trim((string)arr($request, 'personnel_signatory_position_c', ''));

$defaultSignatories = [];
try {
    $stmtDefaults = $db->query("SELECT key_name, name, position FROM system_signatories");
    foreach ($stmtDefaults->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $defaultSignatories[$s['key_name']] = $s;
    }
} catch (Throwable $t) {
    $defaultSignatories = [];
}

if ($signatoryAName === '') {
    $signatoryAName = trim((string)($defaultSignatories['certification']['name'] ?? 'ANN GERALYN T. PELIAS'));
}
if ($signatoryAPosition === '') {
    $signatoryAPosition = trim((string)($defaultSignatories['certification']['position'] ?? 'Chief Administrative Officer'));
}
if ($signatoryCName === '') {
    $signatoryCName = trim((string)($defaultSignatories['final_approver']['name'] ?? 'ATTY. ALBERTO T. ESCOBARTE'));
}
if ($signatoryCPosition === '') {
    $signatoryCPosition = trim((string)($defaultSignatories['final_approver']['position'] ?? 'Assistant Regional Director'));
}

$lawTitle = trim((string)arr($request, 'law_title', ''));
$lawText = trim((string)arr($request, 'law_text', ''));

$departmentHeadFullName = trim(
    (string)arr($request, 'department_head_first_name', '') . ' ' .
    (string)arr($request, 'department_head_middle_name', '') . ' ' .
    (string)arr($request, 'department_head_last_name', '')
);
if ($departmentHeadFullName === '') {
    $departmentHeadFullName = 'Chief of the Division/Section or Unit Head';
}

$leaveTypeChecks = [
    'vacation leave' => false,
    'mandatory/forced leave' => false,
    'sick leave' => false,
    'maternity leave' => false,
    'paternity leave' => false,
    'special privilege leave' => false,
    'solo parent leave' => false,
    'study leave' => false,
    '10-day vawc leave' => false,
    'rehabilitation privilege' => false,
    'special leave benefits for women' => false,
    'special emergency (calamity) leave' => false,
    'adoption leave' => false,
    'others' => false,
];

switch ($selectedLeaveType) {
    case 'vacation':
    case 'vacational':
    case 'annual':
    case 'vacation leave':
        $leaveTypeChecks['vacation leave'] = true;
        break;
    case 'mandatory/forced leave':
        $leaveTypeChecks['mandatory/forced leave'] = true;
        break;
    case 'sick':
    case 'sick leave':
        $leaveTypeChecks['sick leave'] = true;
        break;
    case 'maternity':
    case 'maternity leave':
        $leaveTypeChecks['maternity leave'] = true;
        break;
    case 'paternity':
    case 'paternity leave':
        $leaveTypeChecks['paternity leave'] = true;
        break;
    case 'special privilege leave':
        $leaveTypeChecks['special privilege leave'] = true;
        break;
    case 'solo parent leave':
        $leaveTypeChecks['solo parent leave'] = true;
        break;
    case 'study leave':
        $leaveTypeChecks['study leave'] = true;
        break;
    case '10-day vawc leave':
        $leaveTypeChecks['10-day vawc leave'] = true;
        break;
    case 'rehabilitation privilege':
        $leaveTypeChecks['rehabilitation privilege'] = true;
        break;
    case 'special leave benefits for women':
        $leaveTypeChecks['special leave benefits for women'] = true;
        break;
    case 'special emergency (calamity) leave':
        $leaveTypeChecks['special emergency (calamity) leave'] = true;
        break;
    case 'adoption leave':
        $leaveTypeChecks['adoption leave'] = true;
        break;
    default:
        $leaveTypeChecks['others'] = true;
        break;
}

$detailChecks = [
    'within_ph' => false,
    'abroad' => false,
    'in_hospital' => false,
    'out_patient' => false,
    'women_special' => false,
    'masters' => false,
    'bar_review' => false,
    'monetization' => false,
    'terminal' => false,
];

if ($leaveSubtype === 'within_ph') {
    $detailChecks['within_ph'] = true;
}
if ($leaveSubtype === 'abroad') {
    $detailChecks['abroad'] = true;
}
if ($leaveSubtype === 'in_hospital') {
    $detailChecks['in_hospital'] = true;
}
if ($leaveSubtype === 'out_patient') {
    $detailChecks['out_patient'] = true;
}
if ($leaveSubtype === 'masters') {
    $detailChecks['masters'] = true;
}
if ($leaveSubtype === 'bar_review') {
    $detailChecks['bar_review'] = true;
}

if ($leaveTypeChecks['special leave benefits for women']) {
    $detailChecks['women_special'] = true;
}
if ($selectedLeaveType === 'monetization of leave credits') {
    $detailChecks['monetization'] = true;
}
if ($selectedLeaveType === 'terminal leave') {
    $detailChecks['terminal'] = true;
}

$otherLeaveLabel = (!$leaveTypeChecks['others']) ? '' : (arr($request, 'leave_type_name', ''));

$sickIllnessText = '';
if ($isSickBucket && ($detailChecks['in_hospital'] || $detailChecks['out_patient'])) {
    $sickIllnessText = trim((string)($detailsData['illness'] ?? ''));
}

$womenIllnessText = '';
if ($leaveTypeChecks['special leave benefits for women']) {
    $womenIllnessText = trim((string)(
        $detailsData['surgery_details']
        ?? $detailsData['illness']
        ?? ''
    ));
}

$studyOtherPurposeText = '';
if ($leaveTypeChecks['study leave']) {
    $studyOtherPurposeText = trim((string)(
        $detailsData['other_purpose']
        ?? arr($request, 'reason', '')
    ));
}

$showStudyOthers = $leaveTypeChecks['study leave']
    && !$detailChecks['masters']
    && !$detailChecks['bar_review']
    && $studyOtherPurposeText !== '';

$monetizationReasonText = '';
if ($detailChecks['monetization']) {
    $monetizationReasonText = trim((string)($detailsData['monetization_reason'] ?? ''));
}

$terminalReasonText = '';
if ($detailChecks['terminal']) {
    $terminalReasonText = trim((string)($detailsData['terminal_reason'] ?? ''));
}

// optional logos
$depedLogo = firstExistingPath([
    'pictures/deped.jpg',
    '/../assets/img/deped.png',
    '/../assets/img/deped.jpg'
]);

$regionLogo = firstExistingPath([
    'pictures/region4.jpg',
    '/../assets/img/region4a.png',
    '/../assets/img/region.png'
]);

$certificationAsOf = trim((string)arr($request, 'certification_as_of', ''));
if ($certificationAsOf === '') {
    $certificationAsOf = trim((string)(
        arr($request, 'finalized_at')
        ?: arr($request, 'personnel_checked_at')
        ?: arr($request, 'department_head_approved_at')
        ?: arr($request, 'created_at')
        ?: date('Y-m-d')
    ));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <meta charset="UTF-8">
    <title>Application for Leave - <?= e(trim($firstName . ' ' . $lastName)); ?></title>
    <link rel="stylesheet" href="../assets/css/print_leave_form.css">
</head>
<body>
<div class="page">

    <table class="top-meta">
        <tr>
            <td class="meta-left">
                <div><strong><em>Civil Service Form No. 6</em></strong></div>
                <div><strong><em>Revised 2020</em></strong></div>
            </td>
            <td class="meta-right"><strong>ANNEX A</strong></td>
        </tr>
    </table>

    <div class="header-shell">
        <div class="header-logos">
            <div class="logo-wrap">
                <?php if ($depedLogo): ?>
                    <img src="<?= e($depedLogo); ?>" alt="DepEd Seal" class="seal-img">
                <?php else: ?>
                    <div class="seal"></div>
                <?php endif; ?>
            </div>
            <div class="logo-wrap">
                <?php if ($regionLogo): ?>
                    <img src="<?= e($regionLogo); ?>" alt="Region Seal" class="seal-img">
                <?php else: ?>
                    <div class="seal"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="header-center-block">
            <div class="gov-line">Republic of the Philippines</div>
            <div class="gov-line">Department of Education</div>
            <div class="gov-region">Region IV-A CALABARZON</div>
            <div class="gov-sub">Gate 2 Karangalan Village, Cainta, Rizal</div>
        </div>
    </div>

    <div class="main-title">APPLICATION FOR LEAVE</div>

    <table class="leave-form">
        <colgroup>
            <col style="width:12%">
            <col style="width:13%">
            <col style="width:15%">
            <col style="width:14%">
            <col style="width:12%">
            <col style="width:14%">
            <col style="width:10%">
            <col style="width:10%">
        </colgroup>

        <tr>
            <td colspan="3" class="cell-label head-cell">1.&nbsp; OFFICE/DEPARTMENT</td>
            <td colspan="5" class="cell-label head-cell">
                2.&nbsp; NAME:
                <span class="name-guide guide-last">(Last)</span>
                <span class="name-guide guide-first">(First)</span>
                <span class="name-guide guide-middle">(Middle)</span>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="cell-value value-row office-value">DEPED REGION IV-A CALABARZON</td>
            <td colspan="2" class="cell-value value-row"><?= e($lastName); ?></td>
            <td colspan="2" class="cell-value value-row"><?= e($firstName); ?></td>
            <td colspan="1" class="cell-value value-row"><?= e($middleName); ?></td>
        </tr>

        <tr>
            <td colspan="2" class="cell-label head-cell">3.&nbsp; DATE OF FILING</td>
            <td colspan="2" class="field-line centered strong"><?= e(fmtDisplayDate($dateOfFiling)); ?></td>
            <td colspan="1" class="cell-label head-cell">4.&nbsp; POSITION</td>
            <td colspan="2" class="field-line centered"><?= e($position); ?></td>
            <td colspan="1" class="salary-inline-cell">
                <span class="salary-inline-label">5.&nbsp; SALARY</span>
                <span class="salary-inline-line"><?= $salary > 0 ? number_format($salary, 2) : ''; ?></span>
            </td>
        </tr>

        <tr>
            <td colspan="8" class="section-title section-row">6.&nbsp; DETAILS OF APPLICATION</td>
        </tr>

        <tr>
            <td colspan="4" class="subsection-header head-cell">6.A TYPE OF LEAVE TO BE AVAILED OF</td>
            <td colspan="4" class="subsection-header head-cell">6.B DETAILS OF LEAVE</td>
        </tr>

        <tr>
            <td colspan="4" class="top-align list-cell">
                <table class="inner-list">
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['vacation leave']); ?></td><td><strong>Vacation Leave</strong> <span class="small-note">(Sec. 51, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['mandatory/forced leave']); ?></td><td><strong>Mandatory/Forced Leave</strong> <span class="small-note">(Sec. 25, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['sick leave']); ?></td><td><strong>Sick Leave</strong> <span class="small-note">(Sec. 43, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['maternity leave']); ?></td><td><strong>Maternity Leave</strong> <span class="small-note">(R.A. No. 11210 / IRR issued by CSC, DOLE and SSS)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['paternity leave']); ?></td><td><strong>Paternity Leave</strong> <span class="small-note">(R.A. No. 8187 / CSC MC No. 71, s. 1998, as amended)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['special privilege leave']); ?></td><td><strong>Special Privilege Leave</strong> <span class="small-note">(Sec. 21, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['solo parent leave']); ?></td><td><strong>Solo Parent Leave</strong> <span class="small-note">(RA No. 8972 / CSC MC No. 8, s. 2004)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['study leave']); ?></td><td><strong>Study Leave</strong> <span class="small-note">(Sec. 68, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['10-day vawc leave']); ?></td><td><strong>10-Day VAWC Leave</strong> <span class="small-note">(RA No. 9262 / CSC MC No. 15, s. 2005)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['rehabilitation privilege']); ?></td><td><strong>Rehabilitation Privilege</strong> <span class="small-note">(Sec. 55, Rule XVI, Omnibus Rules Implementing E.O. No. 292)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['special leave benefits for women']); ?></td><td><strong>Special Leave Benefits for Women</strong> <span class="small-note">(RA No. 9710 / CSC MC No. 25, s. 2010)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['special emergency (calamity) leave']); ?></td><td><strong>Special Emergency (Calamity) Leave</strong> <span class="small-note">(CSC MC No. 2, s. 2012, as amended)</span></td></tr>
                    <tr><td class="box"><?= checkbox($leaveTypeChecks['adoption leave']); ?></td><td><strong>Adoption Leave</strong> <span class="small-note">(R.A. No. 8552)</span></td></tr>
                    <tr>
                        <td class="box"><?= checkbox($leaveTypeChecks['others']); ?></td>
                        <td><em>Others:</em> <span class="line-fill"><?= e($otherLeaveLabel); ?></span></td>
                    </tr>
                </table>
            </td>

            <td colspan="4" class="top-align list-cell">
                <table class="inner-list details-list">
                    <tr><td colspan="2" class="italic-head">In case of Vacation/Special Privilege Leave:</td></tr>
                    <tr>
                        <td class="box"><?= checkbox($detailChecks['within_ph']); ?></td>
                        <td>Within the Philippines <span class="inline-line"><?= e($detailChecks['within_ph'] ? ($detailsData['location'] ?? '') : ''); ?></span></td>
                    </tr>
                    <tr>
                        <td class="box"><?= checkbox($detailChecks['abroad']); ?></td>
                        <td>Abroad (Specify) <span class="inline-line"><?= e($detailChecks['abroad'] ? ($detailsData['location'] ?? '') : ''); ?></span></td>
                    </tr>

                    <tr><td colspan="2" class="italic-head">In case of Sick Leave:</td></tr>
                        <tr>
                            <td class="box"><?= checkbox($detailChecks['in_hospital']); ?></td>
                            <td>
                                In Hospital (Specify Illness)
                                <span class="inline-line"><?= e($detailChecks['in_hospital'] ? $sickIllnessText : ''); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="box"><?= checkbox($detailChecks['out_patient']); ?></td>
                            <td>
                                Out Patient (Specify Illness)
                                <span class="inline-line"><?= e($detailChecks['out_patient'] ? $sickIllnessText : ''); ?></span>
                            </td>
                        </tr>

                        <tr><td colspan="2" class="italic-head">In case of Special Leave Benefits for Women:</td></tr>
                        <tr>
                            <td class="box"></td>
                            <td>
                                (Specify Illness)
                                <span class="inline-line"><?= e($detailChecks['women_special'] ? $womenIllnessText : ''); ?></span>
                            </td>
                        </tr>

                        <tr><td colspan="2" class="italic-head">In case of Study Leave:</td></tr>
                            <tr>
                                <td class="box"><?= checkbox($detailChecks['masters']); ?></td>
                                <td>Completion of Master's Degree</td>
                            </tr>
                            <tr>
                                <td class="box"><?= checkbox($detailChecks['bar_review']); ?></td>
                                <td>BAR/Board Examination Review</td>
                            </tr>
                            <tr>
                                <td class="box"><?= checkbox($detailChecks['monetization']); ?></td>
                                <td>Monetization of Leave Credits</td>
                            </tr>
                            <tr>
                                <td class="box"><?= checkbox($detailChecks['terminal']); ?></td>
                                <td>Terminal Leave</td>
                            </tr>
                            <tr>
                                <td class="box"></td>
                                <td>
                                    <em>Others:</em>
                                    <span class="inline-line"><?= e($showStudyOthers ? $studyOtherPurposeText : ''); ?></span>
                                </td>
                            </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="subsection-header head-cell no-bottom-cell">6.C NUMBER OF WORKING DAYS APPLIED FOR</td>
            <td colspan="4" class="subsection-header head-cell no-bottom-cell">6.D COMMUTATION</td>
        </tr>

        <tr>
            <td colspan="4" class="days-block top-align no-top-row no-bottom-row">
                <div class="days-line-wrap">
                    <div class="line-wide line-top-value">
                        <span class="days-line-value"><?= number_format($deduct, 3); ?></span>
                    </div>
                </div>
                <div class="inclusive-label">INCLUSIVE DATES</div>
                <div class="line-wide centered-date">
                    <?= e(fmtDisplayDate(arr($request, 'start_date', ''))); ?>
                    <?= (!empty(arr($request, 'start_date')) || !empty(arr($request, 'end_date'))) ? ' to ' : '' ?>
                    <?= e(fmtDisplayDate(arr($request, 'end_date', ''))); ?>
                </div>
            </td>
            <td colspan="4" class="commutation-block top-align no-top-row no-bottom-row">
                <div class="comm-row"><?= checkbox($commNotRequested); ?> <span>Not Requested</span></div>
                <div class="comm-row"><?= checkbox($commRequested); ?> <span>Requested</span></div>
                <div class="applicant-signature">(Signature of Applicant)</div>
            </td>
        </tr>

        <tr>
            <td colspan="8" class="section-title section-row">7.&nbsp; DETAILS OF ACTION ON APPLICATION</td>
        </tr>

        <tr>
            <td colspan="4" class="subsection-header head-cell">7.A CERTIFICATION OF LEAVE CREDITS</td>
            <td colspan="4" class="subsection-header head-cell">7.B RECOMMENDATION</td>
        </tr>

        <tr>
            <td colspan="4" class="top-align cert-cell">
                <div class="as-of-wrap">As of <span class="as-of-line"><?= e(fmtDisplayDate($certificationAsOf)); ?></span></div>

                <table class="credits-table">
                    <tr>
                        <th></th>
                        <th>Vacation Leave</th>
                        <th>Sick Leave</th>
                    </tr>
                    <tr>
                        <td><em>Total Earned</em></td>
                        <td><?= number_format($vacTotalEarned, 3); ?></td>
                        <td><?= number_format($sickTotalEarned, 3); ?></td>
                    </tr>
                    <tr>
                        <td><em>Less this application</em></td>
                        <td><?= number_format($vacLess, 3); ?></td>
                        <td><?= number_format($sickLess, 3); ?></td>
                    </tr>
                    <tr>
                        <td><em>Balance</em></td>
                        <td><?= number_format($vacBalance, 3); ?></td>
                        <td><?= number_format($sickBalance, 3); ?></td>
                    </tr>
                </table>

                <div class="sig-area cert-sign">
                    <div class="sig-name"><?= e($signatoryAName); ?></div>
                    <div class="sig-line"></div>
                    <div class="sig-pos"><?= e($signatoryAPosition); ?></div>
                </div>
            </td>

            <td colspan="4" class="top-align recommendation-cell">
                <div class="rec-row"><?= checkbox(!$isDisapproved); ?> <span>For approval</span></div>
                <div class="rec-row"><?= checkbox($isDisapproved); ?> <span>For disapproval due to</span> <span class="reason-line short-reason"><?= e($isDisapproved ? $recommendationReason : ''); ?></span></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>

                <div class="sig-area lower">
                    <div class="sig-name dept-head-sign"><?= e($departmentHeadFullName); ?></div>
                    <div class="sig-line"></div>
                    <div class="sig-pos">Chief of the Division/Section or Unit Head</div>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="4" class="top-align approval-cell no-right-border">
                <div class="approve-title">7.C APPROVED FOR:</div>
                <div class="approve-row"><span class="short-line"><?= number_format($daysWithPay, 3); ?></span> day with pay</div>
                <div class="approve-row"><span class="short-line"><?= number_format($daysWithoutPay, 3); ?></span> days without pay</div>
                <div class="approve-row"><span class="short-line"><?= e($approvedOthers); ?></span> others (Specify)</div>
            </td>

            <td colspan="4" class="top-align disapprove-cell no-left-border">
                <div class="approve-title">7.&nbsp;D&nbsp; DISAPPROVED DUE TO:</div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
                <div class="reason-line big"></div>
            </td>
        </tr>

        <tr>
            <td colspan="8" class="final-signatory-row">
                <div class="sig-area centered-final">
                    <div class="sig-name final"><?= e($signatoryCName); ?></div>
                    <div class="sig-line"></div>
                    <div class="sig-pos"><?= e($signatoryCPosition); ?></div>
                </div>
            </td>
        </tr>
    </table>

    <?php if ($lawTitle !== '' || $lawText !== ''): ?>
        <div class="law-note">
            <strong>Related Law:</strong> <?= e($lawTitle); ?>
            <?php if ($lawText !== ''): ?>
                <div class="law-text"><?= nl2br(e($lawText)); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
window.print();
</script>
</body>
</html>
