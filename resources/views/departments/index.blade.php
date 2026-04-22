@extends('layouts.app')
@section('title', 'Manage Departments - Leave System')

@section('content')
    @include('partials.page-header', [
        'title' => 'Manage Departments',
        'subtitle' => 'Manage department structure and employee assignments',
        'actions' => ['<button type="button" class="btn btn-primary" id="openCreateDepartmentModal">+ New Department</button>']
    ])

    <div class="ui-card ajax-fragment department-shell">
        <div class="fragment-toolbar">
            <form method="GET" action="{{ route('manage-departments') }}" class="department-search-form">
                <div class="search-input">
                    <input class="form-control" type="text" name="q" value="{{ $search }}" placeholder="Search departments...">
                </div>
                <button type="submit" class="btn btn-secondary">Search</button>
                @if($search !== '')<a href="{{ route('manage-departments') }}" class="btn btn-ghost">Clear</a>@endif
            </form>
            <div class="fragment-summary">Showing {{ $departments->firstItem() ?? 0 }}–{{ $departments->lastItem() ?? 0 }} of {{ $departments->total() }} departments.</div>
        </div>

        <div class="table-wrap">
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
                    <tr><td colspan="5" class="help-text">No departments found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($departments->hasPages())
            <div style="margin-top:18px;">{{ $departments->links('vendor.pagination.clean') }}</div>
        @endif
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
.department-shell .fragment-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}.department-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.department-search-form .search-input{min-width:280px;flex:1}.department-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.btn-sm{padding:8px 12px;font-size:13px}
</style>
@endpush

@push('scripts')
<script>
(function(){
    var createModal = document.getElementById('createDepartmentModal');
    var editModal = document.getElementById('editDepartmentModal');
    document.getElementById('openCreateDepartmentModal').addEventListener('click', function(){ createModal.style.display = 'flex'; });
    document.querySelectorAll('[data-close]').forEach(function(btn){ btn.addEventListener('click', function(){ document.getElementById(btn.getAttribute('data-close')).style.display = 'none'; }); });
    document.querySelectorAll('.edit-department-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('editDepartmentName').value = btn.getAttribute('data-name');
            document.getElementById('editDepartmentForm').action = '{{ url('/manage-departments') }}/' + btn.getAttribute('data-id') + '?{{ http_build_query(request()->only('q', 'page')) }}';
            editModal.style.display = 'flex';
        });
    });
    window.addEventListener('click', function(e){ if(e.target === createModal){createModal.style.display='none';} if(e.target === editModal){editModal.style.display='none';} });
})();
</script>
@endpush
