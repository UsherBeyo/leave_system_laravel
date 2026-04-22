@php
    $role = auth()->user()->role;
    $currentRoute = optional(request()->route())->getName();
    $sidebarNotificationCounts = $sidebarNotificationCounts ?? ['leave_requests' => 0, 'apply_leave' => 0];
@endphp
<aside class="sidebar">
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="sidebar-link-icon">
                <svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 5a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V9zm0 5a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2z"/></svg>
            </span>
            <span>Dashboard</span>
        </a>

        @if(in_array($role,['admin','manager','department_head','hr','personnel'], true))
            <a href="{{ route('leave.requests') }}" class="sidebar-link {{ request()->routeIs('leave.requests') ? 'active' : '' }}">
                <span class="sidebar-link-icon">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4z" clip-rule="evenodd"/></svg>
                </span>
                <span>Leave Requests</span>
                @if(!empty($sidebarNotificationCounts['leave_requests']))<span class="sidebar-badge">{{ (int) $sidebarNotificationCounts['leave_requests'] }}</span>@endif
            </a>
        @endif

        @if(in_array($role,['employee','manager','department_head'], true))
            <a href="{{ route('leave.apply') }}" class="sidebar-link {{ request()->routeIs('leave.apply') ? 'active' : '' }}">
                <span class="sidebar-link-icon">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.3A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"/></svg>
                </span>
                <span>Apply Leave</span>
            </a>
        @endif

        @if(in_array($role,['employee','manager','department_head','hr','personnel','admin'], true))
            <a href="{{ route('calendar') }}" class="sidebar-link {{ request()->routeIs('calendar') ? 'active' : '' }}">
                <span class="sidebar-link-icon">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a2 2 0 012-2h8a2 2 0 012 2v9a2 2 0 01-2 2H8a2 2 0 01-2-2V7z" clip-rule="evenodd"/></svg>
                </span>
                <span>Calendar</span>
            </a>
        @endif

        @if(in_array($role,['admin','hr','personnel','department_head','manager','employee'], true))
            <a href="{{ route('reports') }}" class="sidebar-link {{ request()->routeIs('reports') ? 'active' : '' }}">
                <span class="sidebar-link-icon">
                    <svg fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                </span>
                <span>Reports</span>
            </a>
        @endif

        @if($role === 'admin' || in_array($role,['hr','personnel'], true))
            <div class="sidebar-section">
                <div class="sidebar-section-label">Management</div>

                @if($role === 'admin')
                    <a href="{{ route('manage-employees') }}" class="sidebar-link {{ request()->routeIs('manage-employees') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                        </span>
                        <span>Manage Employees</span>
                    </a>
                    <a href="{{ route('manage-departments') }}" class="sidebar-link {{ request()->routeIs('manage-departments') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                        </span>
                        <span>Departments</span>
                    </a>
                @endif

                <a href="{{ route('holidays') }}" class="sidebar-link {{ request()->routeIs('holidays') ? 'active' : '' }}">
                    <span class="sidebar-link-icon">
                        <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    </span>
                    <span>Holidays</span>
                </a>

                @if($role === 'admin')
                    <a href="{{ route('manage-accruals') }}" class="sidebar-link {{ request()->routeIs('manage-accruals') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path d="M8.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM12.5 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM16 10a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
                        </span>
                        <span>Accruals</span>
                    </a>
                    <a href="{{ route('manage-leave-types') }}" class="sidebar-link {{ request()->routeIs('manage-leave-types') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4l4 4h4a2 2 0 012 2v4a2 2 0 01-2 2h-4l-4-4H6a2 2 0 01-2-2V4z"/></svg>
                        </span>
                        <span>Leave Types</span>
                    </a>
                    <a href="{{ route('statistics') }}" class="sidebar-link {{ request()->routeIs('statistics') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 001 1h12a1 1 0 100-2H4a1 1 0 00-1 1zm2-3a1 1 0 011-1h1a1 1 0 011 1v1H5v-1zm4-4a1 1 0 011-1h1a1 1 0 011 1v5H9v-5zm4-3a1 1 0 011-1h1a1 1 0 011 1v8h-2V7z"/></svg>
                        </span>
                        <span>Statistics</span>
                    </a>
                @endif

                @if(in_array($role,['personnel','admin','hr'], true))
                    <a href="{{ route('signatories-settings') }}" class="sidebar-link {{ request()->routeIs('signatories-settings') ? 'active' : '' }}">
                        <span class="sidebar-link-icon">
                            <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                        </span>
                        <span>Settings</span>
                    </a>
                @endif
            </div>
        @endif
    </nav>
</aside>
