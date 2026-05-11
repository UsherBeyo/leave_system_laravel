@extends('layouts.app')
@section('title', 'Reports - Leave System')
@php
    $actions = [];
    $isEmployeeReport = ($role ?? null) === 'employee';
    if ($reportType === 'leave_card' && $selectedEmployee) {
        $exportParams = $isEmployeeReport ? ['type' => 'leave_card', 'export' => 'xlsx'] : ['type' => 'leave_card', 'dept' => $departmentFilter, 'employee_id' => $selectedEmployee->id, 'export' => 'xlsx'];
        $pdfExportParams = $isEmployeeReport ? ['type' => 'leave_card', 'export' => 'pdf'] : ['type' => 'leave_card', 'dept' => $departmentFilter, 'employee_id' => $selectedEmployee->id, 'export' => 'pdf'];
        $actions[] = '<a href="'.route('reports', array_filter($exportParams)).'" class="btn" style="background:#16a34a;color:#fff">Export Leave Card Excel</a>';
        $actions[] = '<a href="'.route('reports', array_filter($pdfExportParams)).'" class="btn" style="background:#dc2626;color:#fff">Export Leave Card PDF</a>';
        if (!$isEmployeeReport) {
            $actions[] = '<a href="'.route('employee-profile', ['employee' => $selectedEmployee->id]).'" class="btn btn-ghost">Open Employee Profile</a>';
        }
    } elseif (in_array($reportType, ['balance','usage'], true)) {
        $actions[] = '<a href="'.route('reports', array_filter(['type' => $reportType, 'dept' => $departmentFilter, 'export' => 'pdf'])).'" class="btn btn-secondary">Export PDF</a>';
        $actions[] = '<a href="'.route('reports', array_filter(['type' => $reportType, 'dept' => $departmentFilter, 'export' => 'csv'])).'" class="btn btn-secondary">Export CSV</a>';
    }
