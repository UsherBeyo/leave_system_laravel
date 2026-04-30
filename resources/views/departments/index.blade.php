@extends('layouts.app')
@section('title', 'Manage Departments - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Manage Departments',
        'subtitle' => 'Manage department structure and employee assignments',
        'actions' => ['<button type="button" class="btn btn-action-green" id="openCreateDepartmentModal">+ New Department</button>']
    ])

    <div class="ui-card ajax-fragment department-shell">
        <div class="fragment-toolbar">
            <form method="GET" action="{{ route('manage-departments') }}" class="department-search-form" id="departmentLiveSearchForm">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" id="departmentLiveSearchInput" value="{{ $search }}" placeholder="Search departments..." autocomplete="off">
                </div>
                <button type="submit" class="btn btn-search-submit">Search</button>
                <a href="{{ route('manage-departments') }}" class="btn btn-ghost" id="departmentLiveSearchClear" style="{{ $search !== '' ? '' : 'display:none;' }}">Clear</a>
                <span class="live-search-status" id="departmentLiveSearchStatus" aria-live="polite"></span>
            </form>
            <div class="fragment-summary" id="departmentLiveSearchSummary">Showing {{ $departments->firstItem() ?? 0 }}–{{ $departments->lastItem() ?? 0 }} of {{ $departments->total() }} departments.</div>
        </div>

        <div class="table-wrap" id="departmentLiveSearchResults">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Employees</th>
                        <th>Department Heads</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td>{{ $department->id }}</td>
                        <td>{{ $department->name }}</td>
                        <td>{{ $department->employees_count }}</td>
                        <td>{{ $department->head_assignments_count }}</td>
                        <td>
                            <div class="department-actions">
                                <button type="button" class="btn btn-secondary btn-sm edit-department-btn"
                                    data-id="{{ $department->id }}"
                                    data-name="{{ e($department->name) }}">Edit</button>
                                <form method="POST" action="{{ route('manage-departments.destroy', $department) }}?{{ http_build_query(request()->only('q', 'page')) }}" onsubmit="return confirm('Delete this department?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="help-text">{{ $search !== '' ? 'No matching departments found for this search.' : 'No departments found.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div id="departmentLiveSearchPagination" style="margin-top:18px;">
            @if($departments->hasPages())
                {{ $departments->links('vendor.pagination.clean') }}
            @endif
        </div>
    </div>

    <div id="createDepartmentModal" class="modal" style="display:none;">
        <div class="modal-content small">
            <span class="modal-close" data-close="createDepartmentModal">&times;</span>
            <h3>Create Department</h3>
            <form method="POST" action="{{ route('manage-departments.store') }}">
                @csrf
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required class="form-control" value="{{ old('name') }}">
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editDepartmentModal" class="modal" style="display:none;">
        <div class="modal-content small">
            <span class="modal-close" data-close="editDepartmentModal">&times;</span>
            <h3>Edit Department</h3>
            <form method="POST" id="editDepartmentForm">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="editDepartmentName" required class="form-control">
                </div>
                <div style="text-align:right;">
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('head')
<style>
.department-shell .fragment-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.department-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.department-search-form .search-input{min-width:280px;flex:1}.department-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.btn-sm{padding:8px 12px;font-size:13px}.live-search-status{font-size:12px;color:var(--muted);min-width:74px}.live-search-loading{opacity:.55;pointer-events:none;transition:opacity .15s ease}
</style>
@endpush

@push('scripts')
<script>
(function(){
    var createModal = document.getElementById('createDepartmentModal');
    var editModal = document.getElementById('editDepartmentModal');
    document.getElementById('openCreateDepartmentModal').addEventListener('click', function(){ createModal.style.display = 'flex'; });
    document.querySelectorAll('[data-close]').forEach(function(btn){ btn.addEventListener('click', function(){ document.getElementById(btn.getAttribute('data-close')).style.display = 'none'; }); });
    function bindEditDepartmentButtons(){
        document.querySelectorAll('.edit-department-btn').forEach(function(btn){
            if(btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(){
                document.getElementById('editDepartmentName').value = btn.getAttribute('data-name');
                document.getElementById('editDepartmentForm').action = '{{ url('/manage-departments') }}/' + btn.getAttribute('data-id') + window.location.search;
                editModal.style.display = 'flex';
            });
        });
    }
    bindEditDepartmentButtons();

    var searchForm = document.getElementById('departmentLiveSearchForm');
    var searchInput = document.getElementById('departmentLiveSearchInput');
    var clearBtn = document.getElementById('departmentLiveSearchClear');
    var statusText = document.getElementById('departmentLiveSearchStatus');
    var results = document.getElementById('departmentLiveSearchResults');
    var summary = document.getElementById('departmentLiveSearchSummary');
    var pagination = document.getElementById('departmentLiveSearchPagination');
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

    function fetchDepartments(pageUrl){
        if(activeController){ activeController.abort(); }
        activeController = new AbortController();
        var url = buildSearchUrl(pageUrl);
        setLoading(true);
        fetch(url.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}, signal: activeController.signal})
            .then(function(response){ return response.text(); })
            .then(function(html){
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newResults = doc.getElementById('departmentLiveSearchResults');
                var newSummary = doc.getElementById('departmentLiveSearchSummary');
                var newPagination = doc.getElementById('departmentLiveSearchPagination');
                if(newResults) results.innerHTML = newResults.innerHTML;
                if(newSummary) summary.innerHTML = newSummary.innerHTML;
                pagination.innerHTML = newPagination ? newPagination.innerHTML : '';
                clearBtn.style.display = searchInput.value.trim() ? '' : 'none';
                window.history.replaceState({}, '', url.pathname + url.search);
                bindEditDepartmentButtons();
            })
            .catch(function(error){ if(error.name !== 'AbortError') statusText.textContent = 'Search failed.'; })
            .finally(function(){ setLoading(false); });
    }

    searchInput.addEventListener('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){ fetchDepartments(); }, 250);
    });
    searchForm.addEventListener('submit', function(e){ e.preventDefault(); fetchDepartments(); });
    clearBtn.addEventListener('click', function(e){ e.preventDefault(); searchInput.value = ''; fetchDepartments(); searchInput.focus(); });
    pagination.addEventListener('click', function(e){
        var link = e.target.closest('a');
        if(!link) return;
        e.preventDefault();
        fetchDepartments(link.href);
        searchInput.focus();
    });

    window.addEventListener('click', function(e){ if(e.target === createModal){createModal.style.display='none';} if(e.target === editModal){editModal.style.display='none';} });
})();
</script>
@endpush
