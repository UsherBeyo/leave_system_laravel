<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/database.php';
require_once '../helpers/Auth.php';
Auth::requireLogin('login.php');
require_once '../helpers/DateHelper.php';
require_once '../models/Leave.php';
require_once '../helpers/Flash.php';
require_once '../helpers/Pagination.php';

if ($_SESSION['role'] !== 'admin') {
    flash_redirect('dashboard.php', 'error', 'Access denied');
}

$db = (new Database())->connect();
$leaveModel = new Leave($db);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle manual single-employee accrual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_accrual'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_redirect('manage_accruals.php', 'error', 'CSRF validation failed.');
    }

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $month = trim($_POST['month'] ?? date('Y-m'));

    if ($employee_id <= 0) {
        flash_redirect('manage_accruals.php', 'error', 'Please select an employee');
    }

    if ($amount <= 0) {
        flash_redirect('manage_accruals.php', 'error', 'Accrual amount must be greater than zero');
    }

    $ok = $leaveModel->accrueSingleEmployee(
        $employee_id,
        $amount,
        $month,
        date('Y-m-d'),
        'Manual accrual recorded'
    );

    if ($ok) {
        flash_redirect('manage_accruals.php', 'success', 'Manual accrual recorded successfully');
    } else {
        flash_redirect('manage_accruals.php', 'error', 'Failed to record manual accrual');
    }
}

// Handle bulk accrual for all employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_bulk_accrual'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        flash_redirect('manage_accruals.php', 'error', 'CSRF validation failed.');
    }

    $amount = floatval($_POST['bulk_amount'] ?? 0);
    $month = trim($_POST['bulk_month'] ?? date('Y-m'));

    if ($amount <= 0) {
        flash_redirect('manage_accruals.php', 'error', 'Bulk accrual amount must be greater than zero');
    }

    $result = $leaveModel->accrueAllEmployees(
        $amount,
        $month,
        date('Y-m-d'),
        'Bulk accrual recorded'
    );

    if (!empty($result['success'])) {
        $count = intval($result['count'] ?? 0);
        flash_redirect('manage_accruals.php', 'success', "Bulk accrual completed for {$count} employee(s).");
    } else {
        flash_redirect('manage_accruals.php', 'error', $result['message'] ?? 'Failed to perform bulk accrual.');
    }
}

