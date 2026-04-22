<?php
// header.php - Application header with title and user info
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../helpers/Notifications.php';

// Get user display name, email, profile picture, and notifications
$displayName = $_SESSION['email'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$profilePic = null;
$userRole = $_SESSION['role'] ?? '';
if (!empty($_SESSION['emp_id'])) {
    if (!isset($db)) {
        require_once __DIR__ . '/../../config/database.php';
        $db = (new Database())->connect();
    }
    $stmt = $db->prepare("SELECT first_name, last_name, profile_pic FROM employees WHERE id = ?");
    $stmt->execute([$_SESSION['emp_id']]);
    $empRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empRecord) {
        $displayName = $empRecord['first_name'] . ' ' . $empRecord['last_name'];
        $profilePic = $empRecord['profile_pic'] ?? null;
    }
}

$headerNotifications = app_header_notifications($db, $_SESSION, $empRecord ?? null, 8);
$headerNotificationCount = (int)($headerNotifications['count'] ?? 0);
$headerNotificationItems = $headerNotifications['items'] ?? [];
$headerLatestNotificationTs = 0;
foreach ($headerNotificationItems as $notifItem) {
    $ts = (int)($notifItem['when_ts'] ?? 0);
    if ($ts > $headerLatestNotificationTs) {
        $headerLatestNotificationTs = $ts;
    }
}
?>
<header class="app-header">
    <div class="header-content-wrapper">
        <div class="header-left">
            <div class="header-brand-area">
                <h1 class="app-title">Leave System</h1>
            </div>
        </div>
        <div class="header-right-container">
            <div class="header-right">
                <div class="header-notification" data-latest-ts="<?= (int)$headerLatestNotificationTs; ?>">
                    <button type="button" class="notification-bell-btn" id="notificationBell" onclick="toggleNotificationMenu(event)" aria-label="Notifications" aria-expanded="false">
                        <svg class="notification-bell-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="currentColor" d="M12 2a6.5 6.5 0 0 0-6.5 6.5v2.96c0 .82-.27 1.62-.76 2.28L3.4 15.5a1 1 0 0 0 .8 1.6h15.6a1 1 0 0 0 .8-1.6l-1.34-1.76a3.82 3.82 0 0 1-.76-2.28V8.5A6.5 6.5 0 0 0 12 2Zm2.87 17.5a3 3 0 0 1-5.74 0h5.74Z"></path>
                        </svg>
                        <span class="notification-bell-badge<?= $headerNotificationCount > 0 ? '' : ' is-hidden'; ?>" id="notificationBellBadge"><?= (int)$headerNotificationCount; ?></span>
                    </button>
                    <div id="notificationMenu" class="notification-menu" style="display:none;">
                        <div class="notification-menu-header">
                            <div>
                                <strong>Notifications</strong>
                                <span><?= count($headerNotificationItems); ?> recent</span>
                            </div>
                        </div>
                        <div class="notification-menu-list">
                            <?php if (empty($headerNotificationItems)): ?>
                                <div class="notification-empty-state">No new leave updates right now.</div>
                            <?php else: ?>
                                <?php foreach ($headerNotificationItems as $item): ?>
                                    <a href="<?= htmlspecialchars($item['href'] ?? '#', ENT_QUOTES, 'UTF-8'); ?>" class="notification-item notification-tone-<?= htmlspecialchars($item['tone'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="notification-item-dot"></span>
                                        <span class="notification-item-body">
                                            <span class="notification-item-title"><?= htmlspecialchars((string)($item['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="notification-item-message"><?= htmlspecialchars((string)($item['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="notification-item-time"><?= htmlspecialchars((string)($item['when_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="profile-section">
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($displayName); ?></div>
                        <div class="profile-email"><?= htmlspecialchars($userEmail); ?></div>
                    </div>
                    <button class="profile-button" id="profileButton" onclick="toggleProfileMenu()">
                        <?php if (!empty($profilePic)): ?>
                            <img src="<?= htmlspecialchars($profilePic); ?>" alt="Profile" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar profile-avatar-placeholder">
                                <span><?= strtoupper(substr($displayName, 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                    </button>
                    <div id="profileMenu" class="profile-menu" style="display: none;">
                        <?php if ($userRole !== 'admin'): ?>
                            <a href="employee_profile.php?id=<?= $_SESSION['emp_id'] ?? ''; ?>" class="profile-menu-item">Profile</a>
                        <?php endif; ?>
                        <a href="change_password.php" class="profile-menu-item">Settings</a>
                        <hr style="margin: 4px 0; border: none; border-top: 1px solid var(--border);">
                        <a href="../controllers/logout.php" class="profile-menu-item">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleProfileMenu() {
    var menu = document.getElementById('profileMenu');
    menu.style.display = menu.style.display === 'none' || menu.style.display === '' ? 'block' : 'none';
}

function notificationSeenStorageKey() {
    return 'leave_notifications_seen_' + <?= (int)($_SESSION['user_id'] ?? 0); ?>;
}

function updateNotificationBadgeVisibility() {
    var bellWrap = document.querySelector('.header-notification');
    var badge = document.getElementById('notificationBellBadge');
    if (!bellWrap || !badge) return;
    var latestTs = parseInt(bellWrap.getAttribute('data-latest-ts') || '0', 10) || 0;
    var seenTs = parseInt(localStorage.getItem(notificationSeenStorageKey()) || '0', 10) || 0;
    if (latestTs > 0 && latestTs <= seenTs) {
        badge.classList.add('is-hidden');
    }
}

function markNotificationsSeen() {
    var bellWrap = document.querySelector('.header-notification');
    var badge = document.getElementById('notificationBellBadge');
    if (!bellWrap) return;
    var latestTs = parseInt(bellWrap.getAttribute('data-latest-ts') || '0', 10) || 0;
    localStorage.setItem(notificationSeenStorageKey(), String(latestTs));
    if (badge) badge.classList.add('is-hidden');
}

function toggleNotificationMenu(event) {
    if (event) event.stopPropagation();
    var menu = document.getElementById('notificationMenu');
    var bell = document.getElementById('notificationBell');
    var profileMenu = document.getElementById('profileMenu');
    if (!menu || !bell) return;
    var isOpening = (menu.style.display === 'none' || menu.style.display === '');
    if (profileMenu) profileMenu.style.display = 'none';
    menu.style.display = isOpening ? 'block' : 'none';
    bell.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
    if (isOpening) {
        markNotificationsSeen();
    }
}

// Force a reload when the page is restored from the browser back-forward cache
window.addEventListener('pageshow', function (event) {
    var navEntries = (window.performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation') : [];
    var navType = navEntries.length ? navEntries[0].type : '';
    if (event.persisted || navType === 'back_forward') {
        window.location.reload();
    }
});

// Close menu when clicking outside
window.addEventListener('click', function(e){
    var profileMenu = document.getElementById('profileMenu');
    var profileSection = document.querySelector('.profile-section');
    if (profileMenu && profileSection && !profileSection.contains(e.target)) {
        profileMenu.style.display = 'none';
    }
    var notificationMenu = document.getElementById('notificationMenu');
    var notificationWrap = document.querySelector('.header-notification');
    var notificationBell = document.getElementById('notificationBell');
    if (notificationMenu && notificationWrap && !notificationWrap.contains(e.target)) {
        notificationMenu.style.display = 'none';
        if (notificationBell) notificationBell.setAttribute('aria-expanded', 'false');
    }
});

document.addEventListener('DOMContentLoaded', updateNotificationBadgeVisibility);
</script>
