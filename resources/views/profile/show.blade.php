@extends('layouts.app')
@section('title', 'Employee Profile - Leave System')
@php
    $actions = [];
    $actions[] = '<a href="'.route('employee-profile', ['employee' => $employeeProfile->id, 'export' => 'history']).'" class="btn btn-secondary">Export History CSV</a>';
    $actions[] = '<a href="'.route('employee-profile', ['employee' => $employeeProfile->id, 'export' => 'leave_card']).'" class="btn btn-ghost">Export Leave Card CSV</a>';
    $actions[] = '<a href="'.route('reports', ['type' => 'leave_card', 'employee_id' => $employeeProfile->id]).'" class="btn btn-ghost">Open Leave Card Report</a>';
    $profileImageUrl = $employeeProfile->profile_pic
        ? asset(ltrim(preg_replace('#^\.\./#', '', (string) $employeeProfile->profile_pic), '/'))
        : null;
@endphp
@push('head')
<style>
.employee-profile-shell{display:flex;flex-direction:column;gap:20px}
.employee-profile-card,.employee-profile-section{background:#fff;border:1px solid var(--border);border-radius:18px;box-shadow:0 6px 16px rgba(15,23,42,.05)}
.employee-profile-card{padding:24px}
.employee-profile-main{display:grid;grid-template-columns:minmax(110px,140px) minmax(0,1fr);gap:22px;align-items:start}
.employee-profile-avatar-wrap{display:flex;flex-direction:column;gap:12px;align-items:flex-start}
.employee-profile-avatar-btn{border:0;background:transparent;padding:0;cursor:pointer;display:inline-flex}
.employee-profile-avatar-img,.employee-profile-avatar-placeholder{width:112px;height:112px;border-radius:28px;overflow:hidden;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 24px rgba(37,99,235,.18);background:linear-gradient(135deg,#2563eb,#1d4ed8)}
.employee-profile-avatar-img{object-fit:cover;background:#fff}
.employee-profile-avatar-placeholder{color:#fff;font-size:42px;font-weight:800}
.employee-profile-name{margin:0;font-size:40px;line-height:1.05}
.employee-profile-email{font-size:15px;color:var(--muted);margin-top:4px}
.employee-profile-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:18px}
.employee-profile-meta-card{padding:14px 16px;border:1px solid var(--border);background:#f8fafc;border-radius:16px;min-width:0}
.employee-profile-meta-label{display:block;font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin-bottom:8px;font-weight:700}
.employee-profile-meta-value{display:block;font-size:15px;font-weight:700;color:var(--text);line-height:1.35;word-break:break-word}
.employee-profile-balance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
.employee-profile-balance-card{padding:18px;border:1px solid var(--border);border-radius:18px;background:linear-gradient(180deg,#ffffff,#f8fbff)}
.employee-profile-actions-note{font-size:13px;color:var(--muted)}
.profile-photo-trigger{font-size:13px;font-weight:600;color:var(--primary)}
.profile-image-modal-content{position:relative;max-width:560px}
.profile-image-modal-figure{display:flex;justify-content:center;align-items:center;background:#f8fafc;border:1px solid var(--border);border-radius:20px;padding:18px;margin:14px 0 10px}
.profile-image-modal-figure img{max-width:100%;max-height:52vh;border-radius:16px;object-fit:contain}
.profile-image-modal-caption{font-size:13px;color:var(--muted);margin-bottom:12px;text-align:center}
.profile-image-modal-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
@media (max-width: 900px){
    .employee-profile-main{grid-template-columns:1fr}
    .employee-profile-avatar-wrap{align-items:center}
    .employee-profile-identity{text-align:center}
    .employee-profile-name{font-size:34px}
}
@media (max-width: 640px){
    .employee-profile-card,.employee-profile-section{padding:18px}
    .employee-profile-name{font-size:30px}
    .employee-profile-meta-grid,.employee-profile-balance-grid{grid-template-columns:1fr}
    .profile-image-modal-actions > *{flex:1 1 100%}
}
</style>
@endpush
@section('content')
@include('partials.page-header', ['title' => 'Employee Profile', 'subtitle' => $employeeProfile->fullName(), 'actions' => $actions])

<div class="employee-profile-shell">
    <div class="employee-profile-card">
        <div class="employee-profile-main">
            <div class="employee-profile-avatar-wrap">
                @if($canEditPhoto)
                    <button type="button" class="employee-profile-avatar-btn" data-open="photoModal" aria-label="Open profile photo">
                        @if($profileImageUrl)
                            <img src="{{ $profileImageUrl }}" alt="{{ $employeeProfile->fullName() }}" class="employee-profile-avatar-img">
                        @else
                            <div class="employee-profile-avatar-placeholder">{{ strtoupper(substr($employeeProfile->first_name ?: $employeeProfile->user?->email ?: 'E', 0, 1)) }}</div>
                        @endif
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-open="photoModal">Change Photo</button>
                @else
                    @if($profileImageUrl)
                        <img src="{{ $profileImageUrl }}" alt="{{ $employeeProfile->fullName() }}" class="employee-profile-avatar-img">
                    @else
                        <div class="employee-profile-avatar-placeholder">{{ strtoupper(substr($employeeProfile->first_name ?: $employeeProfile->user?->email ?: 'E', 0, 1)) }}</div>
                    @endif
                @endif
            </div>
            <div class="employee-profile-identity">
                <h2 class="employee-profile-name">{{ $employeeProfile->fullName() }}</h2>
                <div class="employee-profile-email">{{ $employeeProfile->user?->email ?: '—' }}</div>
                <div class="employee-profile-meta-grid">
                    <div class="employee-profile-meta-card">
                        <span class="employee-profile-meta-label">Department</span>
                        <span class="employee-profile-meta-value">{{ $employeeProfile->department ?: '—' }}</span>
                    </div>
                    <div class="employee-profile-meta-card">
                        <span class="employee-profile-meta-label">Position</span>
                        <span class="employee-profile-meta-value">{{ $employeeProfile->position ?: '—' }}</span>
                    </div>
                    <div class="employee-profile-meta-card">
                        <span class="employee-profile-meta-label">Entrance to Duty</span>
                        <span class="employee-profile-meta-value">{{ optional($employeeProfile->entrance_to_duty)->format('F d, Y') ?: '—' }}</span>
                    </div>
                    <div class="employee-profile-meta-card">
                        <span class="employee-profile-meta-label">Status</span>
                        <span class="employee-profile-meta-value">{{ $employeeProfile->status ?: '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="employee-profile-section" style="padding:22px;">
        <div class="page-header" style="margin-bottom:16px;"><div class="page-title-group"><h3 class="mt-0 mb-0">Leave Balances</h3><p class="page-subtitle">Current balances and all-time used totals based on recorded budget history.</p></div></div>
        @php
            $balanceCards = [
                ['label' => 'Vacational', 'remaining' => (float)$employeeProfile->annual_balance, 'used' => (float)$used['annual']],
                ['label' => 'Sick', 'remaining' => (float)$employeeProfile->sick_balance, 'used' => (float)$used['sick']],
                ['label' => 'Force', 'remaining' => (float)$employeeProfile->force_balance, 'used' => (float)$used['force']],
            ];
        @endphp
        <div class="employee-profile-balance-grid">
            @foreach($balanceCards as $card)
                @php $totalTracked = $card['remaining'] + $card['used']; $pct = $totalTracked > 0 ? max(0,min(100,($card['remaining'] / $totalTracked) * 100)) : 0; @endphp
                <div class="employee-profile-balance-card">
                    <div class="metric-label">{{ $card['label'] }} Balance</div>
                    <div class="metric-value" style="font-size:30px;">{{ number_format($card['remaining'],3) }}</div>
                    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:{{ number_format($pct,2,'.','') }}%"></div></div>
                    <div class="request-kv"><span>Used</span><strong>{{ number_format($card['used'],3) }}</strong></div>
                    <div class="request-kv"><span>Remaining</span><strong>{{ number_format($card['remaining'],3) }}</strong></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="employee-profile-section" style="padding:22px;">
        <div class="page-header" style="margin-bottom:16px;"><div class="page-title-group"><h3 class="mt-0 mb-0">Leave History</h3><p class="page-subtitle">Latest leave requests and their recorded balance snapshots.</p></div></div>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Submitted</th><th>Vac Bal</th><th>Sick Bal</th><th>Force Bal</th><th>Comments</th></tr></thead>
                <tbody>
                @forelse($history as $row)
                    <tr>
                        <td>{{ $row->leave_type_name }}</td>
                        <td>{{ optional($row->start_date)->format('M d, Y') }} - {{ optional($row->end_date)->format('M d, Y') }}</td>
                        <td>{{ number_format((float)$row->total_days, 3) }}</td>
                        <td><span class="badge {{ \App\Support\LeaveFormat::statusClass($row->status, $row->workflow_status) }}">{{ \App\Support\LeaveFormat::statusLabel($row->status, $row->workflow_status) }}</span></td>
                        <td>{{ optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</td>
                        <td>{{ $row->snapshot_annual_balance !== null ? number_format((float)$row->snapshot_annual_balance,3) : '—' }}</td>
                        <td>{{ $row->snapshot_sick_balance !== null ? number_format((float)$row->snapshot_sick_balance,3) : '—' }}</td>
                        <td>{{ $row->snapshot_force_balance !== null ? number_format((float)$row->snapshot_force_balance,3) : '—' }}</td>
                        <td>{{ $row->manager_comments ?: $row->reason ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9">No leave history available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="employee-profile-section" style="padding:22px;">
        <div class="page-header" style="margin-bottom:16px;"><div class="page-title-group"><h3 class="mt-0 mb-0">Budget History</h3><p class="page-subtitle">Recorded balance adjustments, undertime deductions, and accrual history.</p></div></div>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Leave Type</th><th>Action</th><th>Old Balance</th><th>New Balance</th><th>Date</th><th>Notes</th></tr></thead>
                <tbody>
                @forelse($budgetHistory as $row)
                    <tr>
                        <td>{{ $row->leave_type ?: '—' }}</td>
                        <td>{{ ucfirst(str_replace('_',' ',(string)$row->action)) }}</td>
                        <td>{{ number_format((float)$row->old_balance,3) }}</td>
                        <td>{{ number_format((float)$row->new_balance,3) }}</td>
                        <td>{{ optional($row->trans_date)->format('M d, Y') ?: optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</td>
                        <td>{{ $row->notes ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No budget history available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="employee-profile-section" style="padding:22px;">
        <div class="page-header" style="margin-bottom:16px;"><div class="page-title-group"><h3 class="mt-0 mb-0">Leave Card</h3><p class="page-subtitle">Complete transaction history view aligned with the reference leave card report.</p></div></div>
        <div class="table-wrap">
            <table class="clean-table">
                <thead><tr><th>Date</th><th>Particulars</th><th>Vac Earned</th><th>Vac Deducted</th><th>Vac Balance</th><th>Sick Earned</th><th>Sick Deducted</th><th>Sick Balance</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($leaveCard as $row)
                    <tr>
                        <td>{{ $row['date'] ?: '—' }}</td>
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
                    <tr><td colspan="9">No leave card records available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="photoModal" class="modal" style="display:none;">
    <div class="modal-content profile-image-modal-content">
        <span class="modal-close" data-close="photoModal">&times;</span>
        <h3 style="margin-top:0;">Profile Photo</h3>
        <figure class="profile-image-modal-figure">
            @if($profileImageUrl)
                <img id="modalProfileImage" src="{{ $profileImageUrl }}" alt="{{ $employeeProfile->fullName() }}">
            @else
                <img id="modalProfileImage" src="{{ asset('pictures/DEPED-removebg-preview.png') }}" alt="{{ $employeeProfile->fullName() }}">
            @endif
        </figure>
        <div class="profile-image-modal-caption">@if($canEditPhoto)Click Change Photo to preview a new image before saving.@else Click outside the image or use Close to dismiss.@endif</div>
        @if($canEditPhoto)
            <form method="POST" action="{{ route('employee-profile.photo.update') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employeeProfile->id }}">
                <input id="modalProfilePicInput" type="file" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                <div class="profile-image-modal-actions">
                    <button type="button" class="btn btn-secondary" id="choosePhotoBtn">Change Photo</button>
                    <button type="submit" class="btn btn-primary" id="savePhotoBtn" style="display:none;">Save Photo</button>
                    <button type="button" class="btn btn-ghost" id="discardPhotoBtn" style="display:none;">Discard Changes</button>
                    <button type="button" class="btn btn-secondary" data-close="photoModal">Close</button>
                </div>
            </form>
        @else
            <div class="profile-image-modal-actions"><button type="button" class="btn btn-secondary" data-close="photoModal">Close</button></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    function openModal(id){
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.style.display = 'flex';
        modal.classList.add('open');
    }
    function closeModal(id){
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('open');
    }
    document.querySelectorAll('[data-open="photoModal"]').forEach(function(btn){
        btn.addEventListener('click', function(){ openModal('photoModal'); });
    });
    document.querySelectorAll('[data-close="photoModal"]').forEach(function(btn){
        btn.addEventListener('click', function(){ closeModal('photoModal'); });
    });
    var photoModal = document.getElementById('photoModal');
    if (photoModal) {
        photoModal.addEventListener('click', function(e){ if (e.target === photoModal) closeModal('photoModal'); });
    }
    var input = document.getElementById('modalProfilePicInput');
    var img = document.getElementById('modalProfileImage');
    var chooseBtn = document.getElementById('choosePhotoBtn');
    var saveBtn = document.getElementById('savePhotoBtn');
    var discardBtn = document.getElementById('discardPhotoBtn');
    var originalSrc = img ? img.getAttribute('src') : '';
    if (chooseBtn && input) {
        chooseBtn.addEventListener('click', function(){ input.click(); });
    }
    if (input && img) {
        input.addEventListener('change', function(){
            var file = input.files && input.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e){ img.src = e.target.result; };
            reader.readAsDataURL(file);
            if (saveBtn) saveBtn.style.display = 'inline-flex';
            if (discardBtn) discardBtn.style.display = 'inline-flex';
        });
    }
    if (discardBtn && input && img) {
        discardBtn.addEventListener('click', function(){
            input.value = '';
            img.src = originalSrc;
            if (saveBtn) saveBtn.style.display = 'none';
            discardBtn.style.display = 'none';
        });
    }
})();
</script>
@endpush
