@extends('layouts.app')
@section('title', 'Employee Profile - Leave System')
@php
    $actions = [];
    $actions[] = '<a href="'.route('employee-profile', ['employee' => $employeeProfile->id, 'export' => 'history']).'" class="btn btn-secondary">Export History CSV</a>';
    $actions[] = '<a href="'.route('employee-profile', ['employee' => $employeeProfile->id, 'export' => 'leave_card']).'" class="btn btn-ghost">Export Leave Card Excel</a>';
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
.profile-admin-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:16px}
.profile-admin-actions .btn{white-space:normal}
#balanceModal.modal,#historyModal.modal,#undertimeModal.modal{background:rgba(15,23,42,.58);padding:22px;backdrop-filter:blur(3px);z-index:4200}
.profile-history-modal-content{position:relative;width:min(780px,calc(100vw - 28px));max-width:none;padding:0 !important;border:1px solid rgba(226,232,240,.95);border-radius:26px;background:#fff;box-shadow:0 28px 80px rgba(15,23,42,.28);overflow:hidden}
#historyModal .profile-history-modal-content{width:min(960px,calc(100vw - 28px))}
.profile-history-modal-content .modal-close{top:18px;right:18px;width:38px;height:38px;border-radius:999px;background:rgba(255,255,255,.92);border:1px solid rgba(226,232,240,.9);box-shadow:0 8px 20px rgba(15,23,42,.12);display:inline-flex;align-items:center;justify-content:center;line-height:1;font-size:24px;color:#334155;z-index:3;transition:transform .18s ease,background .18s ease,color .18s ease}
.profile-history-modal-content .modal-close:hover{transform:rotate(90deg);background:#eff6ff;color:#1d4ed8}
.profile-history-modal-content > h3{margin:0 !important;padding:26px 72px 6px 28px;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 55%,#ffffff 100%);font-size:24px;line-height:1.2;color:#0f172a;font-weight:800;letter-spacing:-.02em}
.profile-history-modal-content > h3::before{content:'';display:inline-block;width:10px;height:28px;border-radius:999px;background:linear-gradient(180deg,#2563eb,#16a34a);vertical-align:-7px;margin-right:12px;box-shadow:0 8px 18px rgba(37,99,235,.22)}
.profile-history-modal-content > .profile-modal-note{margin:0 !important;padding:0 72px 24px 50px;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 55%,#ffffff 100%);font-size:13px;color:#64748b;line-height:1.55;border-bottom:1px solid #e2e8f0}
.profile-history-modal-content > form{padding:24px 28px 24px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%)}
.profile-modal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:15px 16px}
.profile-modal-grid .field,.profile-history-modal-content .field{margin:0}
.profile-history-modal-content .field label{display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.055em;color:#475569;margin-bottom:7px}
.profile-history-modal-content input,.profile-history-modal-content select{width:100%;min-height:44px;border:1px solid #cbd5e1;border-radius:14px;background:#fff;padding:10px 13px;color:#0f172a;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}
.profile-history-modal-content input:focus,.profile-history-modal-content select:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12);background:#fff}
.profile-modal-note{font-size:13px;color:var(--muted);margin:8px 0 14px;line-height:1.5}
.profile-history-modal-content form .profile-modal-note{padding:12px 14px;margin:12px 0 0;border:1px solid #dbeafe;border-radius:14px;background:#eff6ff;color:#475569}
.profile-modal-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:22px;padding-top:18px;border-top:1px solid #e2e8f0}
.profile-modal-actions .btn{min-height:42px;border-radius:12px}
.profile-modal-divider{border:0;border-top:1px dashed #cbd5e1;margin:20px 0}
#historyUndertimeFields{border:1px solid #bfdbfe;border-radius:18px;background:linear-gradient(180deg,#eff6ff,#ffffff);padding:16px;margin-top:16px !important}
#historyUndertimeFields > strong{display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;color:#1e3a8a;font-size:14px;text-transform:uppercase;letter-spacing:.04em}
#historyUndertimeFields > strong::before{content:'';width:9px;height:9px;border-radius:999px;background:#2563eb}
.profile-history-modal-content .inline-check{display:inline-flex;align-items:center;gap:10px;margin-top:12px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:999px;background:#fff;font-weight:700;color:#334155}
.profile-history-modal-content .inline-check input{width:17px !important;height:17px !important;min-height:17px;margin:0 !important;accent-color:#2563eb}
@media (max-width: 900px){
    .employee-profile-main{grid-template-columns:1fr}
    .employee-profile-avatar-wrap{align-items:center}
    .employee-profile-identity{text-align:center}
    .employee-profile-name{font-size:34px}
}
@media (max-width: 640px){
    .employee-profile-card,.employee-profile-section{padding:18px}
    .employee-profile-name{font-size:30px}
    .employee-profile-meta-grid,.employee-profile-balance-grid,.profile-modal-grid{grid-template-columns:1fr}
    .profile-image-modal-actions > *, .profile-modal-actions > *{flex:1 1 100%}
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
                    <div class="employee-profile-meta-card"><span class="employee-profile-meta-label">Department</span><span class="employee-profile-meta-value">{{ $employeeProfile->department ?: '—' }}</span></div>
                    <div class="employee-profile-meta-card"><span class="employee-profile-meta-label">Position</span><span class="employee-profile-meta-value">{{ $employeeProfile->position ?: '—' }}</span></div>
                    <div class="employee-profile-meta-card"><span class="employee-profile-meta-label">Entrance to Duty</span><span class="employee-profile-meta-value">{{ optional($employeeProfile->entrance_to_duty)->format('F d, Y') ?: '—' }}</span></div>
                    <div class="employee-profile-meta-card"><span class="employee-profile-meta-label">Status</span><span class="employee-profile-meta-value">{{ $employeeProfile->status ?: '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="employee-profile-section" style="padding:22px;">
        <div class="page-header" style="margin-bottom:16px;"><div class="page-title-group"><h3 class="mt-0 mb-0">Leave Balances</h3><p class="page-subtitle">Current balances and all-time used totals based on recorded budget history.</p></div></div>
        @php
            $balanceCards = [
                ['label' => 'Vacation', 'remaining' => (float)$employeeProfile->annual_balance, 'used' => (float)$used['annual']],
                ['label' => 'Sick', 'remaining' => (float)$employeeProfile->sick_balance, 'used' => (float)$used['sick']],
                ['label' => 'Force', 'remaining' => (float)$employeeProfile->force_balance, 'used' => (float)$used['force']],
                ['label' => 'Wellness', 'remaining' => (float)($employeeProfile->wellness_balance ?? 5), 'used' => 0.0],
                ['label' => 'SPL', 'remaining' => (float)($employeeProfile->spl_balance ?? 3), 'used' => 0.0],
                ['label' => 'CTO', 'remaining' => (float)($employeeProfile->cto_balance ?? 0), 'used' => 0.0],
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

    @if($canManageProfileHistory)
        <div class="employee-profile-section" style="padding:22px;">
            <div class="page-header" style="margin-bottom:12px;">
                <div class="page-title-group"><h3 class="mt-0 mb-0">Admin Actions</h3><p class="page-subtitle">Reference-style profile tools for balance correction, historical entries, and undertime recording.</p></div>
            </div>
            <div class="profile-admin-actions">
                <button type="button" class="btn btn-primary" data-open="balanceModal">Update Balances</button>
                <button type="button" class="btn btn-secondary" data-open="historyModal">Add Leave History Entry</button>
                <button type="button" class="btn btn-ghost" data-open="undertimeModal">Record Undertime</button>
            </div>
        </div>
    @endif

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
                <thead><tr><th>Period</th><th>Particulars</th><th>Vac Earned</th><th>Vac Absence Undertime W/ Pay</th><th>Vac Balance</th><th>Vac Absence Undertime W/o Pay</th><th>Sick Earned</th><th>Sick Absence Undertime W/ Pay</th><th>Sick Balance</th><th>Sick Absence Undertime W/o Pay</th><th>Remarks</th></tr></thead>
                <tbody>
                @forelse($leaveCard as $row)
                    <tr>
                        <td>{{ $row['period'] ?? ($row['date'] ?: '—') }}</td>
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
                    <tr><td colspan="11">No leave card records available.</td></tr>
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

@if($canManageProfileHistory)
<div id="balanceModal" class="modal" style="display:none;">
    <div class="modal-content profile-history-modal-content">
        <span class="modal-close" data-close="balanceModal">&times;</span>
        <h3 style="margin-top:0;">Update Balances</h3>
        <p class="profile-modal-note">Manual adjustments update current balances and create budget-history entries like the reference system.</p>
        <form method="POST" action="{{ route('employee-profile.balances.update') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employeeProfile->id }}">
            <div class="profile-modal-grid">
                <div class="field"><label>Vacation Balance</label><input type="number" step="0.001" name="annual_balance" value="{{ number_format((float)$employeeProfile->annual_balance,3,'.','') }}" required></div>
                <div class="field"><label>Sick Balance</label><input type="number" step="0.001" name="sick_balance" value="{{ number_format((float)$employeeProfile->sick_balance,3,'.','') }}" required></div>
                <div class="field"><label>Force Balance</label><input type="number" step="0.001" name="force_balance" value="{{ number_format((float)$employeeProfile->force_balance,3,'.','') }}" required></div>
                <div class="field"><label>Wellness Balance</label><input type="number" step="0.001" name="wellness_balance" value="{{ number_format((float)($employeeProfile->wellness_balance ?? 5),3,'.','') }}"></div>
                <div class="field"><label>SPL Balance</label><input type="number" step="0.001" name="spl_balance" value="{{ number_format((float)($employeeProfile->spl_balance ?? 3),3,'.','') }}"></div>
                <div class="field"><label>CTO Balance</label><input type="number" step="0.001" max="15" name="cto_balance" value="{{ number_format((float)($employeeProfile->cto_balance ?? 0),3,'.','') }}"></div>
            </div>
            <div class="profile-modal-actions">
                <button type="submit" class="btn btn-primary">Update balances</button>
                <button type="button" class="btn btn-secondary" data-close="balanceModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="historyModal" class="modal" style="display:none;">
    <div class="modal-content profile-history-modal-content">
        <span class="modal-close" data-close="historyModal">&times;</span>
        <h3 style="margin-top:0;">Add Leave History Entry</h3>
        <p class="profile-modal-note">Adds historical leave, accrual, or undertime records without changing current balances, except for ledger/history records required by the reference behavior.</p>
        <form method="POST" action="{{ route('employee-profile.history.store') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employeeProfile->id }}">
            <div class="field">
                <label>Leave Type</label>
                <select id="historyType" name="leave_type_id" required>
                    <option value="0">Vacation Accrual Earned</option>
                    <option value="-1">Undertime</option>
                    @foreach($leaveTypes as $leaveType)
                        <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="profile-modal-grid">
                <div class="field"><label>Earning Amount</label><input id="historyEarningAmount" type="number" step="0.001" name="earning_amount" value=""></div>
                <div class="field"><label>Start Date</label><input type="date" name="start_date" required></div>
                <div class="field"><label>End Date</label><input type="date" name="end_date" required></div>
                <div class="field"><label>Total Days</label><input id="historyTotalDays" type="number" step="0.001" name="total_days" required></div>
            </div>
            <div id="historyUndertimeFields" style="display:none;margin-top:12px;">
                <strong>Undertime Details</strong>
                <div class="profile-modal-grid" style="margin-top:10px;">
                    <div class="field"><label>Hours</label><input type="number" step="1" name="undertime_hours" value="0" min="0"></div>
                    <div class="field"><label>Minutes</label><input type="number" step="1" name="undertime_minutes" value="0" min="0" max="60"></div>
                </div>
                <label class="inline-check" style="margin-top:8px;"><input type="checkbox" name="undertime_with_pay" value="1"> With pay</label>
                <p class="profile-modal-note">Deduction uses the undertime chart. Historical undertime does not mutate the current balance.</p>
            </div>
            <div class="field" style="margin-top:12px;"><label>Comments</label><input type="text" name="reason"></div>
            <hr class="profile-modal-divider">
            <p class="profile-modal-note">Optional: supply balances that were available at the time of this historical entry. Leave blank to use the employee's current balances.</p>
            <div class="profile-modal-grid">
                <div class="field"><label>Vacation balance at time</label><input type="number" step="0.001" name="snapshot_annual_balance" value=""></div>
                <div class="field"><label>Sick balance at time</label><input type="number" step="0.001" name="snapshot_sick_balance" value=""></div>
                <div class="field"><label>Force balance at time</label><input type="number" step="0.001" name="snapshot_force_balance" value=""></div>
            </div>
            <div class="profile-modal-actions">
                <button type="submit" class="btn btn-primary">Add history entry</button>
                <button type="button" class="btn btn-secondary" data-close="historyModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="undertimeModal" class="modal" style="display:none;">
    <div class="modal-content profile-history-modal-content">
        <span class="modal-close" data-close="undertimeModal">&times;</span>
        <h3 style="margin-top:0;">Record Undertime</h3>
        <p class="profile-modal-note">This applies one summed undertime deduction to the current Vacation Balance and logs the month/day dates in the leave card remarks.</p>
        <form method="POST" action="{{ route('employee-profile.undertime.store') }}">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employeeProfile->id }}">
            <div id="undertimeRows" style="display:grid;gap:10px;">
                <div class="profile-modal-grid undertime-row" data-undertime-row>
                    <div class="field"><label>Date</label><input type="date" name="items[0][date]" required></div>
                    <div class="field"><label>Hours</label><input type="number" step="1" name="items[0][hours]" value="0" min="0"></div>
                    <div class="field"><label>Minutes</label><input type="number" step="1" name="items[0][undertime_minutes]" value="0" min="0" max="60"></div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary" id="addUndertimeRow" style="margin-top:10px;">+ New Row</button>
            <label class="inline-check" style="margin-top:12px;"><input type="checkbox" name="with_pay" value="1"> With pay if balance is enough</label>
            <div class="profile-modal-actions">
                <button type="submit" class="btn btn-primary">Apply Deduction</button>
                <button type="button" class="btn btn-secondary" data-close="undertimeModal">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
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
    document.querySelectorAll('[data-open]').forEach(function(btn){
        btn.addEventListener('click', function(){ openModal(btn.getAttribute('data-open')); });
    });
    document.querySelectorAll('[data-close]').forEach(function(btn){
        btn.addEventListener('click', function(){ closeModal(btn.getAttribute('data-close')); });
    });
    document.querySelectorAll('.modal').forEach(function(modal){
        modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(modal.id); });
    });

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

    var historyType = document.getElementById('historyType');
    var historyTotalDays = document.getElementById('historyTotalDays');
    var historyEarningAmount = document.getElementById('historyEarningAmount');
    var historyUndertimeFields = document.getElementById('historyUndertimeFields');
    function updateHistoryForm(){
        if (!historyType) return;
        var value = String(historyType.value || '');
        var isAccrual = value === '0';
        var isUndertime = value === '-1';
        if (historyUndertimeFields) historyUndertimeFields.style.display = isUndertime ? 'block' : 'none';
        if (historyEarningAmount) {
            historyEarningAmount.disabled = !isAccrual;
            historyEarningAmount.required = isAccrual;
            if (!isAccrual) historyEarningAmount.value = '';
        }
        if (historyTotalDays) {
            historyTotalDays.disabled = isAccrual || isUndertime;
            historyTotalDays.required = !(isAccrual || isUndertime);
            if (isAccrual || isUndertime) historyTotalDays.value = '';
        }
    }
    if (historyType) {
        historyType.addEventListener('change', updateHistoryForm);
        updateHistoryForm();
    }

    var undertimeRows = document.getElementById('undertimeRows');
    var addUndertimeRow = document.getElementById('addUndertimeRow');
    if (undertimeRows && addUndertimeRow) {
        addUndertimeRow.addEventListener('click', function(){
            var index = undertimeRows.querySelectorAll('[data-undertime-row]').length;
            var row = document.createElement('div');
            row.className = 'profile-modal-grid undertime-row';
            row.setAttribute('data-undertime-row', '1');
            row.innerHTML = '<div class="field"><label>Date</label><input type="date" name="items['+index+'][date]" required></div>'+
                '<div class="field"><label>Hours</label><input type="number" step="1" name="items['+index+'][hours]" value="0" min="0"></div>'+
                '<div class="field"><label>Minutes</label><input type="number" step="1" name="items['+index+'][undertime_minutes]" value="0" min="0" max="60"></div>'+
                '<div class="field" style="align-self:end;"><button type="button" class="btn btn-danger" data-remove-undertime-row>Remove</button></div>';
            undertimeRows.appendChild(row);
        });
        undertimeRows.addEventListener('click', function(event){
            var removeBtn = event.target.closest('[data-remove-undertime-row]');
            if (removeBtn) removeBtn.closest('[data-undertime-row]')?.remove();
        });
    }

})();
</script>
@endpush
