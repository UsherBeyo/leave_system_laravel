@extends('layouts.app')

@section('title', 'Leave Calendar - Leave System')

@php
    $prevMonth = $month === 1 ? 12 : $month - 1;
    $prevYear = $month === 1 ? $year - 1 : $year;
    $nextMonth = $month === 12 ? 1 : $month + 1;
    $nextYear = $month === 12 ? $year + 1 : $year;
    $todayMonth = now()->month;
    $todayYear = now()->year;
@endphp

@section('content')
    @include('partials.page-header', [
        'title' => 'Leave Calendar',
        'subtitle' => 'View all scheduled leaves and holidays',
        'actions' => [
            '<a href="'.route('calendar', ['m' => $prevMonth, 'y' => $prevYear]).'" class="btn btn-ghost">&lt; Prev</a>',
            '<a href="'.route('calendar', ['m' => $todayMonth, 'y' => $todayYear]).'" class="btn btn-secondary">Today</a>',
            '<a href="'.route('calendar', ['m' => $nextMonth, 'y' => $nextYear]).'" class="btn btn-ghost">Next &gt;</a>',
        ],
    ])

    <div class="calendar-shell">
        <div class="calendar-board">
            <div class="ui-card calendar-card">
                <div class="calendar-headline">
                    <div class="calendar-toolbar">
                        <div>
                            <div class="calendar-month-chip">
                                <span>{{ $monthLabel }}</span>
                                <small>{{ $daysWithEvents }} active day{{ $daysWithEvents === 1 ? '' : 's' }}</small>
                            </div>
                            <div class="calendar-note">Click a date with events to view full details.</div>
                        </div>
                        <form method="GET" action="{{ route('calendar') }}" class="calendar-jump-form">
                            <div class="calendar-jump-field">
                                <label class="calendar-jump-label" for="calendar-month-select">Month</label>
                                <select id="calendar-month-select" name="m" class="calendar-jump-select">
                                    @for($monthOption = 1; $monthOption <= 12; $monthOption++)
                                        <option value="{{ $monthOption }}" @selected($monthOption === $month)>{{ \Carbon\Carbon::create($year, $monthOption, 1)->format('F') }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="calendar-jump-field">
                                <label class="calendar-jump-label" for="calendar-year-select">Year</label>
                                <select id="calendar-year-select" name="y" class="calendar-jump-select">
                                    @for($yearOption = max(2000, $year - 5); $yearOption <= min(2100, $year + 5); $yearOption++)
                                        <option value="{{ $yearOption }}" @selected($yearOption === $year)>{{ $yearOption }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="calendar-jump-actions">
                                <button type="submit" class="btn btn-primary">Jump</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="calendar-grid">
                        <tr>
                            <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
                        </tr>
                        @php $day = 1; $dow = 1; @endphp
                        <tr>
                            @for($i = 1; $i < $firstDow; $i++)
                                <td class="is-empty"></td>
                                @php $dow++; @endphp
                            @endfor
                            @while($day <= $daysInMonth)
                                @if($dow > 7)
                                    </tr><tr>
                                    @php $dow = 1; @endphp
                                @endif
                                @php
                                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $dayEvents = $events[$date] ?? [];
                                    $holidayCount = collect($dayEvents)->where('type', 'holiday')->count();
                                    $approvedCount = collect($dayEvents)->where('status', 'approved')->count();
                                    $pendingCount = collect($dayEvents)->where('status', 'pending')->count();
                                    $classes = ['calendar-day'];
                                    if (!empty($dayEvents)) $classes[] = 'has-events';
                                    if ($holidayCount > 0) $classes[] = 'has-holiday';
                                    if ($approvedCount > 0) $classes[] = 'has-approved';
                                    if ($pendingCount > 0) $classes[] = 'has-pending';
                                    if ($date === $today->toDateString()) $classes[] = 'is-today';
                                @endphp
                                <td class="{{ implode(' ', $classes) }}" @if(!empty($dayEvents)) data-date="{{ $date }}" data-human-date="{{ \Carbon\Carbon::parse($date)->format('F j, Y') }}" @endif>
                                    <div class="day-header">
                                        <span class="day-number">{{ $day }}</span>
                                        @if($date === $today->toDateString())<span class="today-badge">Today</span>@endif
                                    </div>
                                    <div class="day-pill-row">
                                        @if($holidayCount > 0)<span class="day-pill holiday">Holiday{{ $holidayCount > 1 ? ' × '.$holidayCount : '' }}</span>@endif
                                        @if($approvedCount > 0)<span class="day-pill approved">Approved{{ $approvedCount > 1 ? ' × '.$approvedCount : '' }}</span>@endif
                                        @if($pendingCount > 0)<span class="day-pill pending">Pending{{ $pendingCount > 1 ? ' × '.$pendingCount : '' }}</span>@endif
                                    </div>
                                </td>
                                @php $day++; $dow++; @endphp
                            @endwhile
                            @while($dow <= 7)
                                <td class="is-empty"></td>
                                @php $dow++; @endphp
                            @endwhile
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="calendar-sidebar-stack">
            <div class="ui-card calendar-summary-card">
                <div class="summary-kicker">Legend</div>
                <h4>Calendar Colors</h4>
                <div class="legend-grid">
                    <div class="legend-item"><div class="legend-left"><span class="legend-dot holiday"></span><span>Holiday</span></div><span class="legend-text">Red chip</span></div>
                    <div class="legend-item"><div class="legend-left"><span class="legend-dot approved"></span><span>Approved Leave</span></div><span class="legend-text">Green chip</span></div>
                    <div class="legend-item"><div class="legend-left"><span class="legend-dot pending"></span><span>Pending Leave</span></div><span class="legend-text">Yellow chip</span></div>
                    <div class="legend-item"><div class="legend-left"><span class="legend-dot today"></span><span>Today</span></div><span class="legend-text">Blue outline</span></div>
                </div>
            </div>

            <div class="ui-card calendar-summary-card calendar-insight-card">
                <div class="summary-kicker">Quick View</div>
                <h4>Open Calendar Details</h4>
                <div class="calendar-action-grid">
                    <button type="button" class="calendar-modal-trigger" data-modal-target="upcomingLeavesModal">
                        <div class="calendar-trigger-copy">
                            <div class="calendar-trigger-title">Upcoming Leaves</div>
                            <div class="calendar-trigger-sub">Review the next approved and pending leave requests.</div>
                        </div>
                        <span class="calendar-trigger-count">{{ $upcomingLeaves->count() }}</span>
                    </button>
                    <button type="button" class="calendar-modal-trigger" data-modal-target="upcomingEventsModal">
                        <div class="calendar-trigger-copy">
                            <div class="calendar-trigger-title">Upcoming Events</div>
                            <div class="calendar-trigger-sub">See the next holidays and non-working dates.</div>
                        </div>
                        <span class="calendar-trigger-count">{{ $upcomingEvents->count() }}</span>
                    </button>
                    @if($showSnapshotDetails)
                        <button type="button" class="calendar-modal-trigger" data-modal-target="snapshotModal">
                            <div class="calendar-trigger-copy">
                                <div class="calendar-trigger-title">Snapshot</div>
                                <div class="calendar-trigger-sub">Get a quick monthly summary before planning ahead.</div>
                            </div>
                            <span class="calendar-trigger-count">{{ $totalMonthRequests }}</span>
                        </button>
                    @endif
                </div>
                <div class="calendar-overview-note">These open in focused modals so you can review details without stretching the page downward.</div>
            </div>
        </div>
    </div>

    <div id="upcomingLeavesModal" class="modal calendar-detail-modal" style="display:none;">
        <div class="modal-content calendar-detail-shell">
            <button type="button" class="modal-close" data-close-modal="upcomingLeavesModal" aria-label="Close">&times;</button>
            <div class="calendar-detail-header">
                <div class="calendar-detail-kicker">Calendar Detail</div>
                <h3 class="calendar-detail-title">Upcoming Leaves</h3>
                <p class="calendar-detail-subtitle">A focused list of the next approved and pending leave requests.</p>
            </div>
            <div class="calendar-detail-body">
                @forelse($upcomingLeaves as $leave)
                    @php $status = strtolower(trim((string) $leave->status)); @endphp
                    <div class="summary-item">
                        <div class="summary-item-header">
                            <div>
                                <div class="summary-item-title">{{ $leave->employee?->fullName() }}</div>
                                <div class="summary-item-sub">{{ $leave->leave_type_name }}</div>
                            </div>
                            <span class="summary-badge {{ $status === 'approved' ? 'approved' : 'pending' }}">{{ ucfirst($status) }}</span>
                        </div>
                        <div class="summary-item-meta">{{ optional($leave->start_date)->format('F j, Y') }} - {{ optional($leave->end_date)->format('F j, Y') }}@if($leave->total_days) • {{ number_format((float) $leave->total_days, 3) }} day(s) @endif</div>
                    </div>
                @empty
                    <div class="empty-state">No upcoming leave requests found.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div id="upcomingEventsModal" class="modal calendar-detail-modal" style="display:none;">
        <div class="modal-content calendar-detail-shell">
            <button type="button" class="modal-close" data-close-modal="upcomingEventsModal" aria-label="Close">&times;</button>
            <div class="calendar-detail-header">
                <div class="calendar-detail-kicker">Calendar Detail</div>
                <h3 class="calendar-detail-title">Upcoming Events</h3>
                <p class="calendar-detail-subtitle">All upcoming holidays in an easier-to-scan modal list.</p>
            </div>
            <div class="calendar-detail-body">
                @forelse($upcomingEvents as $event)
                    <div class="summary-item">
                        <div class="summary-item-header">
                            <div>
                                <div class="summary-item-title">{{ $event->description ?: 'Holiday' }}</div>
                                <div class="summary-item-sub">{{ $event->type ?: 'Holiday' }}</div>
                            </div>
                            <span class="summary-badge holiday">Holiday</span>
                        </div>
                        <div class="summary-item-meta">{{ optional($event->holiday_date)->format('F j, Y') }}</div>
                    </div>
                @empty
                    <div class="empty-state">No upcoming holidays found.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if($showSnapshotDetails)
        <div id="snapshotModal" class="modal calendar-detail-modal" style="display:none;">
            <div class="modal-content calendar-detail-shell">
                <button type="button" class="modal-close" data-close-modal="snapshotModal" aria-label="Close">&times;</button>
                <div class="calendar-detail-header">
                    <div class="calendar-detail-kicker">Calendar Detail</div>
                    <h3 class="calendar-detail-title">This Month Snapshot</h3>
                    <p class="calendar-detail-subtitle">A clean summary of requests, approvals, pending items, and holiday dates for {{ $monthLabel }}.</p>
                </div>
                <div class="calendar-detail-body">
                    <div class="metric-grid" style="margin-bottom:0;">
                        <div class="metric-card"><div class="metric-label">Leave Requests</div><div class="metric-value">{{ $totalMonthRequests }}</div><div class="metric-sub">Requests visible in this month view.</div></div>
                        <div class="metric-card"><div class="metric-label">Approved</div><div class="metric-value" style="color:#15803d;">{{ $monthApprovedCount }}</div><div class="metric-sub">Approved leave requests in this month.</div></div>
                        <div class="metric-card"><div class="metric-label">Pending</div><div class="metric-value" style="color:#a16207;">{{ $monthPendingCount }}</div><div class="metric-sub">Pending leave requests still awaiting action.</div></div>
                        <div class="metric-card"><div class="metric-label">Holiday Dates</div><div class="metric-value" style="color:#b91c1c;">{{ $totalMonthHolidays }}</div><div class="metric-sub">Holiday dates stored for the selected month.</div></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div id="sidePanel" class="calendar-side-panel" aria-hidden="true">
        <button id="closeSidePanel" class="calendar-panel-close" type="button" aria-label="Close">×</button>
        <div id="panelContent"><div class="panel-empty">Select a calendar date with events to view full details.</div></div>
    </div>
@endsection

@push('head')
<style>
.calendar-shell{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:24px;align-items:start}.calendar-board{min-width:0}.calendar-card{overflow:hidden}.calendar-headline{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}.calendar-month-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:linear-gradient(135deg, rgba(37,99,235,0.10), rgba(34,197,94,0.10));border:1px solid rgba(37,99,235,0.15);color:var(--text);font-weight:600}.calendar-month-chip small{font-size:12px;color:var(--muted)}.calendar-grid{width:100%;border-collapse:separate;border-spacing:10px}.calendar-grid th{padding:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);text-align:left}.calendar-grid td{width:14.285%;min-width:110px;height:116px;padding:12px;vertical-align:top;border-radius:18px;border:1px solid var(--border);background:#fff;box-shadow:0 1px 3px rgba(15,23,42,0.05);transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;position:relative}.calendar-grid td.is-empty{background:transparent;border-style:dashed;box-shadow:none}.calendar-grid td[data-date]{cursor:pointer}.calendar-grid td[data-date]:hover{transform:translateY(-2px);box-shadow:0 12px 24px rgba(15,23,42,0.09);border-color:rgba(37, 99, 235, 0.25);background:#f8fbff}.calendar-grid td.has-events::after{content:'';position:absolute;inset:0;border-radius:18px;pointer-events:none;border:1px solid transparent}.calendar-grid td.has-holiday::after{border-color:rgba(239,68,68,0.18)}.calendar-grid td.has-approved::after{box-shadow:inset 0 0 0 1px rgba(34,197,94,0.10)}.calendar-grid td.has-pending::after{box-shadow:inset 0 0 0 1px rgba(234,179,8,0.12)}.calendar-grid td.is-today{background:linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);border-color:rgba(37,99,235,0.35)}.day-header{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}.day-number{font-size:18px;font-weight:700;color:var(--text);line-height:1}.today-badge{padding:4px 8px;border-radius:999px;background:rgba(37,99,235,0.12);color:var(--primary);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.day-pill-row{display:flex;flex-direction:column;gap:6px}.day-pill{display:inline-flex;align-items:center;gap:6px;width:fit-content;max-width:100%;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:600;white-space:nowrap}.day-pill::before{content:'';width:7px;height:7px;border-radius:50%;flex-shrink:0}.day-pill.holiday{background:#fef2f2;color:#b91c1c}.day-pill.approved{background:#f0fdf4;color:#15803d}.day-pill.pending{background:#fffbeb;color:#a16207}.day-pill.holiday::before,.legend-dot.holiday,.summary-badge.holiday::before{background:#ef4444}.day-pill.approved::before,.legend-dot.approved,.summary-badge.approved::before{background:#22c55e}.day-pill.pending::before,.legend-dot.pending,.summary-badge.pending::before{background:#eab308}.calendar-note{margin-top:10px;font-size:12px;color:var(--muted)}.calendar-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}.calendar-jump-form{display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 12px;border-radius:16px;border:1px solid var(--border);background:linear-gradient(180deg,#fff,#f8fafc);box-shadow:0 4px 12px rgba(15,23,42,0.04)}.calendar-jump-field{display:flex;flex-direction:column;gap:4px;min-width:132px}.calendar-jump-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}.calendar-jump-select{min-height:40px;border:1px solid var(--border);border-radius:12px;padding:9px 12px;background:#fff;color:var(--text);font-weight:600}.calendar-jump-actions{display:inline-flex;gap:8px;align-items:center;margin-left:4px;padding-top:16px}.calendar-insight-card{border-radius:20px;overflow:hidden;background:linear-gradient(135deg, rgba(37,99,235,0.06), rgba(16,185,129,0.04))}.calendar-action-grid{display:grid;grid-template-columns:1fr;gap:12px}.calendar-modal-trigger{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;padding:15px 16px;border-radius:18px;border:1px solid rgba(37,99,235,0.12);background:#fff;text-align:left;cursor:pointer;transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;box-shadow:0 2px 10px rgba(15,23,42,0.04)}.calendar-modal-trigger:hover{transform:translateY(-2px);color:#fff;background:#2563eb;box-shadow:0 12px 26px rgba(15,23,42,0.08);border-color:rgba(37,99,235,0.22)}.calendar-modal-trigger:hover .calendar-trigger-title,.calendar-modal-trigger:hover .calendar-trigger-sub{color:#fff}.calendar-modal-trigger:hover .calendar-trigger-count{background:linear-gradient(135deg, #2563eb, #d2f0e6);color:#fff}.calendar-trigger-copy{min-width:0}.calendar-trigger-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:4px}.calendar-trigger-sub{font-size:12px;color:var(--secondary-text)}.calendar-trigger-count{flex-shrink:0;min-width:58px;height:58px;border-radius:18px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#dbeafe,#eef2ff);color:var(--primary);font-size:22px;font-weight:800}.calendar-overview-note{margin-top:12px;font-size:12px;color:var(--muted)}.calendar-detail-modal .modal-content{width:min(760px,94vw);padding:0;border-radius:24px;overflow:hidden;box-shadow:0 20px 45px rgba(15,23,42,0.18)}.calendar-detail-shell{background:linear-gradient(180deg,#f8fbff 0%,#fff 22%)}.calendar-detail-header{position:relative;padding:24px 24px 18px;border-bottom:1px solid var(--border)}.calendar-detail-kicker{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:8px}.calendar-detail-title{font-size:26px;font-weight:800;color:var(--text);margin:0 0 8px;padding-right:52px}.calendar-detail-subtitle{font-size:14px;color:var(--secondary-text);margin:0}.calendar-detail-body{padding:22px 24px 24px;display:flex;flex-direction:column;gap:14px;max-height:70vh;overflow-y:auto}.calendar-detail-modal .modal-close{top:18px;right:18px;width:36px;height:36px;border-radius:12px;background:#fff;border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(15,23,42,0.08)}.calendar-sidebar-stack{display:flex;flex-direction:column;gap:16px}.calendar-summary-card{border-radius:20px;overflow:hidden}.calendar-summary-card h4{margin:0 0 14px}.summary-kicker{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:8px}.legend-grid{display:grid;gap:10px}.legend-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:#fff}.legend-left{display:inline-flex;align-items:center;gap:10px;color:var(--text);font-weight:600}.legend-dot{width:10px;height:10px;border-radius:50%;display:inline-block}.legend-dot.today{background:transparent;border:2px solid #2563eb}.legend-text{font-size:12px;color:var(--muted)}.summary-item{padding:14px;border-radius:16px;border:1px solid var(--border);background:#fff;box-shadow:0 1px 2px rgba(15,23,42,0.04)}.summary-item-header{display:flex;justify-content:space-between;gap:12px;align-items:start;margin-bottom:6px}.summary-item-title{color:var(--text);font-weight:700;line-height:1.3}.summary-item-sub{color:var(--secondary-text);font-size:13px}.summary-item-meta{color:var(--muted);font-size:12px;margin-top:6px}.summary-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;width:fit-content;flex-shrink:0}.summary-badge::before{content:'';width:7px;height:7px;border-radius:50%;display:block}.summary-badge.approved{background:#f0fdf4;color:#15803d}.summary-badge.pending{background:#fffbeb;color:#a16207}.summary-badge.holiday{background:#fef2f2;color:#b91c1c}.calendar-side-panel{position:fixed;top:88px;right:16px;left:auto !important;width:min(400px,calc(100vw - 32px));max-width:400px;height:calc(100vh - 104px);background:#fff;box-shadow:-12px 0 30px rgba(15,23,42,0.12);transform:translateX(calc(100% + 28px));opacity:0;visibility:hidden;pointer-events:none;transition:transform .22s ease,opacity .18s ease,visibility 0s linear .22s;z-index:2200;padding:24px 20px 20px;border-radius:24px 0 0 24px;overflow-y:auto;overflow-x:hidden}.calendar-side-panel.open{transform:translateX(0);opacity:1;visibility:visible;pointer-events:auto;transition:transform .22s ease,opacity .18s ease}.calendar-panel-close{position:absolute;top:16px;right:16px;border:0;background:#eef2ff;color:#1d4ed8;width:38px;height:38px;border-radius:12px;font-size:24px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;z-index:2}.panel-empty{color:var(--muted);padding-top:36px}.panel-kicker{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:6px}.panel-title{font-size:26px;font-weight:800;color:var(--text);margin:0 0 6px}.panel-subtitle{font-size:13px;color:var(--secondary-text);margin-bottom:18px}.panel-event{border:1px solid var(--border);border-radius:16px;padding:14px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,0.04);margin-bottom:12px}.panel-event-title{font-weight:700;color:var(--text);margin-bottom:4px}.panel-event-desc{font-size:13px;color:var(--secondary-text);margin-bottom:8px}.panel-event-meta{font-size:12px;color:var(--muted)}@media (max-width:1100px){.calendar-shell{grid-template-columns:1fr}.calendar-sidebar-stack{order:-1}}@media (max-width:720px){.calendar-grid{border-spacing:6px}.calendar-grid td{height:90px;padding:8px}.day-number{font-size:16px}.day-pill{font-size:10px;padding:4px 8px}.calendar-side-panel{top:76px;right:10px;width:calc(100vw - 20px);height:calc(100vh - 88px);border-radius:20px;transform:translateX(calc(100% + 20px))}}
</style>
@endpush

@push('scripts')
<script>
(function(){
    const events = @json($events);
    const sidePanel = document.getElementById('sidePanel');
    const panelContent = document.getElementById('panelContent');
    const closeSidePanel = document.getElementById('closeSidePanel');
    document.querySelectorAll('.calendar-grid td[data-date]').forEach(function(cell){
        cell.addEventListener('click', function(){
            const date = this.getAttribute('data-date');
            const humanDate = this.getAttribute('data-human-date');
            const dayEvents = events[date] || [];
            let html = '<div class="panel-kicker">Calendar Detail</div>';
            html += '<h3 class="panel-title">'+humanDate+'</h3>';
            html += '<div class="panel-subtitle">'+dayEvents.length+' item(s) scheduled for this date.</div>';
            dayEvents.forEach(function(item){
                const badgeClass = item.type === 'holiday' ? 'holiday' : (item.status === 'approved' ? 'approved' : 'pending');
                const badgeLabel = item.type === 'holiday' ? 'Holiday' : (item.status === 'approved' ? 'Approved Leave' : 'Pending Leave');
                html += '<div class="panel-event">';
                html += '<span class="summary-badge '+badgeClass+'">'+badgeLabel+'</span>';
                html += '<div class="panel-event-title">'+String(item.title || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))+'</div>';
                html += '<div class="panel-event-desc">'+String(item.desc || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))+'</div>';
                html += '<div class="panel-event-meta">'+String(item.meta || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))+'</div>';
                html += '</div>';
            });
            panelContent.innerHTML = html;
            sidePanel.classList.add('open');
            sidePanel.setAttribute('aria-hidden', 'false');
        });
    });
    if(closeSidePanel){ closeSidePanel.addEventListener('click', function(){ sidePanel.classList.remove('open'); sidePanel.setAttribute('aria-hidden', 'true'); }); }
    document.querySelectorAll('[data-modal-target]').forEach(function(button){
        button.addEventListener('click', function(){ const id = this.getAttribute('data-modal-target'); const modal = document.getElementById(id); if(modal) modal.style.display='flex'; });
    });
    document.querySelectorAll('[data-close-modal]').forEach(function(button){
        button.addEventListener('click', function(){ const id = this.getAttribute('data-close-modal'); const modal = document.getElementById(id); if(modal) modal.style.display='none'; });
    });
    document.querySelectorAll('.calendar-detail-modal').forEach(function(modal){
        modal.addEventListener('click', function(e){ if(e.target === modal){ modal.style.display='none'; } });
    });
})();
</script>
@endpush
