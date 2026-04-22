@php
    $employee = auth()->user()->employee;
    $displayName = $employee?->fullName() ?: auth()->user()->email;
    $profilePic = $employee?->profile_pic;
    $userRole = auth()->user()->role;
    $profileRouteParams = auth()->user()->employee?->id ? ['employee' => auth()->user()->employee->id] : [];
    $headerNotifications = $headerNotifications ?? ['count' => 0, 'items' => [], 'latest_ts' => 0];
    $headerNotificationCount = (int) ($headerNotifications['count'] ?? 0);
    $headerNotificationItems = $headerNotifications['items'] ?? [];
    $headerLatestNotificationTs = (int) ($headerNotifications['latest_ts'] ?? 0);
    $profilePicUrl = $profilePic ? asset(ltrim(str_replace('../', '', $profilePic), '/')) : null;
@endphp
<header class="app-header">
    <style>
        .header-notification { position: relative; display:flex; align-items:center; }
        .header-profile-avatar,
        .header-profile-avatar-placeholder {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            max-width: 44px !important;
            max-height: 44px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .header-profile-avatar-placeholder { background: linear-gradient(135deg, #2563eb, #1d4ed8); color:#fff; font-weight:700; }
        .profile-button { width: 48px; height: 48px; border-radius: 999px; display:inline-flex; align-items:center; justify-content:center; padding:0; overflow:hidden; }
        .header-right { display:flex; align-items:center; gap:12px; }
        .notification-bell-btn { position:relative; }
        .notification-bell-badge { position:absolute; top:-2px; right:-2px; }
        @media (max-width: 640px) {
            .header-profile-avatar,
            .header-profile-avatar-placeholder { width: 38px !important; height: 38px !important; min-width:38px !important; min-height:38px !important; max-width:38px !important; max-height:38px !important; }
            .profile-button { width: 42px; height: 42px; }
        }
    </style>
    <div class="header-content-wrapper">
        <div class="header-left">
            <div class="header-brand-area">
                <h1 class="app-title">Leave System</h1>
            </div>
        </div>
        <div class="header-right-container">
            <div class="header-right">
                <div class="header-notification" data-latest-ts="{{ $headerLatestNotificationTs }}">
                    <button type="button" class="notification-bell-btn" id="notificationBell" onclick="toggleNotificationMenu(event)" aria-label="Notifications" aria-expanded="false">
                        <svg class="notification-bell-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="currentColor" d="M12 2a6.5 6.5 0 0 0-6.5 6.5v2.96c0 .82-.27 1.62-.76 2.28L3.4 15.5a1 1 0 0 0 .8 1.6h15.6a1 1 0 0 0 .8-1.6l-1.34-1.76a3.82 3.82 0 0 1-.76-2.28V8.5A6.5 6.5 0 0 0 12 2Zm2.87 17.5a3 3 0 0 1-5.74 0h5.74Z"></path>
                        </svg>
                        <span class="notification-bell-badge{{ $headerNotificationCount > 0 ? '' : ' is-hidden' }}" id="notificationBellBadge">{{ $headerNotificationCount }}</span>
                    </button>
                    <div id="notificationMenu" class="notification-menu" style="display:none;">
                        <div class="notification-menu-header">
                            <div>
                                <strong>Notifications</strong>
                                <span>{{ count($headerNotificationItems) }} recent</span>
                            </div>
                        </div>
                        <div class="notification-menu-list">
                            @if (empty($headerNotificationItems))
                                <div class="notification-empty-state">No new leave updates right now.</div>
                            @else
                                @foreach ($headerNotificationItems as $item)
                                    <a href="{{ $item['href'] ?? '#' }}" class="notification-item notification-tone-{{ $item['tone'] ?? 'info' }}">
                                        <span class="notification-item-dot"></span>
                                        <span class="notification-item-body">
                                            <span class="notification-item-title">{{ $item['title'] ?? 'Notification' }}</span>
                                            <span class="notification-item-message">{{ $item['message'] ?? '' }}</span>
                                            <span class="notification-item-time">{{ $item['when_text'] ?? '' }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
                <div class="profile-section">
                    <div class="profile-info">
                        <div class="profile-name">{{ $displayName }}</div>
                        <div class="profile-email">{{ auth()->user()->email }}</div>
                    </div>
                    <button class="profile-button" id="profileButton" onclick="toggleProfileMenu()" type="button" aria-label="Open profile menu">
                        @if ($profilePicUrl)
                            <img src="{{ $profilePicUrl }}" alt="Profile" class="header-profile-avatar">
                        @else
                            <div class="header-profile-avatar-placeholder">
                                <span>{{ strtoupper(substr($displayName, 0, 1)) }}</span>
                            </div>
                        @endif
                    </button>
                    <div id="profileMenu" class="profile-menu" style="display: none;">
                        @if ($userRole !== 'admin')
                            <a href="{{ route('employee-profile', $profileRouteParams) }}" class="profile-menu-item">Profile</a>
                        @endif
                        <a href="{{ route('change-password') }}" class="profile-menu-item">Settings</a>
                        <hr style="margin: 4px 0; border: none; border-top: 1px solid var(--border);">
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="profile-menu-item" style="width:100%;text-align:left;border:0;background:transparent;cursor:pointer;">Logout</button>
                        </form>
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
    return 'leave_notifications_seen_' + {{ (int) (auth()->id() ?? 0) }};
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

window.addEventListener('pageshow', function (event) {
    var navEntries = (window.performance && performance.getEntriesByType) ? performance.getEntriesByType('navigation') : [];
    var navType = navEntries.length ? navEntries[0].type : '';
    if (event.persisted || navType === 'back_forward') {
        window.location.reload();
    }
});

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
