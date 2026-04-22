<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../helpers/Flash.php';
require_once __DIR__ . '/../../helpers/Auth.php';
require_once __DIR__ . '/../../helpers/Notifications.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . Auth::appUrl('login'));
    exit();
}
$role = $_SESSION['role'] ?? '';
$flashMessages = flash_get_all();

$reportsHref = 'reports.php';

if (!isset($db)) {
    require_once __DIR__ . '/../../config/database.php';
    $db = (new Database())->connect();
}
$sidebarNotificationCounts = app_notification_sidebar_counts($db, $_SESSION);
?>
<?php include __DIR__ . '/../layout/header.php'; ?>

<?php
// Detect current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="app-sidebar">
    <nav class="sidebar-nav">
        <!-- MAIN SECTION -->
        <a href="dashboard.php" class="sidebar-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
            <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 5a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V9zm0 5a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <?php if(in_array($role,['admin','manager','department_head','hr','personnel'], true)): ?>
            <a href="leave_requests.php" class="sidebar-link <?= ($current_page === 'leave_requests.php') ? 'active' : '' ?>">
                <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4z" clip-rule="evenodd" />
                </svg>
                <span>Leave Requests</span>
                <?php if (!empty($sidebarNotificationCounts['leave_requests'])): ?><span class="sidebar-badge"><?= (int)$sidebarNotificationCounts['leave_requests']; ?></span><?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if(in_array($role,['employee','manager','department_head'], true)): ?>
            <a href="apply_leave.php" class="sidebar-link <?= ($current_page === 'apply_leave.php') ? 'active' : '' ?>">
                <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" />
                </svg>
                <span>Apply Leave</span>
            </a>
        <?php endif; ?>
        <?php if(in_array($role,['employee','manager','department_head','hr','personnel','admin'], true)): ?>
            <a href="calendar.php" class="sidebar-link <?= ($current_page === 'calendar.php') ? 'active' : '' ?>">
                <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a2 2 0 012-2h8a2 2 0 012 2v9a2 2 0 01-2 2H8a2 2 0 01-2-2V7z" clip-rule="evenodd" />
                </svg>
                <span>Calendar</span>
            </a>
        <?php endif; ?>

        <?php if(in_array($role,['admin','hr','personnel','department_head'], true)): ?>
            <a href="<?= htmlspecialchars($reportsHref); ?>" class="sidebar-link <?= ($current_page === 'reports.php') ? 'active' : '' ?>">
                <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" />
                </svg>
                <span>Reports</span>
            </a>
        <?php endif; ?>

        <!-- MANAGEMENT SECTION -->
        <?php if($role === 'admin' || in_array($role,['hr','personnel'], true)): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-label">Management</div>
                
        <?php if($role === 'admin'): ?>
                    <a href="manage_employees.php" class="sidebar-link <?= ($current_page === 'manage_employees.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/>
                        </svg>
                        <span>Manage Employees</span>
                    </a>
                    <a href="manage_departments.php" class="sidebar-link <?= ($current_page === 'manage_departments.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                        </svg>
                        <span>Departments</span>
                    </a>
                <?php endif; ?>

                <?php if(in_array($role,['admin','hr','personnel'], true)): ?>
                    <a href="holidays.php" class="sidebar-link <?= ($current_page === 'holidays.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Holidays</span>
                    </a>
                <?php endif; ?>

                <?php if($role === 'admin'): ?>
                    <a href="manage_accruals.php" class="sidebar-link <?= ($current_page === 'manage_accruals.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M8.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM12.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM16 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                        </svg>
                        <span>Accruals</span>
                    </a>
                    <a href="manage_leave_types.php" class="sidebar-link <?= ($current_page === 'manage_leave_types.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M4 4a2 2 0 012-2h4l4 4h4a2 2 0 012 2v4a2 2 0 01-2 2h-4l-4-4H6a2 2 0 01-2-2V4z" />
                        </svg>
                        <span>Leave Types</span>
                    </a>
                <?php endif; ?>

                <?php if(in_array($role,['personnel','admin','hr'], true)): ?>
                    <a href="signatories_settings.php" class="sidebar-link <?= ($current_page === 'signatories_settings.php') ? 'active' : '' ?>">
                        <svg class="sidebar-link-icon" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                        </svg>
                        <span>Settings</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </nav>
</aside>

<script>
function toggleProfileMenu() {
    var menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}
function openSettings() {
    window.location.href = 'change_password.php';
}
window.addEventListener('click', function(e){
    var menu = document.getElementById('profileMenu');
    var section = document.querySelector('.profile-section');
    if(menu && section && !section.contains(e.target)) {
        menu.style.display = 'none';
    }
});
function showToast(message, type = 'info', duration = 3000) {
    var container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(function() {
        toast.classList.add('removing');
        setTimeout(function() {
            toast.remove();
        }, 300);
    }, duration);
}
const sessionFlashMessages = <?= json_encode($flashMessages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function checkFlashMessage() {
    var params = new URLSearchParams(window.location.search);
    var cleaned = false;

    if (Array.isArray(sessionFlashMessages)) {
        sessionFlashMessages.forEach(function(item) {
            if (item && item.message) {
                showToast(item.message, item.type || 'info');
            }
        });
    }

    if (params.has('toast_success')) {
        showToast(decodeURIComponent(params.get('toast_success')), 'success');
        cleaned = true;
    }
    if (params.has('toast_error')) {
        showToast(decodeURIComponent(params.get('toast_error')), 'error');
        cleaned = true;
    }
    if (params.has('toast_warning')) {
        showToast(decodeURIComponent(params.get('toast_warning')), 'warning');
        cleaned = true;
    }
    if (params.has('toast_info')) {
        showToast(decodeURIComponent(params.get('toast_info')), 'info');
        cleaned = true;
    }
    if (params.has('added_history')) {
        showToast('Historical entry added successfully!', 'success');
        cleaned = true;
    }
    if (params.has('undertime')) {
        showToast('Undertime recorded successfully!', 'success');
        cleaned = true;
    }
    if (params.has('saved')) {
        showToast('Signatories updated successfully!', 'success');
        cleaned = true;
    }
    if (cleaned) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}
document.addEventListener('DOMContentLoaded', checkFlashMessage);
</script>

<script>
(function(){
    try {
        var href = '../pictures/DEPED.jpg';
        var existing = document.querySelector("link[rel~='icon']");
        if (existing) {
            existing.href = href;
        } else {
            var l = document.createElement('link');
            l.rel = 'icon';
            l.type = 'image/jpeg';
            l.href = href;
            document.getElementsByTagName('head')[0].appendChild(l);
        }
    } catch (e) {}
})();
</script>
