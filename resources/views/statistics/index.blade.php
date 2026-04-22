@extends('layouts.app')
@section('title', 'Statistics - Leave System')

@section('content')
@include('partials.page-header', [
    'title' => 'System Statistics',
    'subtitle' => 'System and department-wide analytics based on the current records.',
])

<div class="metric-grid">
    <div class="metric-card"><div class="metric-label">Total Employees</div><div class="metric-value">{{ number_format($totalEmployees) }}</div></div>
    <div class="metric-card"><div class="metric-label">Active User Accounts</div><div class="metric-value">{{ number_format($activeUsers) }}</div></div>
    <div class="metric-card"><div class="metric-label">Inactive User Accounts</div><div class="metric-value">{{ number_format($inactiveUsers) }}</div></div>
    <div class="metric-card"><div class="metric-label">Average Vacational Balance</div><div class="metric-value">{{ number_format($averageAnnual, 3) }}</div></div>
</div>

<div class="section-card" style="margin-bottom:20px;">
    <div class="page-header" style="margin-bottom:12px;">
        <div class="page-title-group"><h2 class="page-title" style="font-size:24px;">Employees by Department</h2><p class="page-subtitle">Count of employee records grouped by department.</p></div>
    </div>
    <div class="table-wrap">
        <table class="clean-table">
            <thead><tr><th>Department</th><th>Count</th></tr></thead>
            <tbody>
                @forelse($departmentStats as $row)
                    <tr><td>{{ $row->department_name }}</td><td>{{ number_format($row->count) }}</td></tr>
                @empty
                    <tr><td colspan="2">No department statistics available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section-card">
    <div class="page-header" style="margin-bottom:12px;">
        <div class="page-title-group"><h2 class="page-title" style="font-size:24px;">Users by Role</h2><p class="page-subtitle">Count of user accounts grouped by application role.</p></div>
    </div>
    <div class="table-wrap">
        <table class="clean-table">
            <thead><tr><th>Role</th><th>Count</th></tr></thead>
            <tbody>
                @forelse($roleStats as $row)
                    <tr><td>{{ ucfirst(str_replace('_',' ', (string) $row->role)) }}</td><td>{{ number_format($row->count) }}</td></tr>
                @empty
                    <tr><td colspan="2">No role statistics available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
