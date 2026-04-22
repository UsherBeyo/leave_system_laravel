@extends('layouts.app')
@section('title', 'Dashboard - Leave System')
@php
    $roleLabel = ucfirst(str_replace('_', ' ', (string) $role));
    $displayName = auth()->user()->employee?->fullName() ?: auth()->user()->email;
    $actions = [];
    if (in_array($role, ['department_head','personnel','manager','hr','admin'], true)) {
        $actions[] = '<a href="'.route('leave.requests').'" class="btn btn-secondary">Open Leave Requests</a>';
    }
    if ($role === 'employee') {
        $actions[] = '<a href="'.route('leave.apply').'" class="btn btn-primary">Apply Leave</a>';
    }
    $badge = function($status, $workflow = null) {
        $status = strtolower((string) $status);
        $workflow = strtolower((string) $workflow);
        if ($workflow === 'pending_personnel' || $workflow === 'pending_department_head' || $workflow === 'returned_by_personnel') return 'badge badge-pending';
        if ($workflow === 'finalized' || $status === 'approved') return 'badge badge-approved';
        if (str_contains($workflow, 'rejected') || $status === 'rejected') return 'badge badge-rejected';
        return 'badge badge-pending';
    };
@endphp
@section('content')
    @include('partials.page-header', ['title' => 'Dashboard', 'subtitle' => 'Welcome back, '.$displayName, 'actions' => $actions])

    <div class="ui-card">
        <h3 style="margin-top:0;">{{ match($role) {
            'employee' => 'Your leave overview is ready.',
            'department_head' => 'Department approvals at a glance.',
            'personnel' => 'Personnel review and print queue overview.',
            'manager' => 'Manager review dashboard.',
            'hr' => 'HR operational overview.',
            'admin' => 'System-wide control center.',
            default => 'Dashboard overview.'
        } }}</h3>
        <p>{{ match($role) {
            'employee' => 'Track your balances, monitor pending requests, and keep an eye on upcoming approved leaves from one place.',
            'department_head' => 'See what needs your decision, monitor upcoming team leaves, and keep your department workflow moving.',
            'personnel' => 'Review final-stage requests, monitor the print queue, and watch the approved leave pipeline in real time.',
            'manager' => 'Review pending leave requests and monitor leave trends across your assigned team.',
            'hr' => 'Monitor overall leave demand, department distribution, and pending review workload.',
            'admin' => 'See the system health, employee distribution, and leave operations in one consolidated dashboard.',
            default => 'Your dashboard is ready.'
        } }}</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
            <span class="badge badge-approved">Today: {{ now()->format('F d, Y') }}</span>
            <span class="badge badge-pending">Role: {{ $roleLabel }}</span>
            @if(auth()->user()->employee?->department)
                <span class="badge" style="background:#e8f0ff;color:#2563eb;">Department: {{ auth()->user()->employee->department }}</span>
            @endif
        </div>
    </div>

    @if($employeeDashboard)
        <div class="metric-grid">
            <div class="metric-card"><div class="metric-label">Annual Balance</div><div class="metric-value">{{ number_format($employeeDashboard['annual'],3) }}</div><div class="metric-sub">Your current annual leave balance</div></div>
            <div class="metric-card"><div class="metric-label">Sick Balance</div><div class="metric-value">{{ number_format($employeeDashboard['sick'],3) }}</div><div class="metric-sub">Your current sick leave balance</div></div>
            <div class="metric-card"><div class="metric-label">Force Balance</div><div class="metric-value">{{ number_format($employeeDashboard['force'],3) }}</div><div class="metric-sub">Your current force leave balance</div></div>
            <div class="metric-card"><div class="metric-label">Pending Requests</div><div class="metric-value">{{ $employeeDashboard['pending_count'] }}</div><div class="metric-sub">Requests still in workflow</div></div>
        </div>

        <div class="metric-grid">
            <div class="metric-card"><div class="metric-label">Annual Used This Month</div><div class="metric-value">{{ number_format($employeeDashboard['annual_used_this_month'],3) }}</div></div>
            <div class="metric-card"><div class="metric-label">Sick Used This Month</div><div class="metric-value">{{ number_format($employeeDashboard['sick_used_this_month'],3) }}</div></div>
            <div class="metric-card"><div class="metric-label">Force Used This Year</div><div class="metric-value">{{ number_format($employeeDashboard['force_used_this_year'],3) }}</div></div>
            <div class="metric-card"><div class="metric-label">Approved This Month</div><div class="metric-value">{{ $employeeDashboard['approved_this_month'] }}</div></div>
        </div>

        <div class="content-grid" style="grid-template-columns:2fr 1fr;">
            <div class="ui-card table-card">
                <div class="page-header" style="margin-bottom:16px;">
                    <div class="page-title-group"><h3 class="mt-0 mb-0">Recent Requests</h3><p class="page-subtitle">Your latest submitted leave requests</p></div>
                </div>
                <div class="table-wrap">
                    <table class="clean-table">
                        <thead>
                        <tr><th>Leave Type</th><th>Dates</th><th>Total Days</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        @forelse($employeeDashboard['recent_requests'] as $row)
                            <tr>
                                <td>{{ $row->leave_type_name }}</td>
                                <td>{{ optional($row->start_date)->format('M d, Y') }} - {{ optional($row->end_date)->format('M d, Y') }}</td>
                                <td>{{ number_format((float)$row->total_days,3) }}</td>
                                <td><span class="{{ $badge($row->status,$row->workflow_status) }}">{{ ucfirst($row->workflow_status ?: $row->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No leave requests yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card">
                <h3 style="margin-top:0;">Pending Snapshot</h3>
                @if(empty($employeeDashboard['pending_requests']))
                    <div class="help-text">You have no pending requests right now.</div>
                @else
                    <div style="display:grid;gap:12px;">
                        @foreach($employeeDashboard['pending_requests'] as $row)
                            <div style="padding:14px 16px;border:1px solid var(--border);border-radius:14px;background:linear-gradient(180deg,#fff,#fcfdff);">
                                <strong>{{ $row->leave_type_name }}</strong>
                                <div class="help-text" style="margin-top:6px;">{{ optional($row->start_date)->format('M d, Y') }} - {{ optional($row->end_date)->format('M d, Y') }} · {{ number_format((float)$row->total_days,3) }} day(s)</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($departmentHeadDashboard)
        <div class="metric-grid">
            <div class="metric-card"><div class="metric-label">Pending Department Head Review</div><div class="metric-value">{{ $departmentHeadDashboard['pending_count'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Approved This Month</div><div class="metric-value">{{ $departmentHeadDashboard['approved_this_month'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Returned / Rejected</div><div class="metric-value">{{ $departmentHeadDashboard['returned_count'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Upcoming Requests</div><div class="metric-value">{{ $departmentHeadDashboard['upcoming_count'] }}</div></div>
        </div>
    @endif

    @if($personnelDashboard)
        <div class="metric-grid">
            <div class="metric-card"><div class="metric-label">Pending Personnel Review</div><div class="metric-value">{{ $personnelDashboard['pending_personnel_count'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Finalized / Approved</div><div class="metric-value">{{ $personnelDashboard['approved_count'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Rejected</div><div class="metric-value">{{ $personnelDashboard['rejected_count'] }}</div></div>
            <div class="metric-card"><div class="metric-label">Pending Print</div><div class="metric-value">{{ $personnelDashboard['pending_print_count'] }}</div></div>
        </div>
    @endif
@endsection
