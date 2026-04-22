@extends('layouts.app')
@section('title', 'Reports - Leave System')
@php
    $actions = [];
    if ($reportType === 'leave_card' && $selectedEmployee) {
        $actions[] = '<a href="'.route('reports', array_filter(['type' => 'leave_card', 'dept' => $departmentFilter, 'employee_id' => $selectedEmployee->id, 'export' => 'csv'])).'" class="btn btn-secondary">Export Leave Card CSV</a>';
        $actions[] = '<a href="'.route('employee-profile', ['employee' => $selectedEmployee->id]).'" class="btn btn-ghost">Open Employee Profile</a>';
    } elseif (in_array($reportType, ['balance','usage'], true)) {
        $actions[] = '<a href="'.route('reports', array_filter(['type' => $reportType, 'dept' => $departmentFilter, 'export' => 'csv'])).'" class="btn btn-secondary">Export CSV</a>';
    }
@endphp
@push('head')
<style>
.report-shell{display:flex;flex-direction:column;gap:18px}.report-filter-card{padding:20px 22px}.report-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end}.report-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.report-value{font-size:28px;font-weight:800;color:var(--text)}.report-table-actions{display:flex;gap:8px;flex-wrap:wrap}.report-empty{padding:36px;border:1px dashed var(--border);background:#fff;border-radius:18px;text-align:center;color:var(--muted)}
.ledger-wrap{overflow:auto}.ledger-wrap table{min-width:980px}.summary-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;font-weight:700}
</style>
@endpush
@section('content')
@include('partials.page-header', ['title' => 'Reports', 'subtitle' => 'Pure Laravel reports built from the capstone report flow.', 'actions' => $actions])