@endphp
@push('head')
<style>
.report-shell{display:flex;flex-direction:column;gap:18px}.report-filter-card{padding:20px 22px}.report-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end}.report-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.report-value{font-size:28px;font-weight:800;color:var(--text)}.report-table-actions{display:flex;gap:8px;flex-wrap:wrap}.report-empty{padding:36px;border:1px dashed var(--border);background:#fff;border-radius:18px;text-align:center;color:var(--muted)}
.ledger-wrap{overflow-x:auto;overflow-y:hidden}.ledger-wrap table{min-width:100%}.summary-pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:12px;font-weight:700}
.leave-card-container{margin-top:20px}
.leave-card-table{background:#fff;border:1px solid #e5e7eb;border-radius:20px;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,0.06)}
.leave-card-header{padding:24px;border-bottom:1px solid #e5e7eb;background:#f9fafb}
.leave-card-header h3{margin:0 0 6px 0;font-size:18px;font-weight:700;color:#111827}
.leave-card-header p{margin:0;font-size:14px;color:#6b7280}
.leave-card-table-wrap{width:100%;overflow-x:auto}
.leave-card-table-wrap table{width:100%;border-collapse:collapse;font-size:12px}
.leave-card-table-wrap thead th{background:#f3f4f6;padding:12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6b7280;border-bottom:1px solid #d1d5db;white-space:nowrap}
.leave-card-table-wrap tbody td{padding:12px;border-bottom:1px solid #e5e7eb;color:#374151;text-align:left}
.leave-card-table-wrap tbody tr:last-child td{border-bottom:none}
.leave-card-pagination{padding:20px 24px;background:#fff;border-top:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.leave-card-pagination-info{font-size:12px;color:#6b7280}
.leave-card-pagination-buttons{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.pagination-btn{padding:6px 12px;border-radius:999px;border:1px solid #d1d5db;background:#f3f4f6;color:#374151;font-size:12px;font-weight:500;cursor:pointer;transition:all 0.2s ease}
.pagination-btn:hover{background:#e5e7eb}
.pagination-btn.active{background:#1e40af;color:#fff;border-color:#1e40af}
.pagination-btn.nav-btn{background:#3b82f6;color:#fff;border-color:#3b82f6}
.pagination-btn.nav-btn:disabled{background:#d1d5db;color:#9ca3af;border-color:#d1d5db;cursor:not-allowed}
.pagination-number-btn{padding:6px 10px;min-width:32px;text-align:center}
</style>
@endpush
@section('content')
@include('partials.page-header', ['title' => $isEmployeeReport ? 'Leave Card' : 'Reports', 'subtitle' => $isEmployeeReport ? 'View your own leave card only.' : 'Pure Laravel reports built from the capstone report flow.', 'actions' => $actions])

<div class="report-shell">
    @if(!$isEmployeeReport)
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
                <button style="padding: 8px 12px; margin-bottom: 20px;" type="submit" class="btn btn-primary">Apply Filter</button>
            </div>
        </form>
    </div>
    @endif

    @if(!$isEmployeeReport)
    <div class="report-summary-grid">
        <div class="metric-card"><div class="metric-label">Total Employees</div><div class="report-value">{{ $summary['totalEmployees'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Pending Requests</div><div class="report-value">{{ $summary['pendingRequests'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Approved Requests</div><div class="report-value">{{ $summary['approvedRequests'] }}</div></div>
        <div class="metric-card"><div class="metric-label">Average Vacational Balance</div><div class="report-value">{{ number_format((float)$summary['avgAnnualBalance'], 3) }}</div></div>
    </div>
    @endif

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
            <div class="leave-card-container">
                <div class="leave-card-table">
                    <div class="leave-card-header">
                        <h3>Leave Card - {{ $selectedEmployee->fullName() }}</h3>
                        <p>Complete transaction history combining leave requests and balance history.</p>
                    </div>
                    <div class="leave-card-table-wrap">
                        <table id="leaveCardTable">
                            <thead>
                                <tr>
                                    <th>PERIOD</th>
                                    <th>PARTICULARS</th>
                                    <th>VAC EARNED</th>
                                    <th>VAC ABS W/ PAY</th>
                                    <th>VAC BALANCE</th>
                                    <th>VAC ABS W/O PAY</th>
                                    <th>SICK EARNED</th>
                                    <th>SICK ABS W/ PAY</th>
                                    <th>SICK BALANCE</th>
                                    <th>SICK ABS W/O PAY</th>
                                    <th>REMARKS</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($reportData as $row)
                                <tr>
                                    <td>{{ $row['period'] ?? $row['date'] }}</td>
                                    <td>{{ $row['particulars'] }}</td>
                                    <td>{{ ($row['vac_earned'] ?? 0) != 0 ? number_format((float)$row['vac_earned'],3) : '' }}</td>
                                    <td>{{ ($row['vac_with_pay'] ?? 0) != 0 ? number_format((float)$row['vac_with_pay'],3) : '' }}</td>
                                    <td>{{ $row['vac_balance'] === '' ? '' : number_format((float)$row['vac_balance'],3) }}</td>
                                    <td>{{ ($row['vac_without_pay'] ?? 0) != 0 ? number_format((float)$row['vac_without_pay'],3) : '' }}</td>
                                    <td>{{ ($row['sick_earned'] ?? 0) != 0 ? number_format((float)$row['sick_earned'],3) : '' }}</td>
                                    <td>{{ ($row['sick_with_pay'] ?? 0) != 0 ? number_format((float)$row['sick_with_pay'],3) : '' }}</td>
                                    <td>{{ $row['sick_balance'] === '' ? '' : number_format((float)$row['sick_balance'],3) }}</td>
                                    <td>{{ ($row['sick_without_pay'] ?? 0) != 0 ? number_format((float)$row['sick_without_pay'],3) : '' }}</td>
                                    <td>{{ $row['remarks'] ?? ($row['status'] ?? '') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="11">No leave card history found for this employee.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="leave-card-pagination">
                        <div class="leave-card-pagination-info">
                            Showing <span id="pageStart">1</span> to <span id="pageEnd">10</span> of <span id="totalRows">0</span> records
                        </div>
                        <div class="leave-card-pagination-buttons">
                            <button id="prevBtn" class="pagination-btn nav-btn" onclick="previousPage()" style="margin-right:6px;">Prev</button>
                            <div id="pageNumbers"></div>
                            <button id="nextBtn" class="pagination-btn nav-btn" onclick="nextPage()" style="margin-left:6px;">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('leaveCardTable');
    if (!table) return;
    
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const emptyRow = rows.find(r => r.querySelector('td[colspan="11"]'));
    
    if (emptyRow) {
        rows.length = rows.indexOf(emptyRow) + 1;
    }
    
    const dataRows = rows.filter(r => !r.querySelector('td[colspan="11"]'));
    const rowsPerPage = 10;
    let currentPage = 1;
    const totalPages = Math.ceil(dataRows.length / rowsPerPage);
    
    function updateDisplay() {
        const startIdx = (currentPage - 1) * rowsPerPage;
        const endIdx = startIdx + rowsPerPage;
        
        dataRows.forEach((row, idx) => {
            row.style.display = (idx >= startIdx && idx < endIdx) ? '' : 'none';
        });
        
        const actualEnd = Math.min(endIdx, dataRows.length);
        document.getElementById('pageStart').textContent = dataRows.length > 0 ? startIdx + 1 : 0;
        document.getElementById('pageEnd').textContent = actualEnd;
        document.getElementById('totalRows').textContent = dataRows.length;
        
        document.getElementById('prevBtn').disabled = currentPage === 1;
        document.getElementById('nextBtn').disabled = currentPage === totalPages;
        
        const pageNumbersDiv = document.getElementById('pageNumbers');
        pageNumbersDiv.innerHTML = '';
        
        // Calculate which page numbers to show
        const maxVisible = 10;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = 'pagination-btn pagination-number-btn' + (i === currentPage ? ' active' : '');
            btn.onclick = () => { currentPage = i; updateDisplay(); };
            pageNumbersDiv.appendChild(btn);
        }
    }
    
    window.previousPage = function() {
        if (currentPage > 1) {
            currentPage--;
            updateDisplay();
        }
    };
    
    window.nextPage = function() {
        if (currentPage < totalPages) {
            currentPage++;
            updateDisplay();
        }
    };
    
    updateDisplay();
});
</script>
@endpush
