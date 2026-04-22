<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');

if (!in_array($_SESSION['role'], ['employee','manager','department_head','admin'], true)) {
    die("Access denied");
}

$db = (new Database())->connect();
$emp_id = (int)($_SESSION['emp_id'] ?? 0);

if ($emp_id <= 0) {
    die("Employee record not found.");
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function safeFloat($v): float {
    return is_numeric($v) ? (float)$v : 0.0;
}

function leaveRulePreset(string $typeName): array {
    $key = strtolower(trim($typeName));
    $key = preg_replace('/\s+/', ' ', $key);
    $key = str_replace([' / ', ' /', '/ '], '/', $key);

    $base = [
        'bucket' => 'annual',
        'bucket_label' => 'Vacational Balance',
        'secondary_bucket' => null,
        'secondary_bucket_label' => null,
        'show_rules' => true,
        'min_days_notice' => 0,
        'max_days' => null,
        'max_days_per_year' => null,
        'deduct_balance' => true,
        'required_doc_count' => 0,
        'allow_emergency' => false,
        'show_force_balance_only' => false,
        'subtype_label' => '',
        'subtypes' => [],
        'show_location_text' => false,
        'location_label' => 'Specify',
        'show_illness_text' => false,
        'show_other_purpose' => false,
        'show_expected_delivery' => false,
        'show_calamity_location' => false,
        'show_surgery_details' => false,
        'show_monetization_reason' => false,
        'show_terminal_reason' => false,
        'documents' => [],
        'rules_text' => [],
        'used_by_year' => [],
    ];

    switch ($key) {
        case 'vacation leave':
        case 'vacation':
        case 'vacational':
        case 'annual':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'min_days_notice' => 5,
                'subtype_label' => 'Vacation Details',
                'subtypes' => [
                    'within_ph' => 'Within the Philippines',
                    'abroad' => 'Abroad',
                ],
                'show_location_text' => true,
                'location_label' => 'Location / Destination',
                'rules_text' => [
                    'Vacation leave must be filed five (5) days before the start date of leave.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'mandatory/forced leave':
        case 'mandatory / forced leave':
        case 'mandatory/force leave':
        case 'mandatory / force leave':
        case 'mandatory':
        case 'forced':
        case 'forced leave':
        case 'force':
        case 'force leave':
            return array_merge($base, [
                'bucket' => 'force',
                'bucket_label' => 'Force Balance',
                'secondary_bucket' => 'annual',
                'secondary_bucket_label' => 'Vacational Balance',
                'min_days_notice' => 5,
                'show_force_balance_only' => true,
                'rules_text' => [
                    'Force leave must be filed five (5) days before the start date of leave.',
                    'Standard force leave deduction affects both Force Balance and Vacational Balance.',
                    'If this leave is for official seminar or work-aligned attendance, tick the checkbox below so it deducts only from Force Balance and not from Vacational Balance.',
                ],
            ]);

        case 'sick leave':
        case 'sick':
            return array_merge($base, [
                'bucket' => 'sick',
                'bucket_label' => 'Sick Balance',
                'allow_emergency' => true,
                'subtype_label' => 'Sick Leave Details',
                'subtypes' => [
                    'in_hospital' => 'In Hospital',
                    'out_patient' => 'Out Patient',
                ],
                'show_illness_text' => true,
                'documents' => [
                    'medical_certificate' => 'Medical Certificate (required when the sick leave covers more than five (5) continuous working days)',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'If the sick leave covers more than five (5) continuous working days, a medical certificate is required.',
                    'Sick leave should be filed within one (1) calendar month from the date of leave.',
                    'If filed beyond one (1) calendar month, personnel will decide whether it will be recorded as with pay or without pay.',
                    'When recorded as without pay, Sick Balance will not be deducted and the leave will be treated as without pay on the printable form.',
                ],
            ]);

        case 'maternity leave':
        case 'maternity':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 105,
                'deduct_balance' => false,
                'show_expected_delivery' => true,
                'documents' => [
                    'proof_of_pregnancy' => 'Proof of pregnancy',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Proof of pregnancy attachment is required.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'paternity leave':
        case 'paternity':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 7,
                'max_days_per_year' => 7,
                'deduct_balance' => false,
                'documents' => [
                    'child_delivery_proof' => 'Proof of child’s delivery (Birth Certificate or Medical Certificate)',
                    'marriage_contract' => 'Marriage Contract',
                ],
                'required_doc_count' => 2,
                'rules_text' => [
                    'Paternity leave is limited to seven (7) days per year.',
                    'If the total requested or already filed days exceed seven (7), the request should be filed as Vacation Leave instead.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'special privilege leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 3,
                'max_days_per_year' => 3,
                'min_days_notice' => 5,
                'deduct_balance' => false,
                'allow_emergency' => false,
                'subtype_label' => 'Special Privilege Leave Details',
                'subtypes' => [
                    'within_ph' => 'Within the Philippines',
                    'abroad' => 'Abroad',
                ],
                'show_location_text' => true,
                'location_label' => 'Location / Destination',
                'documents' => [
                    'special_privilege_supporting_document' => 'Special Privilege Leave supporting document',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Special Privilege Leave must be filed five (5) days before the start date.',
                    'Only up to three (3) days may be consumed in one year.',
                    'Supporting document attachment is required.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'solo parent leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 7,
                'max_days_per_year' => 7,
                'min_days_notice' => 5,
                'deduct_balance' => false,
                'documents' => [
                    'solo_parent_id' => 'Solo Parent Identification Card',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Solo Parent Leave is limited to seven (7) days per year.',
                    'It must be filed five (5) to seven (7) days before the start date of leave.',
                    'Solo Parent Identification Card attachment is required.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'study leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 180,
                'subtype_label' => 'Study Leave Details',
                'subtypes' => [
                    'masters' => 'Completion of Master’s Degree',
                    'bar_review' => 'BAR / Board Examination Review',
                ],
                'show_other_purpose' => true,
                'documents' => [
                    'study_contract' => 'Study Leave Contract',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Study Leave may be granted for up to six (6) months.',
                    'The study leave contract attachment is required.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'vawc leave':
        case '10-day vawc leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 10,
                'max_days_per_year' => 10,
                'deduct_balance' => false,
                'allow_emergency' => true,
                'documents' => [
                    'barangay_protection_order' => 'Barangay Protection Order (BPO)',
                    'court_protection_order' => 'Temporary/Permanent Protection Order (TPO/PPO)',
                    'bpo_tpo_ppo_filing_certification' => 'Certification that the BPO/TPO/PPO application has been filed',
                    'police_report_or_medical_certificate' => 'Police Report and/or Medical Certificate',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'VAWC leave is limited to ten (10) days per year.',
                    'It shall be filed in advance or immediately upon the woman employee’s return from such leave.',
                    'At least one supporting document from the list is required.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'rehabilitation privilege':
        case 'rehabilitation leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 300,
                'documents' => [
                    'rehabilitation_documents' => 'Rehabilitation Leave Supporting Document',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Rehabilitation Leave may be granted for up to ten (10) months.',
                    'Supporting attachment is required.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'special leave benefits for women':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 60,
                'show_surgery_details' => true,
                'documents' => [
                    'women_leave_supporting_document' => 'Special Leave Benefits for Women supporting document',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Special Leave Benefits for Women may be granted for up to two (2) months.',
                    'Supporting attachment is required.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'special emergency (calamity) leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'max_days' => 5,
                'max_days_per_year' => 5,
                'allow_emergency' => true,
                'show_calamity_location' => true,
                'documents' => [
                    'calamity_supporting_document' => 'Proof that the employee is eligible for Special Emergency (Calamity) Leave',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Special Emergency (Calamity) Leave may be applied for a maximum of five (5) straight working days or on a staggered basis within thirty (30) days from the actual occurrence of the calamity or disaster.',
                    'It may be enjoyed once a year only.',
                    'Supporting proof is required and must be validated by the office.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'monetization of leave credits':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'show_monetization_reason' => true,
                'documents' => [
                    'monetization_attachment' => 'Monetization Supporting Attachment',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Attachment is required for monetization of leave credits.',
                    'This leave deducts from the Vacational Balance.',
                ],
            ]);

        case 'terminal leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'deduct_balance' => false,
                'show_terminal_reason' => true,
                'documents' => [
                    'proof_of_separation' => 'Proof of resignation, retirement, or separation from the service',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Proof of resignation, retirement, or separation from the service is required.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        case 'adoption leave':
            return array_merge($base, [
                'bucket' => 'annual',
                'bucket_label' => 'Vacational Balance',
                'deduct_balance' => false,
                'documents' => [
                    'pre_adoptive_placement_authority' => 'Authenticated copy of the Pre-Adoptive Placement Authority issued by the DSWD',
                ],
                'required_doc_count' => 1,
                'rules_text' => [
                    'Application for Adoption Leave shall be filed with an authenticated copy of the Pre-Adoptive Placement Authority issued by the DSWD.',
                    'This leave does not deduct from any leave balance.',
                ],
            ]);

        default:
            return $base;
    }
}


$empStmt = $db->prepare("
    SELECT e.id, e.first_name, e.middle_name, e.last_name, e.department, e.position, e.salary,
           e.annual_balance, e.sick_balance, e.force_balance, u.email
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ?
    LIMIT 1
");
$empStmt->execute([$emp_id]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

$typesStmt = $db->query("SELECT * FROM leave_types ORDER BY id ASC");
$leaveTypes = $typesStmt->fetchAll(PDO::FETCH_ASSOC);

$leaveTypeUsageByYear = [];
$usageStmt = $db->prepare("
    SELECT leave_type_id, YEAR(start_date) AS usage_year, SUM(total_days) AS used_days
    FROM leave_requests
    WHERE employee_id = ?
      AND leave_type_id IS NOT NULL
      AND status IN ('pending', 'approved')
    GROUP BY leave_type_id, YEAR(start_date)
");
$usageStmt->execute([$emp_id]);
foreach ($usageStmt->fetchAll(PDO::FETCH_ASSOC) as $usageRow) {
    $typeId = (int)($usageRow['leave_type_id'] ?? 0);
    $year = (string)($usageRow['usage_year'] ?? '');
    if ($typeId > 0 && $year !== '') {
        $leaveTypeUsageByYear[$typeId][$year] = safeFloat($usageRow['used_days'] ?? 0);
    }
}

$balanceMap = [
    'annual' => safeFloat($employee['annual_balance'] ?? 0),
    'sick'   => safeFloat($employee['sick_balance'] ?? 0),
    'force'  => safeFloat($employee['force_balance'] ?? 0),
    'none'   => 0,
];

$leaveTypeRulesById = [];
foreach ($leaveTypes as $lt) {
    $preset = leaveRulePreset((string)$lt['name']);
    $preset['id'] = (int)$lt['id'];
    $preset['name'] = (string)$lt['name'];
    $preset['current_balance'] = $balanceMap[$preset['bucket']] ?? 0;
    $preset['secondary_balance'] = !empty($preset['secondary_bucket']) ? ($balanceMap[$preset['secondary_bucket']] ?? 0) : null;
    $preset['used_by_year'] = $leaveTypeUsageByYear[(int)$lt['id']] ?? [];
    $leaveTypeRulesById[(int)$lt['id']] = $preset;
}

$fullName = trim(
    (string)($employee['first_name'] ?? '') . ' ' .
    (string)($employee['middle_name'] ?? '') . ' ' .
    (string)($employee['last_name'] ?? '')
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Apply Leave</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js">
const attachmentInput = document.getElementById('attachments');
const attachmentFileList = document.getElementById('attachment-file-list');
if (attachmentInput && attachmentFileList) {
    attachmentInput.addEventListener('change', function () {
        attachmentFileList.innerHTML = '';
        Array.from(this.files || []).slice(0, 5).forEach(function (file) {
            const chip = document.createElement('span');
            chip.className = 'request-chip request-chip-neutral';
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            chip.textContent = file.name + ' (' + sizeMb + ' MB)';
            attachmentFileList.appendChild(chip);
        });
    });
}

</script>
    <style>
        .leave-application-card {
            max-width: 980px;
            margin: 0 auto;
        }
        .leave-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .readonly-box {
            width: 100%;
            padding: 10px 12px;
            min-height: 44px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #f8fafc;
            color: #111827;
            display: flex;
            align-items: center;
        }
        .section-block {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            background: #fff;
            margin-bottom: 18px;
        }
        .section-block h3 {
            margin-bottom: 14px;
            font-size: 17px;
        }
        .muted-note {
            font-size: 13px;
            color: #6b7280;
        }
        .rule-box {
            display: none;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            border-radius: 14px;
            padding: 16px;
            margin-top: 12px;
        }
        .rule-box.active {
            display: block;
        }
        .rule-box h4 {
            margin-bottom: 8px;
            font-size: 15px;
            color: #1d4ed8;
        }
        .rule-list {
            margin: 0;
            padding-left: 18px;
        }
        .rule-list li {
            margin-bottom: 6px;
            color: #1e3a8a;
            font-size: 14px;
        }
        .dynamic-area {
            display: none;
        }
        .dynamic-area.active {
            display: block;
        }
        .chip-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            background: #fff;
            font-size: 13px;
            color: #374151;
        }
        .radio-card-group,
        .check-card-group {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 8px;
        }
        .choice-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            cursor: pointer;
        }
        .choice-card input {
            width: auto;
            margin: 2px 0 0 0;
        }
        .choice-card span {
            color: #111827;
            font-size: 14px;
        }
        .doc-checklist {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .doc-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .doc-item input {
            width: auto;
            margin: 2px 0 0 0;
        }
        .doc-item span {
            color: #111827;
            font-size: 14px;
        }
        .warning-box {
            margin-top: 12px;
            border: 1px solid #fcd34d;
            background: #fffbeb;
            color: #92400e;
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 13px;
            display: none;
        }
        .warning-box.active {
            display: block;
        }
        .balance-banner {
            margin-top: 10px;
            display: none;
            padding: 12px 14px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid var(--border);
            color: #111827;
            font-size: 14px;
        }
        .balance-banner.active {
            display: block;
        }
        .compact-textarea {
            min-height: 110px;
        }
        @media (max-width: 900px) {
            .leave-grid-2,
            .radio-card-group,
            .check-card-group,
            .doc-checklist {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main">
    <?php
    $title = 'Apply Leave';
    $subtitle = 'Request leave and view your available balance';
    include __DIR__ . '/partials/ui/page-header.php';
    ?>
    <div class="ui-card leave-application-card">
        <h2 class="page-subtitle" style="text-align:center;margin-bottom:8px;">Application for Leave</h2>
        <p style="text-align:center;font-size:13px;color:#6b7280;margin-bottom:24px;">
            Fill out the request based on the official leave form. Extra instructions and requirements will appear only for the selected leave type.
        </p>

        <form method="POST" action="../controllers/LeaveController.php" id="applyLeaveForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">

            <div class="section-block">
                <h3>1. Employee Information</h3>
                <div class="leave-grid-2">
                    <div>
                        <label>Full Name</label>
                        <div class="readonly-box"><?= e($fullName); ?></div>
                    </div>
                    <div>
                        <label>Date of Filing</label>
                        <input type="date" name="filing_date" value="<?= e(date('Y-m-d')); ?>" required lang="en-CA" autocomplete="off">
                    </div>
                    <div>
                        <label>Office / Department</label>
                        <div class="readonly-box"><?= e($employee['department'] ?? ''); ?></div>
                    </div>
                    <div>
                        <label>Position</label>
                        <div class="readonly-box"><?= e($employee['position'] ?? ''); ?></div>
                    </div>
                    <div>
                        <label>Salary</label>
                        <div class="readonly-box"><?= safeFloat($employee['salary'] ?? 0) > 0 ? e(number_format((float)$employee['salary'], 2)) : '—'; ?></div>
                    </div>
                    <div>
                        <label>Email</label>
                        <div class="readonly-box"><?= e($employee['email'] ?? ''); ?></div>
                    </div>
                </div>
            </div>

            <div class="section-block">
                <h3>2. Leave Request</h3>

                <div class="leave-grid-2">
                    <div>
                        <label for="leave_type">Leave Type</label>
                        <select name="leave_type_id" id="leave_type" required>
                            <option value="">-- Select Leave Type --</option>
                            <?php foreach ($leaveTypes as $lt): ?>
                                <option value="<?= (int)$lt['id']; ?>"><?= e($lt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="commutation">Commutation</label>
                        <select name="commutation" id="commutation">
                            <option value="Not Requested">Not Requested</option>
                            <option value="Requested">Requested</option>
                        </select>
                    </div>

                    <div>
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" required lang="en-CA" autocomplete="off">
                    </div>

                    <div>
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" required lang="en-CA" autocomplete="off">
                    </div>

                    <div>
                        <label for="total_days">Total Days</label>
                        <input type="text" id="total_days" readonly>
                        <div id="date-range-feedback" class="muted-note" style="margin-top:8px;"></div>
                    </div>

                    <div>
                        <label>
                            <input type="checkbox" name="emergency_case" id="emergency_case" value="1" style="width:auto;margin-right:8px;">
                            Emergency case
                        </label>
                        <div class="muted-note">Use only when the selected leave type allows emergency filing.</div>
                    </div>
                </div>

                <div id="balance-banner" class="balance-banner"></div>
                <div id="rule-box" class="rule-box">
                    <h4>Selected Leave Type Rules</h4>
                    <ul id="rule-list" class="rule-list"></ul>
                </div>
                <div id="warning-box" class="warning-box"></div>
            </div>

            <div class="section-block dynamic-area" id="details-section">
                <h3>3. Leave Details</h3>

                <div id="subtype-wrapper" style="display:none;">
                    <label id="subtype-label">Leave Details</label>
                    <div id="subtype-options" class="radio-card-group"></div>
                </div>

                <div id="location-wrapper" style="display:none;">
                    <label id="location-label" for="detail_location">Specify Location / Destination</label>
                    <input type="text" name="details[location]" id="detail_location" placeholder="Enter location / destination">
                </div>

                <div id="illness-wrapper" style="display:none;">
                    <label for="detail_illness">Specify Illness / Condition</label>
                    <textarea name="details[illness]" id="detail_illness" class="compact-textarea" placeholder="Enter illness / medical condition"></textarea>
                </div>

                <div id="other-purpose-wrapper" style="display:none;">
                    <label for="detail_other_purpose">Other Purpose / Remarks</label>
                    <textarea name="details[other_purpose]" id="detail_other_purpose" class="compact-textarea" placeholder="Enter other purpose if needed"></textarea>
                </div>

                <div id="expected-delivery-wrapper" style="display:none;">
                    <label for="detail_expected_delivery">Expected Date of Delivery</label>
                    <input type="date" name="details[expected_delivery]" id="detail_expected_delivery" lang="en-CA" autocomplete="off">
                </div>

                <div id="calamity-location-wrapper" style="display:none;">
                    <label for="detail_calamity_location">Calamity / Disaster Location</label>
                    <input type="text" name="details[calamity_location]" id="detail_calamity_location" placeholder="Enter affected location">
                </div>

                <div id="surgery-wrapper" style="display:none;">
                    <label for="detail_surgery">Gynecological Surgery / Procedure Details</label>
                    <textarea name="details[surgery_details]" id="detail_surgery" class="compact-textarea" placeholder="Enter surgery details / clinical summary"></textarea>
                </div>

                <div id="monetization-wrapper" style="display:none;">
                    <label for="detail_monetization_reason">Reason for Monetization</label>
                    <textarea name="details[monetization_reason]" id="detail_monetization_reason" class="compact-textarea" placeholder="State valid and justifiable reasons"></textarea>
                </div>

                <div id="terminal-wrapper" style="display:none;">
                    <label for="detail_terminal_reason">Terminal Leave Basis</label>
                    <textarea name="details[terminal_reason]" id="detail_terminal_reason" class="compact-textarea" placeholder="Indicate resignation / retirement / separation details"></textarea>
                </div>

                <div id="force-balance-only-wrapper" style="display:none;margin-top:16px;">
                    <label class="chip">
                        <input type="checkbox" name="details[force_balance_only]" id="detail_force_balance_only" value="1" style="width:auto;">
                        Official seminar / work-aligned attendance (deduct from Force Balance only; do not deduct Vacational Balance)
                    </label>
                </div>

                <div style="margin-top:16px;">
                    <label for="reason">General Reason / Remarks</label>
                    <textarea name="reason" id="reason" rows="5" required class="compact-textarea" placeholder="Enter the reason for your leave request"></textarea>
                </div>
            </div>

            <div class="section-block dynamic-area" id="documents-section">
                <h3>4. Supporting Documents</h3>

                <div id="documents-list" class="doc-checklist"></div>

                <div class="chip-row">
                    <label class="chip">
                        <input type="checkbox" name="medical_certificate_attached" value="1" style="width:auto;">
                        Medical certificate attached
                    </label>
                    <label class="chip">
                        <input type="checkbox" name="affidavit_attached" value="1" style="width:auto;">
                        Affidavit attached
                    </label>
                </div>

                <div class="form-grid" style="margin-top:16px;">
                    <div style="grid-column:1 / -1;">
                        <label for="attachments">Upload Supporting Files</label>
                        <input type="file" name="attachments[]" id="attachments" multiple accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <div class="muted-note" style="margin-top:8px;">Attach up to 5 files. Allowed types: PDF, JPG, PNG, WEBP. Maximum 10MB each.</div>
                        <div id="attachment-file-list" class="request-chip-list" style="margin-top:10px;"></div>
                    </div>
                </div>
            </div>

            <div id="submit-block-note" class="warning-box" style="margin-top:0;margin-bottom:12px;"></div>
            <div style="text-align:center;margin-top:20px;">
                <button type="submit" id="submitLeaveButton" style="padding:12px 32px;font-size:16px;">Submit Leave Request</button>
            </div>
        </form>
    </div>
</div>

<script>
const leaveTypeRules = <?= json_encode($leaveTypeRulesById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function calculateDaysAndRefresh() {
    if (typeof calculateDays === 'function') {
        calculateDays(updateLeaveTypeUI);
        return;
    }
    updateLeaveTypeUI();
}

function renderSubtypeOptions(rule) {
    const wrapper = document.getElementById('subtype-wrapper');
    const label = document.getElementById('subtype-label');
    const options = document.getElementById('subtype-options');

    options.innerHTML = '';

    if (!rule || !rule.subtypes || Object.keys(rule.subtypes).length === 0) {
        wrapper.style.display = 'none';
        return;
    }

    label.textContent = rule.subtype_label || 'Leave Details';
    Object.entries(rule.subtypes).forEach(([value, text]) => {
        const item = document.createElement('label');
        item.className = 'choice-card';
        item.innerHTML = `
            <input type="radio" name="leave_subtype" value="${value}">
            <span>${text}</span>
        `;
        options.appendChild(item);
    });

    wrapper.style.display = 'block';
}

function renderDocuments(rule) {
    const section = document.getElementById('documents-section');
    const list = document.getElementById('documents-list');
    const selectedDocs = Array.from(document.querySelectorAll('input[name="supporting_documents[]"]:checked')).map(input => input.value);
    list.innerHTML = '';

    if (!rule || !rule.documents || Object.keys(rule.documents).length === 0) {
        section.classList.remove('active');
        return;
    }

    Object.entries(rule.documents).forEach(([key, text]) => {
        const item = document.createElement('label');
        item.className = 'doc-item';
        item.innerHTML = `
            <input type="checkbox" name="supporting_documents[]" value="${key}" ${selectedDocs.includes(key) ? 'checked' : ''}>
            <span>${text}</span>
        `;
        list.appendChild(item);
    });

    if ((rule.required_doc_count || 0) > 0) {
        const note = document.createElement('div');
        note.className = 'muted-note';
        note.style.marginTop = '8px';
        note.textContent = `Required upload count for this leave: ${rule.required_doc_count}`;
        list.appendChild(note);
    }

    section.classList.add('active');
}

function renderRules(rule) {
    const ruleBox = document.getElementById('rule-box');
    const ruleList = document.getElementById('rule-list');

    ruleList.innerHTML = '';

    if (!rule || !rule.show_rules || !rule.rules_text || rule.rules_text.length === 0) {
        ruleBox.classList.remove('active');
        return;
    }

    rule.rules_text.forEach(text => {
        const li = document.createElement('li');
        li.textContent = text;
        ruleList.appendChild(li);
    });

    ruleBox.classList.add('active');
}

function getSelectedRuleYear() {
    const start = document.getElementById('start_date').value;
    if (start && /^\d{4}-\d{2}-\d{2}$/.test(start)) {
        return start.slice(0, 4);
    }
    const filing = document.querySelector('input[name="filing_date"]').value;
    if (filing && /^\d{4}-\d{2}-\d{2}$/.test(filing)) {
        return filing.slice(0, 4);
    }
    return String(new Date().getFullYear());
}

function getRuleUsage(rule) {
    if (!rule || !rule.used_by_year) {
        return 0;
    }
    const year = getSelectedRuleYear();
    return Number(rule.used_by_year[year] || 0);
}

function getAttachmentCount() {
    const attachmentInput = document.getElementById('attachments');
    return attachmentInput && attachmentInput.files ? attachmentInput.files.length : 0;
}

function getSelectedSupportingDocumentCount() {
    return document.querySelectorAll('input[name="supporting_documents[]"]:checked').length;
}

function updateWarning(rule) {
    const warningBox = document.getElementById('warning-box');
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;
    const filing = document.querySelector('input[name="filing_date"]').value;
    const totalDays = parseFloat(document.getElementById('total_days').value || '0');

    let warnings = [];

    if (rule) {
        if (rule.min_days_notice && start && filing) {
            const startDate = new Date(start + 'T00:00:00');
            const filingDate = new Date(filing + 'T00:00:00');
            const diffMs = startDate - filingDate;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays < rule.min_days_notice) {
                warnings.push(`This leave is filed at least ${rule.min_days_notice} day(s) in advance.`);
            }
        }

        if (rule.max_days && totalDays > rule.max_days) {
            warnings.push(`This leave type allows up to ${rule.max_days} day(s) per application.`);
        }

        const usedThisYear = getRuleUsage(rule);
        if (rule.max_days_per_year && (usedThisYear + totalDays) > Number(rule.max_days_per_year)) {
            warnings.push(`This leave type allows up to ${rule.max_days_per_year} day(s) per year. Already filed this year: ${usedThisYear}.`);
        }

        const emergencyChecked = document.getElementById('emergency_case').checked;
        if (emergencyChecked && !rule.allow_emergency) {
            warnings.push('Emergency filing is not normally allowed for this leave type.');
        }

        const isSick = (rule.name || '').toLowerCase().includes('sick');
        if (isSick && totalDays > 5) {
            warnings.push('Sick leave exceeding five (5) continuous working days requires a medical certificate.');
        }

        if (isSick && end && filing) {
            const endDate = new Date(end + 'T00:00:00');
            const filingDate = new Date(filing + 'T00:00:00');
            const lateCheck = new Date(endDate);
            lateCheck.setMonth(lateCheck.getMonth() + 1);
            if (filingDate > lateCheck) {
                warnings.push('This sick leave was filed beyond one (1) calendar month. Personnel will choose whether it is with pay or without pay.');
            }
        }
    }

    if (warnings.length === 0) {
        warningBox.classList.remove('active');
        warningBox.innerHTML = '';
        return;
    }

    warningBox.innerHTML = warnings.join('<br>');
    warningBox.classList.add('active');
}

function refreshSubmitState(rule) {
    const submitBtn = document.getElementById('submitLeaveButton');
    const noteBox = document.getElementById('submit-block-note');
    if (!submitBtn || !noteBox) {
        return;
    }

    let reasons = [];
    const start = document.getElementById('start_date').value;
    const filing = document.querySelector('input[name="filing_date"]').value;
    const totalDays = parseFloat(document.getElementById('total_days').value || '0');
    const selectedDocs = getSelectedSupportingDocumentCount();
    const attachmentCount = getAttachmentCount();

    if (rule) {
        if (rule.min_days_notice && start && filing) {
            const startDate = new Date(start + 'T00:00:00');
            const filingDate = new Date(filing + 'T00:00:00');
            const diffDays = Math.floor((startDate - filingDate) / (1000 * 60 * 60 * 24));
            if (diffDays < rule.min_days_notice) {
                reasons.push(`Minimum notice requirement not met (${rule.min_days_notice} day(s)).`);
            }
        }

        if (rule.max_days && totalDays > rule.max_days) {
            reasons.push(`Maximum days per application exceeded (${rule.max_days}).`);
        }

        const usedThisYear = getRuleUsage(rule);
        if (rule.max_days_per_year && (usedThisYear >= Number(rule.max_days_per_year) || (usedThisYear + totalDays) > Number(rule.max_days_per_year))) {
            reasons.push(`Maximum days per year exceeded (${rule.max_days_per_year}).`);
        }

        if ((rule.required_doc_count || 0) > 0) {
            if (selectedDocs < Number(rule.required_doc_count)) {
                reasons.push(`Select at least ${rule.required_doc_count} required document type(s).`);
            }
            if (attachmentCount < Number(rule.required_doc_count)) {
                reasons.push(`Upload at least ${rule.required_doc_count} supporting attachment file(s).`);
            }
        }

        const isSick = (rule.name || '').toLowerCase().includes('sick');
        const medCertChecked = !!document.querySelector('input[name="medical_certificate_attached"]:checked');
        if (isSick && totalDays > 5) {
            if (!medCertChecked) {
                reasons.push('Medical certificate checkbox must be marked for sick leave beyond five (5) days.');
            }
            if (attachmentCount < 1) {
                reasons.push('Upload the medical certificate file for this sick leave request.');
            }
        }
    }

    submitBtn.disabled = reasons.length > 0;
    if (reasons.length > 0) {
        noteBox.innerHTML = reasons.join('<br>');
        noteBox.classList.add('active');
    } else {
        noteBox.innerHTML = '';
        noteBox.classList.remove('active');
    }
}

function updateLeaveTypeUI() {
    const typeElem = document.getElementById('leave_type');
    const selectedId = typeElem.value;
    const rule = leaveTypeRules[selectedId] || null;

    const detailsSection = document.getElementById('details-section');
    const balanceBanner = document.getElementById('balance-banner');

    detailsSection.classList.toggle('active', !!rule);

    if (rule) {
        if (rule.deduct_balance === false) {
            balanceBanner.innerHTML = `<strong>${rule.name}</strong> does not deduct from any leave balance.`;
        } else if (rule.secondary_bucket_label && rule.secondary_balance !== null) {
            balanceBanner.innerHTML = `<strong>${rule.name}</strong> deducts from <strong>${rule.bucket_label}</strong> and <strong>${rule.secondary_bucket_label}</strong> · Current balances: <strong>${Number(rule.current_balance).toFixed(3)}</strong> + <strong>${Number(rule.secondary_balance).toFixed(3)}</strong> day(s)`;
        } else {
            balanceBanner.innerHTML = `<strong>${rule.name}</strong> deducts from <strong>${rule.bucket_label}</strong> · Current balance: <strong>${Number(rule.current_balance).toFixed(3)}</strong> day(s)`;
        }
        balanceBanner.classList.add('active');
    } else {
        balanceBanner.classList.remove('active');
        balanceBanner.innerHTML = '';
    }

    renderRules(rule);
    renderSubtypeOptions(rule);
    renderDocuments(rule);

    document.getElementById('location-wrapper').style.display = rule && rule.show_location_text ? 'block' : 'none';
    document.getElementById('location-label').textContent = rule && rule.location_label ? rule.location_label : 'Specify';

    document.getElementById('illness-wrapper').style.display = rule && rule.show_illness_text ? 'block' : 'none';
    document.getElementById('other-purpose-wrapper').style.display = rule && rule.show_other_purpose ? 'block' : 'none';
    document.getElementById('expected-delivery-wrapper').style.display = rule && rule.show_expected_delivery ? 'block' : 'none';
    document.getElementById('calamity-location-wrapper').style.display = rule && rule.show_calamity_location ? 'block' : 'none';
    document.getElementById('surgery-wrapper').style.display = rule && rule.show_surgery_details ? 'block' : 'none';
    document.getElementById('monetization-wrapper').style.display = rule && rule.show_monetization_reason ? 'block' : 'none';
    document.getElementById('terminal-wrapper').style.display = rule && rule.show_terminal_reason ? 'block' : 'none';
    document.getElementById('force-balance-only-wrapper').style.display = rule && rule.show_force_balance_only ? 'block' : 'none';

    updateWarning(rule);
    refreshSubmitState(rule);
}

window.addEventListener('load', function () {
    const leaveType = document.getElementById('leave_type');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const filingDate = document.querySelector('input[name="filing_date"]');
    const emergencyCase = document.getElementById('emergency_case');

    if (leaveType) {
        leaveType.addEventListener('change', function () {
            updateLeaveTypeUI();
            calculateDaysAndRefresh();
        });
    }

    if (startDate) {
        startDate.addEventListener('change', calculateDaysAndRefresh);
    }

    if (endDate) {
        endDate.addEventListener('change', calculateDaysAndRefresh);
    }

    if (filingDate) {
        filingDate.addEventListener('change', updateLeaveTypeUI);
    }

    if (emergencyCase) {
        emergencyCase.addEventListener('change', updateLeaveTypeUI);
    }

    document.addEventListener('change', function (event) {
        if (!event.target) {
            return;
        }
        if (event.target.name === 'medical_certificate_attached' || event.target.id === 'detail_force_balance_only') {
            updateLeaveTypeUI();
            return;
        }
        if (event.target.name === 'supporting_documents[]') {
            const selectedId = document.getElementById('leave_type').value;
            const rule = leaveTypeRules[selectedId] || null;
            refreshSubmitState(rule);
        }
    });

    updateLeaveTypeUI();
});

const attachmentInput = document.getElementById('attachments');
const attachmentFileList = document.getElementById('attachment-file-list');
if (attachmentInput && attachmentFileList) {
    attachmentInput.addEventListener('change', function () {
        attachmentFileList.innerHTML = '';
        Array.from(this.files || []).slice(0, 5).forEach(function (file) {
            const chip = document.createElement('span');
            chip.className = 'request-chip request-chip-neutral';
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            chip.textContent = file.name + ' (' + sizeMb + ' MB)';
            attachmentFileList.appendChild(chip);
        });
        updateLeaveTypeUI();
    });
}
</script>

</body>
</html>