// Get employees for dropdown
$employees = $db->query("
    SELECT id, first_name, last_name, annual_balance, sick_balance
    FROM employees
    ORDER BY first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get accrual history
$accruals = [];
try {
    $accruals = $db->query("
        SELECT 
            a.id,
            a.employee_id,
            a.amount,
            a.date_accrued AS created_at,
            a.month_reference,
            e.first_name,
            e.last_name
        FROM accrual_history a
        JOIN employees e ON a.employee_id = e.id
        ORDER BY a.date_accrued DESC, a.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    try {
        $accruals = $db->query("
            SELECT 
                a.id,
                a.employee_id,
                a.amount,
                a.created_at,
                NULL AS month_reference,
                e.first_name,
                e.last_name
            FROM accruals a
            JOIN employees e ON a.employee_id = e.id
            ORDER BY a.created_at DESC, a.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ex2) {
        $accruals = [];
    }
}

$totalEmployees = (int)$db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$accrualSearch = trim((string)($_GET['history_q'] ?? ''));
$accruals = pagination_filter_array($accruals, $accrualSearch, [
    function ($a) { return trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')); },
    'amount', 'month_reference', 'created_at'
]);
$accrualPagination = paginate_array($accruals, (int)($_GET['history_page'] ?? 1), 12);
$accruals = $accrualPagination['items'];
?>

<!DOCTYPE html>
<html>
<head>
    <base href="<?= htmlspecialchars(rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/', ENT_QUOTES, 'UTF-8'); ?>">
    <title>Manage Accruals</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="../assets/js/script.js"></script>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="app-main accruals-page">
    <div class="accruals-header">
        <h2>Manage Accruals</h2>
        <p class="accruals-subtitle">Add leave accruals for employees.</p>
    </div>

    <div class="accrual-card accrual-bulk-launcher">
        <div class="accrual-bulk-copy">
            <h3>Bulk Accrual for All Employees</h3>
            <p class="accrual-description">Open a focused modal to add the same accrual amount to both <strong>Vacational</strong> and <strong>Sick</strong> balances of <strong>all employees</strong> without cluttering the page.</p>
            <div class="accrual-note">
                <span class="accrual-note-icon">⚠</span>
                <span><strong>Note:</strong> Force Leave is not affected here.</span>
            </div>
        </div>
        <div class="accrual-bulk-highlights">
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Employees Affected</span>
                <strong><?= $totalEmployees; ?></strong>
            </div>
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Default Amount</span>
                <strong>1.250 days</strong>
            </div>
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Balance Impact</span>
                <strong>Vacational + Sick</strong>
            </div>
        </div>
        <div class="accrual-bulk-launcher-actions">
            <button type="button" class="btn btn-primary accrual-bulk-trigger" id="openBulkAccrualModal">Open Bulk Accrual</button>
        </div>
    </div>

    <div class="accrual-lower-grid">
        <div class="manual-accrual-card">
            <h3>Record Manual Accrual</h3>
            <p class="accrual-description">Use this to record manual accruals for past periods or special cases.</p>

            <form method="POST" class="accrual-manual-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="record_accrual" value="1">

                <div class="accrual-form-item">
                    <label>Employee</label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id']; ?>">
                                <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?>
                                (Vac: <?= number_format((float)$e['annual_balance'], 3); ?> | Sick: <?= number_format((float)$e['sick_balance'], 3); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="accrual-form-item">
                    <label>Amount (days)</label>
                    <input type="number" step="0.001" name="amount" value="1.250" required>
                </div>

                <div class="accrual-form-item">
                    <label>For Month</label>
                    <input type="month" name="month" value="<?= date('Y-m'); ?>" required>
                </div>

                <div class="accrual-form-actions">
                    <button type="submit" class="btn btn-primary">Record Accrual</button>
                </div>
            </form>
        </div>

        <div class="history-card ajax-fragment" data-fragment-id="accrual-history" data-page-param="history_page" data-search-param="history_q">
            <h3>Accrual History</h3>
            <p class="accrual-description">Recent accrual transactions.</p>
            <div class="fragment-toolbar">
                <div class="search-input">
                    <input class="form-control live-search-input" type="text" name="history_q" value="<?= htmlspecialchars($accrualSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search employee or month...">
                </div>
                <div class="fragment-summary">Showing <?= $accrualPagination['from']; ?>–<?= $accrualPagination['to']; ?> of <?= $accrualPagination['total']; ?> history rows</div>
            </div>
            <div class="history-table-shell">
                <table class="accrual-history-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Amount</th>
                            <th>Month Ref</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accruals as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                            <td><span class="amount-pill"><?= number_format((float)$a['amount'], 3); ?> days</span></td>
                            <td><?= htmlspecialchars(!empty($a['month_reference']) ? app_format_month_ref($a['month_reference']) : '—'); ?></td>
                            <td><?= !empty($a['created_at']) ? htmlspecialchars(app_format_date($a['created_at'])) : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= pagination_render($accrualPagination, 'history_page'); ?>
        </div>
    </div>
</div>

<div id="bulkAccrualModal" class="modal accrual-bulk-modal">
    <div class="modal-content accrual-bulk-modal-content">
        <button type="button" class="modal-close" id="closeBulkAccrualModal" aria-label="Close bulk accrual">&times;</button>
        <div class="accrual-bulk-modal-header">
            <span class="accrual-bulk-kicker">Bulk action</span>
            <h3>Bulk Accrual for All Employees</h3>
            <p class="accrual-description">Set the accrual once, review the impact, then confirm from this modal. This keeps the main page cleaner while still using the same accrual logic.</p>
        </div>

        <div class="accrual-bulk-summary-grid">
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Employees Affected</span>
                <strong><?= $totalEmployees; ?> employee(s)</strong>
            </div>
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Changes Applied To</span>
                <strong>Vacational + Sick</strong>
            </div>
            <div class="accrual-highlight">
                <span class="accrual-highlight-label">Not Included</span>
                <strong>Force Leave</strong>
            </div>
        </div>

        <form method="POST" id="bulkAccrualForm" class="accrual-form-grid accrual-bulk-modal-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="record_bulk_accrual" value="1">

            <div class="accrual-form-item">
                <label>Employees Affected</label>
                <input type="text" value="<?= $totalEmployees; ?> employee(s)" readonly>
            </div>

            <div class="accrual-form-item">
                <label>Amount to Add (days)</label>
                <input type="number" step="0.001" name="bulk_amount" id="bulk_amount" value="1.250" required>
            </div>

            <div class="accrual-form-item">
                <label>For Month</label>
                <input type="month" name="bulk_month" id="bulk_month" value="<?= date('Y-m'); ?>" required>
            </div>

            <div class="accrual-note accrual-bulk-inline-note">
                <span class="accrual-note-icon">⚠</span>
                <span>Bulk accrual updates every employee and writes matching accrual history logs for the selected month.</span>
            </div>

            <div class="accrual-form-actions accrual-bulk-actions">
                <button type="button" class="btn btn-secondary" id="cancelBulkAccrualModal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Accrual to All Employees</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var bulkModal = document.getElementById('bulkAccrualModal');
    var openBulkModalBtn = document.getElementById('openBulkAccrualModal');
    var closeBulkModalBtn = document.getElementById('closeBulkAccrualModal');
    var cancelBulkModalBtn = document.getElementById('cancelBulkAccrualModal');
    var bulkAccrualForm = document.getElementById('bulkAccrualForm');

    function openBulkAccrualModal() {
        if (!bulkModal) return;
        bulkModal.classList.add('open');
        var firstInput = bulkModal.querySelector('#bulk_amount');
        if (firstInput) {
            setTimeout(function() { firstInput.focus(); }, 60);
        }
    }

    function closeBulkAccrualModal() {
        if (!bulkModal) return;
        bulkModal.classList.remove('open');
    }

    if (openBulkModalBtn) {
        openBulkModalBtn.addEventListener('click', openBulkAccrualModal);
    }

    [closeBulkModalBtn, cancelBulkModalBtn].forEach(function(btn) {
        if (!btn) return;
        btn.addEventListener('click', closeBulkAccrualModal);
    });

    if (bulkModal) {
        bulkModal.addEventListener('click', function(e) {
            if (e.target === bulkModal) closeBulkAccrualModal();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && bulkModal && bulkModal.classList.contains('open')) {
            closeBulkAccrualModal();
        }
    });

    if (bulkAccrualForm) {
        bulkAccrualForm.addEventListener('submit', function(e) {
            var amount = document.getElementById('bulk_amount').value || '1.250';
            var month = document.getElementById('bulk_month').value || '';

            var step1 = confirm(
                'Are you sure you want to add ' + amount + ' day(s) to BOTH Vacational and Sick balances of ALL employees?'
            );
            if (!step1) {
                e.preventDefault();
                return;
            }

            var step2 = confirm(
                'This will affect all employees and write accrual history logs for month ' + month + '. Continue?'
            );
            if (!step2) {
                e.preventDefault();
                return;
            }

            var step3 = confirm(
                'Final confirmation: this can be done even if it is NOT yet the end of the month. Force Leave will NOT be changed. Do you want to proceed?'
            );
            if (!step3) {
                e.preventDefault();
            }
        });
    }
})();
</script>

</body>
</html>
