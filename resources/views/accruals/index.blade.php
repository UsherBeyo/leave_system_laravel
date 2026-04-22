@extends('layouts.app')
@section('title', 'Manage Accruals - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Manage Accruals',
        'subtitle' => 'Add leave accruals for employees.',
        'actions' => []
    ])

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
            <div class="accrual-highlight"><span class="accrual-highlight-label">Employees Affected</span><strong>{{ $totalEmployees }}</strong></div>
            <div class="accrual-highlight"><span class="accrual-highlight-label">Default Amount</span><strong>1.250 days</strong></div>
            <div class="accrual-highlight"><span class="accrual-highlight-label">Balance Impact</span><strong>Vacational + Sick</strong></div>
        </div>
        <div class="accrual-bulk-launcher-actions">
            <button type="button" class="btn btn-primary accrual-bulk-trigger" id="openBulkAccrualModal">Open Bulk Accrual</button>
        </div>
    </div>

    <div class="accrual-lower-grid">
        <div class="manual-accrual-card">
            <h3>Record Manual Accrual</h3>
            <p class="accrual-description">Use this to record manual accruals for past periods or special cases.</p>
            <form method="POST" action="{{ route('manage-accruals.manual') }}" class="accrual-manual-form">
                @csrf
                <div class="accrual-form-item">
                    <label>Employee</label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->fullName() }} (Vac: {{ number_format((float)$employee->annual_balance, 3) }} | Sick: {{ number_format((float)$employee->sick_balance, 3) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="accrual-form-item">
                    <label>Amount (days)</label>
                    <input type="number" step="0.001" name="amount" value="1.250" required>
                </div>
                <div class="accrual-form-item">
                    <label>For Month</label>
                    <input type="month" name="month" value="{{ now()->format('Y-m') }}" required>
                </div>
                <div class="accrual-form-actions"><button type="submit" class="btn btn-primary">Record Accrual</button></div>
            </form>
        </div>

        <div class="history-card ajax-fragment" data-fragment-id="accrual-history" data-page-param="history_page" data-search-param="history_q">
            <h3>Accrual History</h3>
            <p class="accrual-description">Recent accrual transactions.</p>
            <div class="fragment-toolbar">
                <form method="GET" action="{{ route('manage-accruals') }}" class="accrual-history-search">
                    <div class="search-input"><input class="form-control" type="text" name="history_q" value="{{ $search }}" placeholder="Search employee or month..."></div>
                    <button type="submit" class="btn btn-secondary">Search</button>
                    @if($search !== '')<a href="{{ route('manage-accruals') }}" class="btn btn-ghost">Clear</a>@endif
                </form>
                <div class="fragment-summary">Showing {{ $history->firstItem() ?? 0 }}–{{ $history->lastItem() ?? 0 }} of {{ $history->total() }} history rows</div>
            </div>
            <div class="history-table-shell">
                <table class="accrual-history-table">
                    <thead><tr><th>Employee</th><th>Amount</th><th>Month Ref</th><th>Date</th></tr></thead>
                    <tbody>
                    @forelse($history as $row)
                        @php $emp = $row->employee; @endphp
                        <tr>
                            <td>{{ $emp?->fullName() ?: 'Employee #' . $row->employee_id }}</td>
                            <td><span class="amount-pill">{{ number_format((float)$row->amount, 3) }} days</span></td>
                            <td>{{ $row->month_reference ?? '—' }}</td>
                            <td>{{ optional($row->date_accrued ?? $row->created_at)->format('F j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="help-text">No accrual history found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($history->hasPages())<div style="margin-top:18px;">{{ $history->links('vendor.pagination.clean') }}</div>@endif
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
                <div class="accrual-highlight"><span class="accrual-highlight-label">Employees Affected</span><strong>{{ $totalEmployees }} employee(s)</strong></div>
                <div class="accrual-highlight"><span class="accrual-highlight-label">Changes Applied To</span><strong>Vacational + Sick</strong></div>
                <div class="accrual-highlight"><span class="accrual-highlight-label">Not Included</span><strong>Force Leave</strong></div>
            </div>
            <form method="POST" action="{{ route('manage-accruals.bulk') }}" id="bulkAccrualForm" class="accrual-form-grid accrual-bulk-modal-form">
                @csrf
                <div class="accrual-form-item"><label>Employees Affected</label><input type="text" value="{{ $totalEmployees }} employee(s)" readonly></div>
                <div class="accrual-form-item"><label>Amount to Add (days)</label><input type="number" step="0.001" name="bulk_amount" id="bulk_amount" value="1.250" required></div>
                <div class="accrual-form-item"><label>For Month</label><input type="month" name="bulk_month" id="bulk_month" value="{{ now()->format('Y-m') }}" required></div>
                <div class="accrual-note accrual-bulk-inline-note"><span class="accrual-note-icon">⚠</span><span>Bulk accrual updates every employee and writes matching accrual history logs for the selected month.</span></div>
                <div class="accrual-form-actions accrual-bulk-actions"><button type="button" class="btn btn-secondary" id="cancelBulkAccrualModal">Cancel</button><button type="submit" class="btn btn-primary">Add Accrual to All Employees</button></div>
            </form>
        </div>
    </div>
@endsection

@push('head')
<style>
.accrual-bulk-launcher{display:grid;grid-template-columns:1.6fr 1fr auto;gap:18px;align-items:center;background:#fff;border:1px solid var(--border);border-radius:18px;padding:22px;margin-bottom:24px;box-shadow:0 4px 14px rgba(15,23,42,0.06)}.accrual-bulk-highlights{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.accrual-highlight,.manual-accrual-card,.history-card{background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:0 4px 14px rgba(15,23,42,0.06)}.accrual-highlight{padding:14px 16px}.accrual-highlight-label,.accrual-description{font-size:13px;color:var(--muted)}.accrual-highlight strong{display:block;margin-top:6px;font-size:18px;color:var(--text)}.accrual-note{display:flex;align-items:flex-start;gap:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:10px 12px;margin-top:12px;color:#9a3412}.accrual-lower-grid{display:grid;grid-template-columns:420px 1fr;gap:18px}.manual-accrual-card,.history-card{padding:22px}.accrual-manual-form{display:grid;gap:14px}.accrual-form-item{display:flex;flex-direction:column;gap:8px}.accrual-form-item label{font-size:13px;font-weight:700;color:var(--text)}.accrual-form-item input,.accrual-form-item select{padding:10px 12px;border:1px solid var(--border);border-radius:12px}.accrual-form-actions{display:flex;justify-content:flex-end;gap:8px}.history-table-shell{overflow:auto}.accrual-history-table{width:100%;border-collapse:collapse}.accrual-history-table th,.accrual-history-table td{padding:12px 14px;border-bottom:1px solid var(--border);text-align:left}.amount-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-weight:700;font-size:12px}.accrual-bulk-modal{display:none}.accrual-bulk-modal.open{display:flex}.accrual-bulk-modal-content{max-width:760px}.accrual-bulk-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.accrual-bulk-modal-form{display:grid;gap:14px}.accrual-bulk-actions{justify-content:flex-end}.accrual-history-search{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.accrual-history-search .search-input{min-width:240px;flex:1}@media (max-width:1100px){.accrual-bulk-launcher,.accrual-lower-grid{grid-template-columns:1fr}.accrual-bulk-highlights,.accrual-bulk-summary-grid{grid-template-columns:1fr}} 
</style>
@endpush

@push('scripts')
<script>
(function(){
    var bulkModal = document.getElementById('bulkAccrualModal');
    var openBulkModalBtn = document.getElementById('openBulkAccrualModal');
    var closeBulkModalBtn = document.getElementById('closeBulkAccrualModal');
    var cancelBulkModalBtn = document.getElementById('cancelBulkAccrualModal');
    var bulkAccrualForm = document.getElementById('bulkAccrualForm');
    function openBulkAccrualModal(){ if(!bulkModal) return; bulkModal.classList.add('open'); }
    function closeBulkAccrualModal(){ if(!bulkModal) return; bulkModal.classList.remove('open'); }
    if(openBulkModalBtn){ openBulkModalBtn.addEventListener('click', openBulkAccrualModal); }
    [closeBulkModalBtn,cancelBulkModalBtn].forEach(function(btn){ if(btn){ btn.addEventListener('click', closeBulkAccrualModal); } });
    if(bulkModal){ bulkModal.addEventListener('click', function(e){ if(e.target===bulkModal){closeBulkAccrualModal();} }); }
    document.addEventListener('keydown', function(e){ if(e.key==='Escape' && bulkModal && bulkModal.classList.contains('open')){ closeBulkAccrualModal(); } });
    if(bulkAccrualForm){ bulkAccrualForm.addEventListener('submit', function(e){ var amount=document.getElementById('bulk_amount').value||'1.250'; var month=document.getElementById('bulk_month').value||''; if(!confirm('Are you sure you want to add ' + amount + ' day(s) to BOTH Vacational and Sick balances of ALL employees?')){ e.preventDefault(); return; } if(!confirm('This will affect all employees and write accrual history logs for month ' + month + '. Continue?')){ e.preventDefault(); return; } if(!confirm('Final confirmation: this can be done even if it is NOT yet the end of the month. Force Leave will NOT be changed. Do you want to proceed?')){ e.preventDefault(); } }); }
})();
</script>
@endpush
