@extends('layouts.app')
@section('title', 'Manage Employees - Leave System')

@push('head')
<style>
.employee-shell{display:grid;gap:20px}
.employee-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.employee-search-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.employee-search-form .search-input{min-width:280px;flex:1}
.employee-list{display:grid;gap:16px;margin-top:8px}
.employee-item{border:1px solid var(--border);border-radius:22px;background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);padding:18px 18px 16px;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1.7fr) minmax(160px,.9fr);gap:18px;align-items:start;box-shadow:0 10px 30px rgba(15,23,42,.04)}
.employee-main{display:flex;gap:14px;align-items:flex-start;min-width:0}
.employee-avatar-thumb{width:54px;height:54px;border-radius:999px;object-fit:cover;border:2px solid #dbeafe;cursor:pointer;flex:0 0 auto}
.employee-avatar-fallback{width:54px;height:54px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.employee-name-wrap{min-width:0;display:grid;gap:6px}
.employee-name{font-size:20px;font-weight:800;color:#0f172a;line-height:1.15;word-break:break-word}
.employee-subline{color:var(--muted);font-size:14px;word-break:break-word}
.employee-badges{display:flex;gap:8px;flex-wrap:wrap}
.employee-role-pill,.employee-status-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}
.employee-role-pill{background:#eff6ff;color:#1d4ed8}
.employee-status-pill{background:#f8fafc;color:#334155;border:1px solid var(--border)}
.employee-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px 14px;min-width:0}
.employee-detail-card{border:1px solid #e5edf7;border-radius:16px;padding:12px 14px;background:#fff;min-width:0}
.employee-detail-card.wide{grid-column:span 3}
.employee-detail-label{display:block;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b;margin-bottom:6px}
.employee-detail-value{display:block;font-size:15px;font-weight:700;color:#0f172a;word-break:break-word}
.employee-balance-grid{grid-column:span 3;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.balance-chip{display:flex;flex-direction:column;gap:8px;padding:12px 14px;border-radius:18px;background:#f8fafc;border:1px solid var(--border);min-width:0}
.balance-chip .balance-label{font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b}
.balance-chip .balance-value{font-size:20px;font-weight:800;color:#0f172a}
.employee-actions{display:grid;grid-template-columns:1fr;gap:10px;align-self:stretch}
.employee-actions .btn{margin-right:0;width:100%;justify-content:center;white-space:nowrap;padding:11px 12px;font-size:13px;line-height:1.2}
.employee-empty{padding:18px;border:1px dashed var(--border);border-radius:18px;background:#fafcff;color:var(--muted)}
.employee-modal-wide{width:min(980px,calc(100vw - 32px));height:min(900px,calc(100vh - 40px));max-height:calc(100vh - 40px);padding:0;overflow:hidden;border-radius:24px;display:flex;flex-direction:column}
.employee-modal-shell{display:grid;grid-template-columns:240px 1fr;min-height:100%;height:100%}
.employee-modal-aside{background:linear-gradient(180deg,#2563eb 0%,#1d4ed8 100%);color:#fff;padding:28px 24px}
.employee-modal-aside h3{color:#fff;margin:0 0 12px;font-size:28px}
.employee-modal-aside p{color:rgba(255,255,255,.9);font-size:14px;line-height:1.6}
.employee-kicker{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.18);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:18px}
.employee-modal-form{padding:22px;background:#fff;display:flex;flex-direction:column;min-height:0}
.employee-modal-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding-right:6px}
.employee-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 16px}
.employee-grid .field.full{grid-column:1 / -1}
.employee-grid .field.two{grid-column:span 2}
.employee-grid .field label{display:block}
.employee-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
.employee-preview{display:flex;align-items:center;gap:14px;padding:14px;border:1px solid #dbe3ef;border-radius:16px;background:linear-gradient(180deg,#f8fbff 0%,#eff6ff 100%);margin-bottom:16px}
.employee-preview-avatar{width:56px;height:56px;border-radius:999px;object-fit:cover;background:#eff6ff;color:#1d4ed8;font-size:24px;font-weight:800;display:flex;align-items:center;justify-content:center}
.employee-preview-name{margin:0;font-size:18px;font-weight:800;color:#0f172a}
.employee-preview-meta{margin:4px 0 0;color:var(--muted);font-size:13px}
.help-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.modal-image-shell{display:none;position:fixed;inset:0;background:rgba(2,6,23,.82);z-index:99999;align-items:center;justify-content:center;flex-direction:column;padding:24px}
.modal-image-shell img{max-width:80vw;max-height:80vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
.modal-image-title{color:#fff;font-size:20px;margin-bottom:22px;text-align:center}
.modal-image-shell button{margin-top:18px}
.sticky-actions{position:sticky;bottom:0;background:#fff}
.legend-text{font-size:12px;color:var(--muted)}
@media (max-width:1280px){.employee-item{grid-template-columns:minmax(0,1fr)}.employee-actions{grid-template-columns:repeat(3,minmax(0,1fr));align-self:auto}.employee-details{grid-template-columns:repeat(2,minmax(0,1fr))}.employee-detail-card.wide,.employee-balance-grid{grid-column:span 2}}
@media (max-width:1120px){.employee-modal-shell{grid-template-columns:1fr}.employee-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.employee-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:760px){.employee-grid,.employee-details,.employee-balance-grid,.employee-actions{grid-template-columns:1fr}.employee-detail-card.wide,.employee-balance-grid{grid-column:auto}.employee-modal-wide{width:min(100vw - 12px,980px);height:min(100vh - 12px,900px)}.employee-search-form .search-input{min-width:100%}}
</style>
@endpush

@section('content')
@include('partials.page-header', [
    'title' => 'Manage Employees',
    'subtitle' => 'Create, edit, and review employee accounts and balances.',
    'actions' => ['<button type="button" class="btn btn-primary" id="openCreateEmployeeModal">+ New Employee</button>']
])

<div class="employee-shell">
    <div class="ui-card">
        <div class="employee-toolbar">
            <form method="GET" action="{{ route('manage-employees') }}" class="employee-search-form">
                <div class="search-input"><input class="form-control" type="text" name="q" value="{{ $search }}" placeholder="Search employees..."></div>
                <button type="submit" class="btn btn-secondary">Search</button>
                @if($search !== '')<a href="{{ route('manage-employees') }}" class="btn btn-ghost">Clear</a>@endif
            </form>
            <div class="legend-text">Showing {{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }} of {{ $employees->total() }} employees.</div>
        </div>

        <div class="employee-list">
            @forelse($employees as $employee)
                @php
                    $payload = [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'email' => $employee->user?->email,
                        'role' => $employee->user?->role,
                        'is_active' => (int) ($employee->user?->is_active ?? 1),
                        'first_name' => $employee->first_name,
                        'middle_name' => $employee->middle_name,
                        'last_name' => $employee->last_name,
                        'department_id' => $employee->department_id,
                        'manager_id' => $employee->manager_id,
                        'position' => $employee->position,
                        'salary' => $employee->salary,
                        'status' => $employee->status,
                        'civil_status' => $employee->civil_status,
                        'entrance_to_duty' => optional($employee->entrance_to_duty)->format('Y-m-d'),
                        'unit' => $employee->unit,
                        'gsis_policy_no' => $employee->gsis_policy_no,
                        'national_reference_card_no' => $employee->national_reference_card_no,
                        'annual_balance' => $employee->annual_balance,
                        'sick_balance' => $employee->sick_balance,
                        'force_balance' => $employee->force_balance,
                        'profile_pic' => $employee->profile_pic,
                    ];
                    $imageUrl = $employee->profile_pic ? asset(ltrim(str_replace('../','',$employee->profile_pic), '/')) : null;
                    $entrance = $employee->entrance_to_duty ? $employee->entrance_to_duty->format('F j, Y') : '—';
                    $statusText = $employee->status ?: ($employee->user?->is_active ? 'Active' : 'Inactive');
                    $roleText = ucfirst(str_replace('_',' ', (string) $employee->user?->role));
                @endphp
                <article class="employee-item">
                    <div class="employee-main">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $employee->fullName() }}" class="employee-avatar-thumb" data-image-src="{{ $imageUrl }}" data-image-name="{{ $employee->fullName() }}">
                        @else
                            <div class="employee-avatar-fallback">{{ strtoupper(substr($employee->first_name ?: ($employee->user?->email ?? 'E'), 0, 1)) }}</div>
                        @endif
                        <div class="employee-name-wrap">
                            <div class="employee-name">{{ $employee->fullName() }}</div>
                            <div class="employee-subline">{{ $employee->user?->email ?: '—' }}</div>
                            <div class="employee-badges">
                                <span class="employee-role-pill">{{ $roleText }}</span>
                                <span class="employee-status-pill">{{ $statusText }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="employee-details">
                        <div class="employee-detail-card">
                            <span class="employee-detail-label">Department</span>
                            <span class="employee-detail-value">{{ $employee->department ?: '—' }}</span>
                        </div>
                        <div class="employee-detail-card">
                            <span class="employee-detail-label">Position</span>
                            <span class="employee-detail-value">{{ $employee->position ?: '—' }}</span>
                        </div>
                        <div class="employee-detail-card">
                            <span class="employee-detail-label">Entrance to Duty</span>
                            <span class="employee-detail-value">{{ $entrance }}</span>
                        </div>
                        <div class="employee-balance-grid">
                            <div class="balance-chip">
                                <span class="balance-label">Vacational</span>
                                <span class="balance-value">{{ number_format((float) $employee->annual_balance, 3) }}</span>
                            </div>
                            <div class="balance-chip">
                                <span class="balance-label">Sick</span>
                                <span class="balance-value">{{ number_format((float) $employee->sick_balance, 3) }}</span>
                            </div>
                            <div class="balance-chip">
                                <span class="balance-label">Force</span>
                                <span class="balance-value">{{ number_format((float) $employee->force_balance, 0) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="employee-actions">
                        <a href="{{ route('employee-profile', ['employee' => $employee->id]) }}" class="btn btn-ghost btn-sm">Profile</a>
                        <a href="{{ route('employee-profile', ['employee' => $employee->id, 'export' => 'leave_card']) }}" class="btn btn-ghost btn-sm">Leave Card</a>
                        <a href="{{ route('manage-employees', array_filter(['q' => $search, 'page' => request('page'), 'view_history' => $employee->id])) }}#employee-history" class="btn btn-ghost btn-sm">History</a>
                        <button type="button" class="btn btn-secondary btn-sm open-edit-modal" data-payload='@json($payload)'>Edit</button>
                    </div>
                </article>
            @empty
                <div class="employee-empty">No employees found.</div>
            @endforelse
        </div>

        @if($employees->hasPages())
            <div style="margin-top:18px;">{{ $employees->links('vendor.pagination.clean') }}</div>
        @endif
    </div>
</div>



<div id="createEmployeeModal" class="modal" style="display:none;">
    <div class="modal-content employee-modal-wide">
        <span class="modal-close" data-close="createEmployeeModal">&times;</span>
        <div class="employee-modal-shell">
            <div class="employee-modal-aside">
                <span class="employee-kicker">New account</span>
                <h3>Create Employee</h3>
                <p>Create the user account and employee record in one step. This keeps the Laravel flow aligned with the reference system.</p>
            </div>
            <form method="POST" action="{{ route('manage-employees.store') }}" enctype="multipart/form-data" class="employee-modal-form">
                @csrf
                <div class="employee-modal-body">
                    @include('employees.partials.form', ['mode' => 'create'])
                </div>
                <div class="employee-modal-actions sticky-actions">
                    <button type="button" class="btn btn-secondary" data-close="createEmployeeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editEmployeeModal" class="modal" style="display:none;">
    <div class="modal-content employee-modal-wide">
        <span class="modal-close" data-close="editEmployeeModal">&times;</span>
        <div class="employee-modal-shell">
            <div class="employee-modal-aside">
                <span class="employee-kicker">Update record</span>
                <h3>Edit Employee</h3>
                <p>Update employee information, balances, role assignment, and profile photo without leaving the list page.</p>
            </div>
            <form method="POST" id="editEmployeeForm" enctype="multipart/form-data" class="employee-modal-form">
                @csrf
                @method('PUT')
                <div class="employee-modal-body">
                    <div class="employee-preview">
                        <div id="editPreviewAvatar" class="employee-preview-avatar">E</div>
                        <div>
                            <p class="employee-preview-name" id="editPreviewName">Employee Name</p>
                            <p class="employee-preview-meta" id="editPreviewMeta">Department · Role</p>
                        </div>
                    </div>
                    @include('employees.partials.form', ['mode' => 'edit'])
                </div>
                <div class="employee-modal-actions sticky-actions">
                    <button type="button" class="btn btn-secondary" data-close="editEmployeeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="imageModal" class="modal-image-shell">
    <div class="modal-image-title" id="modalImageName"></div>
    <img id="modalImage" alt="Preview">
    <button type="button" class="btn btn-primary" id="closeImageModal">Close</button>
</div>
@endsection

@push('scripts')
<script>
(function(){
    const departments = @json($departments->map(fn($d)=>['id'=>$d->id,'name'=>$d->name])->values());
    const createModal = document.getElementById('createEmployeeModal');
    const editModal = document.getElementById('editEmployeeModal');
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalImageName = document.getElementById('modalImageName');
    const openCreate = document.getElementById('openCreateEmployeeModal');
    const editForm = document.getElementById('editEmployeeForm');

    function show(modal){ if(modal){ modal.style.display='flex'; } }
    function hide(modal){ if(modal){ modal.style.display='none'; } }

    if (openCreate) openCreate.addEventListener('click', () => show(createModal));
    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', () => hide(document.getElementById(btn.getAttribute('data-close')))));
    window.addEventListener('click', function(e){ if(e.target===createModal) hide(createModal); if(e.target===editModal) hide(editModal); if(e.target===imageModal) hide(imageModal); });

    function setField(id, value){ const el=document.getElementById(id); if(!el) return; if(el.type==='checkbox'){ el.checked=!!Number(value || 0); } else { el.value = value ?? ''; } }

    document.querySelectorAll('.open-edit-modal').forEach(btn => {
        btn.addEventListener('click', function(){
            const data = JSON.parse(btn.getAttribute('data-payload'));
            editForm.action = '{{ url('/manage-employees') }}/' + data.id + '?{{ http_build_query(request()->only('q','page')) }}';
            ['email','first_name','middle_name','last_name','department_id','manager_id','role','position','salary','status','civil_status','entrance_to_duty','unit','gsis_policy_no','national_reference_card_no','annual_balance','sick_balance','force_balance','password'].forEach(key => setField('edit_'+key, data[key]));
            setField('edit_is_active', data.is_active);
            const previewName = ((data.first_name || '') + ' ' + (data.middle_name || '') + ' ' + (data.last_name || '')).replace(/\s+/g, ' ').trim() || 'Employee';
            const dept = departments.find(d => String(d.id) === String(data.department_id || ''));
            document.getElementById('editPreviewName').textContent = previewName;
            document.getElementById('editPreviewMeta').textContent = (dept ? dept.name : 'No department') + ' · ' + (data.role ? data.role.replace('_',' ') : 'employee');
            const avatar = document.getElementById('editPreviewAvatar');
            if (data.profile_pic) {
                avatar.innerHTML = '<img src="{{ asset('') }}' + String(data.profile_pic).replace(/^\.\.\//,'') + '" alt="" style="width:56px;height:56px;border-radius:999px;object-fit:cover;">';
            } else {
                avatar.textContent = previewName.charAt(0).toUpperCase();
            }
            show(editModal);
        });
    });

    document.querySelectorAll('[data-image-src]').forEach(img => {
        img.addEventListener('click', function(){
            modalImage.src = img.getAttribute('data-image-src');
            modalImageName.textContent = img.getAttribute('data-image-name') || 'Employee Photo';
            show(imageModal);
        });
    });
    document.getElementById('closeImageModal').addEventListener('click', ()=> hide(imageModal));

    @if($errors->any())
        @if(old('form_mode') === 'edit')
            show(editModal);
        @else
            show(createModal);
        @endif
    @endif

    @if($editEmployee)
        document.querySelector('.open-edit-modal[data-payload]')?.dispatchEvent(new Event('noop'));
        @php
            $payload = [
                'id' => $editEmployee->id,
                'email' => $editEmployee->user?->email,
                'role' => $editEmployee->user?->role,
                'is_active' => (int) ($editEmployee->user?->is_active ?? 1),
                'first_name' => $editEmployee->first_name,
                'middle_name' => $editEmployee->middle_name,
                'last_name' => $editEmployee->last_name,
                'department_id' => $editEmployee->department_id,
                'manager_id' => $editEmployee->manager_id,
                'position' => $editEmployee->position,
                'salary' => $editEmployee->salary,
                'status' => $editEmployee->status,
                'civil_status' => $editEmployee->civil_status,
                'entrance_to_duty' => optional($editEmployee->entrance_to_duty)->format('Y-m-d'),
                'unit' => $editEmployee->unit,
                'gsis_policy_no' => $editEmployee->gsis_policy_no,
                'national_reference_card_no' => $editEmployee->national_reference_card_no,
                'annual_balance' => $editEmployee->annual_balance,
                'sick_balance' => $editEmployee->sick_balance,
                'force_balance' => $editEmployee->force_balance,
                'profile_pic' => $editEmployee->profile_pic,
            ];
        @endphp
        const forcedData = @json($payload);
        editForm.action = '{{ url('/manage-employees') }}/' + forcedData.id;
        ['email','first_name','middle_name','last_name','department_id','manager_id','role','position','salary','status','civil_status','entrance_to_duty','unit','gsis_policy_no','national_reference_card_no','annual_balance','sick_balance','force_balance','password'].forEach(key => setField('edit_'+key, forcedData[key]));
        setField('edit_is_active', forcedData.is_active);
        document.getElementById('editPreviewName').textContent = ((forcedData.first_name || '') + ' ' + (forcedData.middle_name || '') + ' ' + (forcedData.last_name || '')).replace(/\s+/g, ' ').trim();
        document.getElementById('editPreviewMeta').textContent = (departments.find(d => String(d.id) === String(forcedData.department_id || ''))?.name || 'No department') + ' · ' + (forcedData.role || 'employee');
        const avatar = document.getElementById('editPreviewAvatar');
        if (forcedData.profile_pic) { avatar.innerHTML = '<img src="{{ asset('') }}' + String(forcedData.profile_pic).replace(/^\.\.\//,'') + '" alt="" style="width:56px;height:56px;border-radius:999px;object-fit:cover;">'; } else { avatar.textContent = (forcedData.first_name || 'E').charAt(0).toUpperCase(); }
        show(editModal);
    @endif
})();
</script>
@endpush