<div class="report-shell">
    <div class="ui-card report-filter-card">
        <form method="GET" action="{{ route('reports') }}" class="report-filter-grid">
            <div class="field">
                <label>Report Type</label>
                <select name="type">
                    <option value="summary" @selected($reportType === 'summary')>Summary</option>
                    <option value="balance" @selected($reportType === 'balance')>Leave Balance</option>
                    <option value="usage" @selected($reportType === 'usage')>Leave Usage</option>
                    <option value="leave_card" @selected($reportType === 'leave_card')>Leave Card</option>
                </select>
            </div>
            @if($reportType !== 'leave_card')
                <div class="field">
                    <label>Department</label>
                    <select name="dept">
                        <option value="">All Departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department }}" @selected($departmentFilter === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div class="field">
                    <label>Employee</label>
                    <select name="employee_id">
                        <option value="">-- select --</option>
                        @foreach($employees as $employeeRow)
                            <option value="{{ $employeeRow->id }}" @selected(optional($selectedEmployee)->id === $employeeRow->id)>{{ $employeeRow->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </div>
        </form>
    </div>

    <div class="report-summary-grid">
        <div class="metric-card"><div class="metric-label">Total Employees</div><div class="report-value">{{ $summary['totalEmployees'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Pending Requests</div><div class="report-value">{{ $summary['pendingRequests'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved Requests</div><div class="report-value">{{ $summary['approvedRequests'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Average Vacational Balance</div><div class="report-value">{{ number_format((float)$summary['avgAnnualBalance'], 3) }}</div></div>
    </div>

    @if($reportType === 'summary')
        <div class="ui-card">
            <div class="page-header" style="margin-bottom:14px;">
                <div class="page-title-group"><h3 class="mt-0 mb-0">System Summary</h3><p class="page-subtitle">Mirrors the main capstone metrics your department expects.</p></div>
            </div>
            <div class="table-wrap">
                <table class="clean-table">
                    <thead><tr><th>Metric</th><th>Value</th></tr></thead>
                    <tbody>
                    <tr><td>Total Employees</td><td><span class="summary-pill">{{ $summary['totalEmployees'] }}</span></td></tr>
                    <tr><td>Pending Requests</td><td><span class="summary-pill">{{ $summary['pendingRequests'] }}</span></td></tr>
                    <tr><td>Approved Requests</td><td><span class="summary-pill">{{ $summary['approvedRequests'] }}</span></td></tr>
                    <tr><td>Average Vacational Balance</td><td><span class="summary-pill">{{ number_format((float)$summary['avgAnnualBalance'], 3) }} days</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($reportType === 'balance')
        <div class="ui-card table-card">
            <div class="page-header" style="margin-bottom:16px;">
                <div class="page-title-group"><h3 class="mt-0 mb-0">Leave Balance Report</h3><p class="page-subtitle">Employee balances scoped to the allowed departments for this account.</p></div>
            </div>
            <div class="table-wrap">
                <table class="clean-table">
                    <thead><tr><th>Name</th><th>Department</th><th>Vacational</th><th>Sick</th><th>Force</th><th>Actions</th></tr></thead>
                    <tbody>
                    @forelse($reportData as $row)
                        <tr>
                            <td>{{ $row->fullName() }}</td>
                            <td>{{ $row->department ?: '—' }}</td>
                            <td>{{ number_format((float)$row->annual_balance, 3) }}</td>
                            <td>{{ number_format((float)$row->sick_balance, 3) }}</td>
                            <td>{{ number_format((float)$row->force_balance, 3) }}</td>
                            <td>
                                <div class="report-table-actions">
                                    <a href="{{ route('employee-profile', ['employee' => $row->id]) }}" class="btn btn-secondary">Profile</a>
                                    <a href="{{ route('reports', ['type' => 'leave_card', 'employee_id' => $row->id]) }}" class="btn btn-ghost">Leave Card</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No employees found for the selected scope.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($reportType === 'usage')
        <div class="ui-card table-card">
            <div class="page-header" style="margin-bottom:16px;">
                <div class="page-title-group"><h3 class="mt-0 mb-0">Leave Usage Report</h3><p class="page-subtitle">Approved leave requests grouped by department and leave type.</p></div>
            </div>
            <div class="table-wrap">
                <table class="clean-table">
                    <thead><tr><th>Department</th><th>Leave Type</th><th>Request Count</th><th>Total Days</th></tr></thead>
                    <tbody>
                    @forelse($reportData as $row)
                        <tr>
                            <td>{{ $row['department'] }}</td>
                            <td>{{ $row['leave_type'] }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>{{ number_format((float)$row['total_days'], 3) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">No approved leave usage found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($reportType === 'leave_card')
        @if(!$selectedEmployee)
            <div class="report-empty">Select an employee first to open the Leave Card report.</div>
        @else
            <div class="ui-card table-card">
                <div class="page-header" style="margin-bottom:16px;">
                    <div class="page-title-group"><h3 class="mt-0 mb-0">Leave Card - {{ $selectedEmployee->fullName() }}</h3><p class="page-subtitle">Complete transaction history combining leave requests and balance history.</p></div>
                </div>
                <div class="ledger-wrap">
                    <table class="clean-table">
                        <thead><tr><th>Date</th><th>Particulars</th><th>Vac Earned</th><th>Vac Deducted</th><th>Vac Balance</th><th>Sick Earned</th><th>Sick Deducted</th><th>Sick Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($reportData as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['particulars'] }}</td>
                                <td>{{ ($row['vac_earned'] ?? 0) != 0 ? number_format((float)$row['vac_earned'],3) : '' }}</td>
                                <td>{{ ($row['vac_deducted'] ?? 0) != 0 ? number_format((float)$row['vac_deducted'],3) : '' }}</td>
                                <td>{{ $row['vac_balance'] === '' ? '' : number_format((float)$row['vac_balance'],3) }}</td>
                                <td>{{ ($row['sick_earned'] ?? 0) != 0 ? number_format((float)$row['sick_earned'],3) : '' }}</td>
                                <td>{{ ($row['sick_deducted'] ?? 0) != 0 ? number_format((float)$row['sick_deducted'],3) : '' }}</td>
                                <td>{{ $row['sick_balance'] === '' ? '' : number_format((float)$row['sick_balance'],3) }}</td>
                                <td>{{ $row['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No leave card history found for this employee.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
