@extends('layouts.app')
@section('title', 'Manage Leave Types - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Leave Types',
        'subtitle' => 'Configure available leave categories and allocation rules',
        'actions' => ['<button type="button" class="btn btn-action-green " id="openCreateLeaveTypeModal">+ New Leave Type</button>']
    ])

    <div class="ui-card leave-types-card ajax-fragment">
        <div class="fragment-toolbar">
            <form method="GET" action="{{ route('manage-leave-types') }}" class="leave-type-search-form" id="leaveTypeLiveSearchForm">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" id="leaveTypeLiveSearchInput" value="{{ $search }}" placeholder="Search leave types..." autocomplete="off">
                </div>
                <button type="submit" class="btn btn-search-submit">Search</button>
                <a href="{{ route('manage-leave-types') }}" class="btn btn-ghost" id="leaveTypeLiveSearchClear" style="{{ $search !== '' ? '' : 'display:none;' }}">Clear</a>
                <span class="live-search-status" id="leaveTypeLiveSearchStatus" aria-live="polite"></span>
            </form>
            <div class="fragment-summary" id="leaveTypeLiveSearchSummary">Showing {{ $types->firstItem() ?? 0 }}–{{ $types->lastItem() ?? 0 }} of {{ $types->total() }} leave types.</div>
        </div>
        <div class="table-wrap" id="leaveTypeLiveSearchResults">
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
                            <div class="department-actions" style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">
                                <button type="button" class="btn btn-secondary btn-sm edit-leave-type-btn" data-payload='@json($type)'>Edit</button>
                                <form method="POST" action="{{ route('manage-leave-types.destroy', $type) }}?{{ http_build_query(request()->only('q', 'page')) }}" style="margin: 0; display: inline-flex;" onsubmit="return confirm('Delete this leave type?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="help-text">{{ $search !== '' ? 'No matching leave types found for this search.' : 'No leave types found.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div id="leaveTypeLiveSearchPagination" style="margin-top:18px;">
            @if($types->hasPages())
                {{ $types->links('vendor.pagination.clean') }}
            @endif
        </div>
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
.leave-types-card .fragment-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.leave-type-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.leave-type-search-form .search-input{min-width:280px;flex:1}.type-name{font-weight:700;color:var(--text)}.type-meta{font-size:12px;color:var(--muted);margin-top:4px}.leave-type-modal-content{max-width:1100px}.leave-type-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.leave-type-form-grid .field.full{grid-column:1 / -1}.leave-type-check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.leave-type-panel{border:1px solid var(--border);border-radius:16px;padding:16px;background:#fff}.live-search-status{font-size:12px;color:var(--muted);min-width:74px}.live-search-loading{opacity:.55;pointer-events:none;transition:opacity .15s ease}@media (max-width:900px){.leave-type-form-grid,.leave-type-check-grid{grid-template-columns:1fr}}
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

    function bindEditLeaveTypeButtons(){
        document.querySelectorAll('.edit-leave-type-btn').forEach(function(btn){
            if(btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(){
                var payload = JSON.parse(btn.getAttribute('data-payload'));
                editForm.action = '{{ url('/manage-leave-types') }}/' + payload.id + window.location.search;
                ['name','law_title','law_text','max_days_per_year','balance_bucket','deduct_behavior','max_days','min_days_notice','details_schema_json','rules_text','min_days_advance','max_duration_days','special_rules_text'].forEach(function(field){ setVal('edit_' + field, payload[field]); });
                ['deduct_balance','requires_approval','auto_approve','allow_emergency','requires_documents','requires_medical_certificate','requires_affidavit_if_no_medcert','requires_travel_details','requires_affidavit_if_no_medical','requires_proof_of_pregnancy','requires_marriage_certificate','requires_child_delivery_proof','requires_solo_parent_id','requires_police_report','requires_barangay_protection_order','requires_medical_report','requires_letter_request','requires_dswd_proof','allow_emergency_filing','allow_half_day','with_pay_default'].forEach(function(field){ setBool('edit_' + field, payload[field]); });
                editModal.style.display = 'flex';
            });
        });
    }
    bindEditLeaveTypeButtons();

    var searchForm = document.getElementById('leaveTypeLiveSearchForm');
    var searchInput = document.getElementById('leaveTypeLiveSearchInput');
    var clearBtn = document.getElementById('leaveTypeLiveSearchClear');
    var statusText = document.getElementById('leaveTypeLiveSearchStatus');
    var results = document.getElementById('leaveTypeLiveSearchResults');
    var summary = document.getElementById('leaveTypeLiveSearchSummary');
    var pagination = document.getElementById('leaveTypeLiveSearchPagination');
    var searchTimer = null;
    var activeController = null;

    function setLoading(isLoading){
        results.classList.toggle('live-search-loading', isLoading);
        statusText.textContent = isLoading ? 'Searching...' : '';
    }

    function buildSearchUrl(pageUrl){
        var url = new URL(pageUrl || searchForm.action, window.location.origin);
        var query = searchInput.value.trim();
        if(query){ url.searchParams.set('q', query); } else { url.searchParams.delete('q'); }
        if(!pageUrl){ url.searchParams.delete('page'); }
        return url;
    }

    function fetchLeaveTypes(pageUrl){
        if(activeController){ activeController.abort(); }
        activeController = new AbortController();
        var url = buildSearchUrl(pageUrl);
        setLoading(true);
        fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}, signal: activeController.signal})
            .then(function(response){ return response.text(); })
            .then(function(html){
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newResults = doc.getElementById('leaveTypeLiveSearchResults');
                var newSummary = doc.getElementById('leaveTypeLiveSearchSummary');
                var newPagination = doc.getElementById('leaveTypeLiveSearchPagination');
                if(newResults) results.innerHTML = newResults.innerHTML;
                if(newSummary) summary.innerHTML = newSummary.innerHTML;
                pagination.innerHTML = newPagination ? newPagination.innerHTML : '';
                clearBtn.style.display = searchInput.value.trim() ? '' : 'none';
                window.history.replaceState({}, '', url.pathname + url.search);
                bindEditLeaveTypeButtons();
            })
            .catch(function(error){ if(error.name !== 'AbortError') statusText.textContent = 'Search failed.'; })
            .finally(function(){ setLoading(false); });
    }

    searchInput.addEventListener('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){ fetchLeaveTypes(); }, 250);
    });
    searchForm.addEventListener('submit', function(e){ e.preventDefault(); fetchLeaveTypes(); });
    clearBtn.addEventListener('click', function(e){ e.preventDefault(); searchInput.value = ''; fetchLeaveTypes(); searchInput.focus(); });
    pagination.addEventListener('click', function(e){
        var link = e.target.closest('a');
        if(!link) return;
        e.preventDefault();
        fetchLeaveTypes(link.href);
        searchInput.focus();
    });

    window.addEventListener('click', function(e){ if(e.target === createModal){createModal.style.display='none';} if(e.target === editModal){editModal.style.display='none';} });
})();
</script>
@endpush
