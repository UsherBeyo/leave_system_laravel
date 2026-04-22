@extends('layouts.app')
@section('title', 'Manage Leave Types - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Leave Types',
        'subtitle' => 'Configure available leave categories and allocation rules',
        'actions' => ['<button type="button" class="btn btn-primary" id="openCreateLeaveTypeModal">+ New Leave Type</button>']
    ])

    <div class="ui-card leave-types-card ajax-fragment">
        <div class="fragment-toolbar">
            <form method="GET" action="{{ route('manage-leave-types') }}" class="leave-type-search-form">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" value="{{ $search }}" placeholder="Search leave types...">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
                @if($search !== '')<a href="{{ route('manage-leave-types') }}" class="btn btn-ghost">Clear</a>@endif
            </form>
            <div class="fragment-summary">Showing {{ $types->firstItem() ?? 0 }}–{{ $types->lastItem() ?? 0 }} of {{ $types->total() }} leave types.</div>
        </div>
        <div class="table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Bucket</th>
                        <th>Deduct?</th>
                        <th>Approval?</th>
                        <th>Notice</th>
                        <th>Docs?</th>
                        <th>Max/Yr</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($types as $type)
                    <tr>
                        <td>
                            <div class="type-name">{{ $type->name }}</div>
                            @if($type->law_title)<div class="type-meta">{{ $type->law_title }}</div>@endif
                        </td>
                        <td>{{ ucfirst($type->balance_bucket ?: 'annual') }}</td>
                        <td>{{ $type->deduct_balance ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->requires_approval ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->min_days_notice ?? $type->min_days_advance ?? 0 }} day(s)</td>
                        <td>{{ $type->requires_documents ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->max_days_per_year ?: '-' }}</td>
                        <td>
                            <div class="department-actions">
                                <button type="button" class="btn btn-secondary btn-sm edit-leave-type-btn" data-payload='@json($type)'>Edit</button>
                                <form method="POST" action="{{ route('manage-leave-types.destroy', $type) }}?{{ http_build_query(request()->only('q', 'page')) }}" onsubmit="return confirm('Delete this leave type?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="help-text">No leave types found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($types->hasPages())
            <div style="margin-top:18px;">{{ $types->links('vendor.pagination.clean') }}</div>
        @endif
    </div>

    <div id="createLeaveTypeModal" class="modal" style="display:none;">
        <div class="modal-content large leave-type-modal-content">
            <span class="modal-close" data-close="createLeaveTypeModal">&times;</span>
            <h3>Create Leave Type</h3>
            @include('leave-types.partials.form', ['action' => route('manage-leave-types.store'), 'method' => 'POST', 'prefix' => 'create', 'type' => null])
        </div>
    </div>

    <div id="editLeaveTypeModal" class="modal" style="display:none;">
        <div class="modal-content large leave-type-modal-content">
            <span class="modal-close" data-close="editLeaveTypeModal">&times;</span>
            <h3>Edit Leave Type</h3>
            @include('leave-types.partials.form', ['action' => '#', 'method' => 'PUT', 'prefix' => 'edit', 'type' => null])
        </div>
    </div>
@endsection

@push('head')
<style>
.leave-types-card .fragment-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.leave-type-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.leave-type-search-form .search-input{min-width:280px;flex:1}.type-name{font-weight:700;color:var(--text)}.type-meta{font-size:12px;color:var(--muted);margin-top:4px}.leave-type-modal-content{max-width:1100px}.leave-type-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.leave-type-form-grid .field.full{grid-column:1 / -1}.leave-type-check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.leave-type-panel{border:1px solid var(--border);border-radius:16px;padding:16px;background:#fff}@media (max-width:900px){.leave-type-form-grid,.leave-type-check-grid{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>
(function(){
    var createModal = document.getElementById('createLeaveTypeModal');
    var editModal = document.getElementById('editLeaveTypeModal');
    var editForm = document.getElementById('edit-leave-type-form');
    document.getElementById('openCreateLeaveTypeModal').addEventListener('click', function(){ createModal.style.display = 'flex'; });
    document.querySelectorAll('[data-close]').forEach(function(btn){ btn.addEventListener('click', function(){ document.getElementById(btn.getAttribute('data-close')).style.display = 'none'; }); });

    function setBool(id, value){ var el = document.getElementById(id); if(el){ el.checked = !!Number(value || 0) || value === true; } }
    function setVal(id, value){ var el = document.getElementById(id); if(el){ el.value = value ?? ''; } }

    document.querySelectorAll('.edit-leave-type-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var payload = JSON.parse(btn.getAttribute('data-payload'));
            editForm.action = '{{ url('/manage-leave-types') }}/' + payload.id + '?{{ http_build_query(request()->only('q','page')) }}';
            ['name','law_title','law_text','max_days_per_year','balance_bucket','deduct_behavior','max_days','min_days_notice','details_schema_json','rules_text','min_days_advance','max_duration_days','special_rules_text'].forEach(function(field){ setVal('edit_' + field, payload[field]); });
            ['deduct_balance','requires_approval','auto_approve','allow_emergency','requires_documents','requires_medical_certificate','requires_affidavit_if_no_medcert','requires_travel_details','requires_affidavit_if_no_medical','requires_proof_of_pregnancy','requires_marriage_certificate','requires_child_delivery_proof','requires_solo_parent_id','requires_police_report','requires_barangay_protection_order','requires_medical_report','requires_letter_request','requires_dswd_proof','allow_emergency_filing','allow_half_day','with_pay_default'].forEach(function(field){ setBool('edit_' + field, payload[field]); });
            editModal.style.display = 'flex';
        });
    });
    window.addEventListener('click', function(e){ if(e.target === createModal){createModal.style.display='none';} if(e.target === editModal){editModal.style.display='none';} });
})();
</script>
@endpush
