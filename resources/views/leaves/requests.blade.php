@extends('layouts.app')
@section('title', 'Leave Requests - Leave System')
@php
    $openDetailId = (int) request('open_detail', 0);
    $badgeClass = function($status, $workflow = null) {
        $status = strtolower((string) $status);
        $workflow = strtolower((string) $workflow);
        if ($workflow === 'pending_personnel' || $workflow === 'pending_department_head' || $workflow === 'returned_by_personnel') return 'badge badge-pending';
        if ($workflow === 'finalized' || $status === 'approved') return 'badge badge-approved';
        if (str_contains($workflow, 'rejected') || $status === 'rejected') return 'badge badge-rejected';
        return 'badge badge-pending';
    };
    $tabs = ($role === 'personnel')
        ? ['pending','approved','rejected']
        : (($role === 'department_head') ? ['all','pending','approved','rejected'] : ['all','pending','approved','rejected','archived']);
    $formatDetailLabel = fn ($key) => ucwords(str_replace('_', ' ', (string) $key));
    $balanceLabels = ['annual' => 'Vacational', 'sick' => 'Sick', 'force' => 'Force'];
@endphp

@push('head')
<style>
.leave-requests-shell{display:flex;flex-direction:column;gap:18px}.filters-card{padding:20px 22px}.filter-actions{display:flex;justify-content:flex-end;align-items:end}.section-intro{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.section-intro p{max-width:840px;margin:0}.requests-count-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-weight:700}.request-list{display:flex;flex-direction:column;gap:14px}
.request-accordion{border:1px solid var(--border);border-radius:22px;background:#fff;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.05)}
.request-accordion summary{list-style:none;cursor:pointer}.request-accordion summary::-webkit-details-marker{display:none}
.request-summary-row{display:grid;grid-template-columns:minmax(250px,1.7fr) minmax(110px,.7fr) minmax(120px,.72fr) minmax(120px,.72fr) minmax(150px,.9fr) minmax(230px,1fr);gap:14px;align-items:center;padding:22px 24px;min-height:112px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%)}
.request-summary-primary{display:flex;align-items:center;gap:12px;min-width:0}.request-avatar{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:800;font-size:21px;display:flex;align-items:center;justify-content:center;flex-shrink:0}.request-summary-name{font-size:16px;font-weight:800;color:var(--text);line-height:1.3}.request-summary-sub{font-size:13px;color:var(--muted);line-height:1.45;white-space:normal;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.request-summary-col{min-width:0}.request-summary-col span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:4px;font-weight:700}.request-summary-col strong{display:block;font-size:14px;color:var(--text);line-height:1.45}.request-summary-row .badge{justify-self:start;white-space:nowrap;display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;min-width:110px;font-size:12px;line-height:1}.request-summary-toggle{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-weight:700;font-size:12px;justify-self:end;white-space:nowrap}.request-accordion[open] .request-summary-toggle{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.request-accordion[open] .request-summary-toggle .summary-chevron{transform:rotate(180deg)}.summary-chevron{transition:transform .2s ease}
.request-card-body{border-top:1px solid var(--border)}.request-card-top{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.9fr);gap:0;border-bottom:1px solid var(--border)}.request-overview{padding:24px 26px;background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%)}.request-sidepanel{padding:24px 26px;background:#f8fafc;border-left:1px solid var(--border);display:flex;flex-direction:column;gap:16px}
.request-person{display:flex;gap:16px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.request-person-main{display:flex;gap:14px;align-items:flex-start;min-width:0}.request-person h3{margin:0 0 4px;font-size:24px}.request-person .help-text{font-size:14px}.request-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:18px}.request-meta-card{padding:14px 16px;border-radius:18px;background:#fff;border:1px solid var(--border);box-shadow:0 4px 14px rgba(15,23,42,.04)}.request-meta-card span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px;font-weight:700}.request-meta-card strong{display:block;font-size:15px;color:var(--text);line-height:1.4}.request-reason-box{margin-top:16px;padding:16px 18px;border-radius:18px;background:#fff;border:1px solid var(--border)}.request-reason-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:6px;font-weight:700}.request-reason-box p{margin:0;color:var(--text);line-height:1.6}.request-top-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.request-stage-note{font-size:13px;color:var(--muted);line-height:1.55}.request-sidecard{padding:16px 18px;border-radius:18px;background:#fff;border:1px solid var(--border)}.request-sidecard h4{margin:0 0 10px;font-size:15px}.request-sidecard .mini-list{display:flex;flex-direction:column;gap:8px}.request-sidecard .mini-row{display:flex;justify-content:space-between;gap:12px;font-size:13px;color:var(--secondary-text)}.request-sidecard .mini-row strong{color:var(--text)}
.balance-visual-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding:22px 24px;background:#fff}.balance-card{border:1px solid var(--border);border-radius:20px;padding:18px 18px 16px;background:#f8fafc;position:relative;overflow:hidden}.balance-card.balance-card-highlight{border-color:#93c5fd;background:linear-gradient(180deg,#eff6ff 0%,#f8fbff 100%);box-shadow:0 10px 28px rgba(37,99,235,.10)}.balance-card-label{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:14px}.balance-card-label strong{font-size:16px}.balance-tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid #dbeafe;color:#1d4ed8;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.balance-values{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center}.balance-value-box{padding:12px 12px;border-radius:16px;background:#fff;border:1px solid var(--border);min-width:0}.balance-value-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:4px;font-weight:700}.balance-value-box strong{display:block;font-size:23px;line-height:1.1;color:var(--text)}.balance-arrow{font-size:26px;color:#94a3b8;font-weight:700;text-align:center}.balance-delta{margin-top:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;font-size:13px;color:var(--secondary-text)}.balance-delta strong{color:var(--text)}.balance-delta-negative{color:#b91c1c;font-weight:700}.balance-delta-neutral{color:#475569;font-weight:700}.balance-impact-note{padding:0 24px 18px;background:#fff}.balance-impact-note p{margin:0;padding:14px 16px;border-radius:16px;background:#f8fafc;border:1px solid var(--border);color:var(--secondary-text)}
.action-sections{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px;padding:0 24px 24px;background:#fff}.action-panel{border:1px solid var(--border);border-radius:20px;padding:18px;background:#f8fafc}.action-panel h4{margin:0 0 12px;font-size:16px}.action-panel p{margin:0 0 14px;font-size:13px;color:var(--muted);line-height:1.55}.request-actions{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:end;margin-top:12px}.request-actions.request-actions-compact{grid-template-columns:1fr}.request-actions .inline-inputs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.request-actions .field{margin:0}.action-row{display:flex;gap:10px;flex-wrap:wrap}.action-row .btn{margin-right:0}.empty-request-state{padding:26px;text-align:center;background:#fff;border:1px dashed var(--border);border-radius:20px;color:var(--muted)}
.request-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:14px}.request-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-top:18px}.request-detail-panel{background:#fff;border:1px solid var(--border);border-radius:18px;padding:18px;box-shadow:0 4px 14px rgba(15,23,42,0.05)}.request-detail-panel h4{margin:0 0 12px;font-size:16px}.request-kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #eef2f7}.request-kv:last-child{border-bottom:none;padding-bottom:0}.request-kv span{color:var(--muted);font-size:13px}.request-kv strong{text-align:right;font-size:14px;color:var(--text)}.request-chip-list{display:flex;flex-wrap:wrap;gap:8px}.request-chip{display:inline-flex;align-items:center;padding:7px 11px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:600;border:1px solid #bfdbfe}.request-chip-muted{background:#f8fafc;color:#475569;border-color:#e2e8f0}.request-note-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.request-note-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px}.request-note-card span{display:block;font-size:12px;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}.request-note-card strong{display:block;font-size:14px;line-height:1.55;color:var(--text);word-break:break-word}.request-detail-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}.request-detail-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px}.request-detail-item span{display:block;font-size:12px;color:var(--muted);margin-bottom:6px}.request-detail-item strong{display:block;font-size:14px;line-height:1.45;color:var(--text);word-break:break-word}
.ls-modal{position:fixed;inset:0;background:rgba(15,23,42,.58);display:none;align-items:center;justify-content:center;padding:24px;z-index:4000}.ls-modal.open{display:flex}.ls-modal-dialog{width:min(1100px,calc(100vw - 32px));max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.25);padding:24px;position:relative}.ls-modal-close{position:absolute;top:14px;right:14px;border:none;background:#f8fafc;border-radius:999px;width:36px;height:36px;font-size:22px;cursor:pointer;color:#334155}.ls-modal-header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding-right:44px;margin-bottom:18px;flex-wrap:wrap}.ls-modal-badge{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700;border:1px solid #bfdbfe}.ls-modal-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:20px}.attachment-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.attachment-preview-body{min-height:220px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:10px;display:flex;align-items:center;justify-content:center}.attachment-preview-frame{width:100%;min-height:70vh;border:none;border-radius:12px;background:#fff}.attachment-preview-image{max-width:100%;max-height:70vh;border-radius:12px;object-fit:contain}.print-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.print-form-grid .field{margin:0}.summary-action-buttons{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
@media (max-width: 1220px){.request-summary-row{grid-template-columns:minmax(180px,1.1fr) repeat(4,minmax(120px,.8fr)) auto;}.request-summary-col.department-col{display:none}}
@media (max-width: 1080px){.request-card-top{grid-template-columns:1fr}.request-sidepanel{border-left:none;border-top:1px solid var(--border)}.balance-visual-grid{grid-template-columns:1fr}.request-summary-row{grid-template-columns:1fr 1fr;}.request-summary-toggle,.summary-action-buttons{justify-self:start}.request-summary-col.hide-medium{display:none}}
@media (max-width: 860px){.request-actions,.request-actions .inline-inputs,.print-form-grid{grid-template-columns:1fr}.filter-actions{justify-content:stretch}.filter-actions .btn{width:100%}.ls-modal{padding:10px}.ls-modal-dialog{width:100%;max-height:calc(100vh - 20px);padding:18px}.request-summary-row{grid-template-columns:1fr;align-items:flex-start}}
@media (max-width: 640px){.request-overview,.request-sidepanel,.balance-visual-grid,.action-sections{padding-left:16px;padding-right:16px}.request-person h3{font-size:20px}.balance-values{grid-template-columns:1fr}.balance-arrow{display:none}.request-top-actions,.section-intro{align-items:stretch}.request-top-actions .btn,.requests-count-pill{width:100%;justify-content:center}}
</style>
@endpush

@section('content')
@include('partials.page-header', ['title' => 'Leave Requests', 'subtitle' => 'Review, track, and manage employee leave submissions', 'actions' => []])
<div class="leave-requests-shell">
    <div class="section-intro">
        <p class="help-text">The compact row layout keeps long request queues easier to scan. Click any row to expand the full balance preview, action panels, uploaded attachments, and request details.</p>
        <div class="requests-count-pill">{{ $rows->total() }} request{{ $rows->total() === 1 ? '' : 's' }} in this view</div>
    </div>

    <div class="tab-links">
        @foreach($tabs as $tabName)
            <a href="{{ route('leave.requests', array_merge(request()->except(['tab','page']), ['tab' => $tabName])) }}" class="{{ $tab === $tabName ? 'active' : '' }}">{{ ucfirst($tabName) }}</a>
        @endforeach
    </div>

    <div class="ui-card filters-card">
        <form method="GET" action="{{ route('leave.requests') }}" class="form-grid" id="leaveRequestFilterForm">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="field" style="grid-column:span 2;"><label>Search This Section</label><input type="text" name="q" id="leaveRequestLiveSearch" value="{{ $search ?? request('q','') }}" placeholder="Search employee, email, leave type, comments, or dates..."></div>
            <div class="field"><label>Month</label><input type="number" name="month" min="0" max="12" value="{{ request('month',0) }}"></div>
            <div class="field"><label>Year</label><input type="number" name="year" min="0" value="{{ request('year',0) }}"></div>
            @if(in_array($role,['admin','personnel','hr']))
                <div class="field"><label>Department</label><select name="department_id"><option value="0">All departments</option>@foreach($departments as $dept)<option value="{{ $dept->id }}" @selected((int)request('department_id') === (int)$dept->id)>{{ $dept->name }}</option>@endforeach</select></div>
            @endif
            <div class="field"><label>Sort By</label><select name="sort_by"><option value="leave" @selected(request('sort_by','leave')==='leave')>Leave date</option><option value="submitted" @selected(request('sort_by')==='submitted')>Submitted</option><option value="forwarded" @selected(request('sort_by')==='forwarded')>Forwarded</option><option value="approved" @selected(request('sort_by')==='approved')>Approved</option></select></div>
            <div class="field"><label>Direction</label><select name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select></div>
            <div class="field filter-actions" style="gap:10px;"><button class="btn btn-primary" type="submit">Apply Filters</button><a href="{{ route('leave.requests', ['tab' => $tab]) }}" class="btn btn-ghost">Clear</a></div>
        </form>
    </div>

    <div class="request-list">
        @forelse($rows as $row)
            @php
                $policy = $leavePolicies[$row->id] ?? ['documents' => []];
                $impact = $approvalImpacts[$row->id] ?? ['mode' => 'projected','current' => ['annual' => 0, 'sick' => 0, 'force' => 0],'before' => ['annual' => 0, 'sick' => 0, 'force' => 0],'after' => ['annual' => 0, 'sick' => 0, 'force' => 0],'deductions' => ['annual' => 0, 'sick' => 0, 'force' => 0],'days_with_pay' => 0,'days_without_pay' => 0,'deduct_days' => 0,'highlight' => [],'notes' => [],'left_label' => 'Current Balance','right_label' => 'After Approval'];
                $selectedDocs = json_decode((string) $row->supporting_documents_json, true); $selectedDocs = is_array($selectedDocs) ? $selectedDocs : [];
                $detailRows=[]; foreach (($row->details_meta ?? []) as $key => $value) { if ($value === null || $value === '' || $value === [] || $value === 0 || $value === '0') continue; $detailRows[]=['label'=>$formatDetailLabel($key),'value'=>is_array($value)?implode(', ', array_map(fn($item)=>(string)$item, $value)):(string)$value]; }
                $supportFlags=[]; foreach($selectedDocs as $docKey){ $supportFlags[]=$policy['documents'][$docKey] ?? $formatDetailLabel($docKey); }
                if($row->medical_certificate_attached) $supportFlags[]='Medical certificate attached'; if($row->affidavit_attached) $supportFlags[]='Affidavit attached'; if($row->emergency_case) $supportFlags[]='Emergency case'; if(!empty(($row->details_meta ?? [])['force_balance_only'])) $supportFlags[]='Deduct only from Force Balance';
                $requestNotes=['Employee Reason'=>trim((string)$row->reason),'Department Head Comment'=>trim((string)$row->department_head_comments),'Personnel Comment'=>trim((string)$row->personnel_comments),'Manager Comment'=>trim((string)$row->manager_comments)];
                $modalId='leaveDetailModal_'.$row->id; $statusText=ucfirst(str_replace('_',' ',(string)($row->workflow_status ?: $row->status))); $employeeName=$row->employee?->fullName() ?: ('Employee #'.$row->employee_id); $avatarText=strtoupper(substr($row->employee?->first_name ?: $employeeName,0,1).substr($row->employee?->last_name ?: '',0,1));
                $leftBalances = $impact['mode'] === 'finalized' ? ($impact['before'] ?? $impact['current']) : ($impact['current'] ?? []);
                $rightBalances = $impact['after'] ?? [];
                $openPrintModalId = 'printModal_'.$row->id;
                $form = $row->form;
            @endphp
            <details class="request-accordion ui-card">
                <summary class="request-summary-row">
                    <div class="request-summary-primary">
                        <div class="request-avatar">{{ $avatarText !== '' ? $avatarText : 'LR' }}</div>
                        <div>
                            <div class="request-summary-name">{{ $employeeName }}</div>
                            <div class="request-summary-sub">{{ $row->leave_type_name }} • {{ $row->employee?->user?->email ?: 'No email on file' }}</div>
                        </div>
                    </div>
                    <div class="request-summary-col department-col"><span>Department</span><strong>{{ $row->employee?->department ?: '—' }}</strong></div>
                    <div class="request-summary-col"><span>Start Leave</span><strong>{{ optional($row->start_date)->format('M d, Y') ?: '—' }}</strong></div>
                    <div class="request-summary-col"><span>End Leave</span><strong>{{ optional($row->end_date)->format('M d, Y') ?: '—' }}</strong></div>
                    <div class="request-summary-col hide-medium"><span>Submitted</span><strong>{{ optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</strong></div>
                    <div class="request-summary-col"><span>Status</span><strong><span class="{{ $badgeClass($row->status,$row->workflow_status) }}">{{ $statusText }}</span></strong></div>
                    <div class="request-summary-toggle">Expand <span class="summary-chevron">▾</span></div>
                </summary>
                <div class="request-card-body">
                    <div class="request-card-top">
                        <div class="request-overview">
                            <div class="request-person">
                                <div class="request-person-main">
                                    <div class="request-avatar">{{ $avatarText !== '' ? $avatarText : 'LR' }}</div>
                                    <div>
                                        <h3>{{ $employeeName }}</h3>
                                        <p class="help-text">{{ $row->employee?->department ?: 'No department' }} • {{ $row->employee?->position ?: 'No position' }}</p>
                                    </div>
                                </div>
                                <div class="summary-action-buttons">
                                    <span class="{{ $badgeClass($row->status,$row->workflow_status) }}">{{ $statusText }}</span>
                                    <button type="button" class="btn btn-secondary" onclick="event.preventDefault();openLeaveModal('{{ $modalId }}')">View Full Request Details</button>
                                    @if(($row->workflow_status === 'finalized' || $row->status === 'approved') && in_array($role,['personnel','hr','admin']))
                                        <button type="button" class="btn btn-primary" onclick="event.preventDefault();openLeaveModal('{{ $openPrintModalId }}')">Customize Signatories &amp; Print</button>
                                    @endif
                                    @if(($row->workflow_status === 'finalized' || $row->status === 'approved'))
                                        <a href="{{ route('leave.print', ['leave' => $row->id]) }}" class="btn btn-ghost" target="_blank" onclick="event.stopPropagation();">Open Print Form</a>
                                    @endif
                                </div>
                            </div>
                            <div class="request-meta-grid">
                                <div class="request-meta-card"><span>Leave Type</span><strong>{{ $row->leave_type_name }}</strong></div>
                                <div class="request-meta-card"><span>Date Range</span><strong>{{ optional($row->start_date)->format('M d, Y') ?: '—' }} to {{ optional($row->end_date)->format('M d, Y') ?: '—' }}</strong></div>
                                <div class="request-meta-card"><span>Total Days</span><strong>{{ number_format((float)$row->total_days,3) }}</strong></div>
                                <div class="request-meta-card"><span>Print Status</span><strong>{{ $row->print_status ?: '—' }}</strong></div>
                            </div>
                            <div class="request-reason-box">
                                <span>Reason / Explanation</span>
                                <p>{{ trim((string) $row->reason) !== '' ? $row->reason : 'No employee reason was entered for this request.' }}</p>
                            </div>
                            <div class="request-summary-grid">
                                <div class="request-meta-card"><span>Filed Date</span><strong>{{ optional($row->filing_date)->format('M d, Y') ?: '—' }}</strong></div>
                                <div class="request-meta-card"><span>Commutation</span><strong>{{ $row->commutation ?: '—' }}</strong></div>
                                <div class="request-meta-card"><span>Submitted</span><strong>{{ optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</strong></div>
                                <div class="request-meta-card"><span>Department</span><strong>{{ $row->employee?->department ?: '—' }}</strong></div>
                            </div>
                        </div>
                        <div class="request-sidepanel">
                            <div class="request-sidecard">
                                <h4>Balance Preview</h4>
                                <p class="request-stage-note">@if($impact['mode'] === 'finalized')These cards show the employee balance before this request was deducted and the recorded final balance saved when Personnel approved it.@else These cards show the employee's current balances now and the projected final balances if this request is approved using the current leave rules.@endif</p>
                            </div>
                            <div class="request-sidecard">
                                <h4>Approval Preview</h4>
                                <div class="mini-list">
                                    <div class="mini-row"><span>With Pay</span><strong>{{ number_format((float)$impact['days_with_pay'],3) }}</strong></div>
                                    <div class="mini-row"><span>Without Pay</span><strong>{{ number_format((float)$impact['days_without_pay'],3) }}</strong></div>
                                    <div class="mini-row"><span>Deduct Days</span><strong>{{ number_format((float)$impact['deduct_days'],3) }}</strong></div>
                                </div>
                            </div>
                            <div class="request-sidecard">
                                <h4>Workflow Notes</h4>
                                <div class="mini-list">
                                    <div class="mini-row"><span>Submitted</span><strong>{{ optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</strong></div>
                                    <div class="mini-row"><span>Dept. Head</span><strong>{{ optional($row->department_head_approved_at)->format('M d, Y h:i A') ?: 'Pending' }}</strong></div>
                                    <div class="mini-row"><span>Personnel</span><strong>{{ optional($row->personnel_checked_at)->format('M d, Y h:i A') ?: 'Pending' }}</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="balance-visual-grid">
                        @foreach($balanceLabels as $bucket => $label)
                            @php
                                $leftValue = (float) ($leftBalances[$bucket] ?? 0);
                                $afterValue = (float) ($rightBalances[$bucket] ?? 0);
                                $deltaValue = (float) ($impact['deductions'][$bucket] ?? 0);
                                $isHighlighted = in_array($bucket, $impact['highlight'] ?? [], true) || $deltaValue > 0;
                            @endphp
                            <div class="balance-card {{ $isHighlighted ? 'balance-card-highlight' : '' }}">
                                <div class="balance-card-label"><strong>{{ $label }} Balance</strong><span class="balance-tag">{{ $isHighlighted ? 'Affected' : 'No Change' }}</span></div>
                                <div class="balance-values">
                                    <div class="balance-value-box"><span>{{ $impact['left_label'] }}</span><strong>{{ number_format($leftValue,3) }}</strong></div>
                                    <div class="balance-arrow">→</div>
                                    <div class="balance-value-box"><span>{{ $impact['right_label'] }}</span><strong>{{ number_format($afterValue,3) }}</strong></div>
                                </div>
                                <div class="balance-delta"><span>Deduction</span>@if($deltaValue > 0)<strong class="balance-delta-negative">-{{ number_format($deltaValue,3) }}</strong>@else<strong class="balance-delta-neutral">0.000</strong>@endif</div>
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($impact['notes']))<div class="balance-impact-note"><p>{{ implode(' ', $impact['notes']) }}</p></div>@endif

                    <div class="action-sections">
                        @if(($row->workflow_status === 'pending_department_head' || blank($row->workflow_status) || $row->workflow_status === 'returned_by_personnel') && in_array($role,['department_head','manager','admin']))
                            <div class="action-panel"><h4>Department Head Review</h4><p>Approve this request to move it to Personnel, or reject it with a clear note for the employee.</p><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact">@csrf<input type="hidden" name="action" value="approve"><div class="field"><label>Department head comments</label><input type="text" name="comments" placeholder="Add a forwarding note for Personnel"></div><button class="btn btn-primary" type="submit">Approve &amp; Forward</button></form></div>
                            <div class="action-panel"><h4>Reject Request</h4><p>Use this only when the request should not proceed to Personnel.</p><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact">@csrf<input type="hidden" name="action" value="reject"><div class="field"><label>Reason for rejection</label><input type="text" name="comments" placeholder="State why the request is being rejected"></div><button class="btn btn-danger" type="submit">Reject</button></form></div>
                        @elseif($row->workflow_status === 'pending_personnel' && in_array($role,['personnel','hr','admin']))
                            <div class="action-panel"><h4>Personnel Final Approval</h4><p>Finalize this request. Leave the day fields blank to use the default full-approval deduction based on the leave rules above.</p><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact">@csrf<input type="hidden" name="action" value="approve"><div class="field"><label>Personnel comments</label><input type="text" name="comments" placeholder="Add a note for the approval record"></div><div class="inline-inputs"><div class="field"><label>Days with pay</label><input type="number" step="0.001" min="0" name="approved_with_pay" placeholder="Default {{ number_format((float)$impact['days_with_pay'],3) }}"></div><div class="field"><label>Days without pay</label><input type="number" step="0.001" min="0" name="approved_without_pay" placeholder="Default {{ number_format((float)$impact['days_without_pay'],3) }}"></div><div class="field"><label>Deduct days</label><input type="number" step="0.001" min="0" name="deduct_days" placeholder="Default {{ number_format((float)$impact['deduct_days'],3) }}"></div></div><button class="btn btn-primary" type="submit">Finalize Approval</button></form></div>
                            <div class="action-panel"><h4>Send Back or Reject</h4><p>Return the request to the Department Head for correction, or reject it completely.</p><div class="action-row" style="margin-bottom:12px;"><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact" style="flex:1 1 260px;">@csrf<input type="hidden" name="action" value="return"><div class="field"><label>Return comments</label><input type="text" name="comments" placeholder="Explain what must be corrected"></div><button class="btn btn-secondary" type="submit">Return to Department Head</button></form><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact" style="flex:1 1 260px;">@csrf<input type="hidden" name="action" value="reject"><div class="field"><label>Reason for rejection</label><input type="text" name="comments" placeholder="State why the request is being rejected"></div><button class="btn btn-danger" type="submit">Reject Request</button></form></div></div>
                        @elseif(($row->workflow_status === 'finalized' || $row->status === 'approved') && in_array($role,['personnel','hr','admin']) && $row->print_status !== 'printed')
                            <div class="action-panel"><h4>Printing Status</h4><p>This request is already approved. Mark it as printed once the leave form or approval document has been released.</p><form method="POST" action="{{ route('leave.requests.action',$row) }}" class="request-actions request-actions-compact">@csrf<input type="hidden" name="action" value="mark_printed"><button class="btn btn-secondary" type="submit">Mark Printed</button></form></div>
                        @endif

                        <div class="action-panel"><h4>Employee Shortcuts</h4><p>Open the employee profile, the employee’s full leave card, or the exact print form layout copied from the legacy capstone system.</p><div class="action-row"><a href="{{ route('employee-profile', ['employee' => $row->employee_id]) }}" class="btn btn-secondary">Employee Profile</a><a href="{{ route('reports', ['type' => 'leave_card', 'employee_id' => $row->employee_id]) }}" class="btn btn-ghost">Open Full Leave Card</a>@if(($row->workflow_status === 'finalized' || $row->status === 'approved'))<a href="{{ route('leave.print', ['leave' => $row->id]) }}" class="btn btn-primary" target="_blank">Print Form</a>@endif</div></div>
                    </div>
                </div>
            </details>

            <div id="{{ $modalId }}" class="ls-modal" onclick="if(event.target===this) closeLeaveModal('{{ $modalId }}')"><div class="ls-modal-dialog"><button type="button" class="ls-modal-close" onclick="closeLeaveModal('{{ $modalId }}')">&times;</button><div class="ls-modal-header"><div><h3 style="margin:0 0 6px;">{{ $employeeName }}</h3><p class="help-text" style="margin:0;">{{ $row->leave_type_name }} • {{ optional($row->start_date)->format('M d, Y') ?: '—' }} to {{ optional($row->end_date)->format('M d, Y') ?: '—' }}</p></div><span class="ls-modal-badge">{{ $statusText }}</span></div>
                <div class="request-detail-grid">
                    <div class="request-detail-panel"><h4>Request Summary</h4><div class="request-kv"><span>Leave Type</span><strong>{{ $row->leave_type_name }}</strong></div><div class="request-kv"><span>Total Days</span><strong>{{ number_format((float)$row->total_days,3) }}</strong></div><div class="request-kv"><span>Commutation</span><strong>{{ $row->commutation ?: '—' }}</strong></div><div class="request-kv"><span>Submitted</span><strong>{{ optional($row->created_at)->format('M d, Y h:i A') ?: '—' }}</strong></div><div class="request-kv"><span>Filed</span><strong>{{ optional($row->filing_date)->format('M d, Y') ?: '—' }}</strong></div></div>
                    <div class="request-detail-panel"><h4>Employee Information</h4><div class="request-kv"><span>Employee ID</span><strong>{{ $row->employee_id }}</strong></div><div class="request-kv"><span>Department</span><strong>{{ $row->employee?->department ?: '—' }}</strong></div><div class="request-kv"><span>Position</span><strong>{{ $row->employee?->position ?: '—' }}</strong></div><div class="request-kv"><span>Approved At</span><strong>{{ optional($row->department_head_approved_at)->format('M d, Y h:i A') ?: '—' }}</strong></div><div class="request-kv"><span>Personnel Checked</span><strong>{{ optional($row->personnel_checked_at)->format('M d, Y h:i A') ?: '—' }}</strong></div></div>
                    <div class="request-detail-panel"><h4>Balance Comparison</h4>@foreach($balanceLabels as $bucket => $label)<div class="request-kv"><span>{{ $label }} {{ $impact['left_label'] }}</span><strong>{{ number_format((float)($leftBalances[$bucket] ?? 0),3) }}</strong></div><div class="request-kv"><span>{{ $label }} {{ $impact['right_label'] }}</span><strong>{{ number_format((float)($rightBalances[$bucket] ?? 0),3) }}</strong></div>@endforeach</div>
                </div>
                @if(!empty($detailRows))<div class="request-detail-panel" style="margin-top:18px;"><h4>Leave-Specific Details</h4><div class="request-detail-list">@foreach($detailRows as $detail)<div class="request-detail-item"><span>{{ $detail['label'] }}</span><strong>{{ $detail['value'] }}</strong></div>@endforeach</div></div>@endif
                <div class="request-detail-panel" style="margin-top:18px;"><h4>Comments &amp; Notes</h4><div class="request-note-grid">@php $hasNotes=false; @endphp @foreach($requestNotes as $label => $value) @continue($value === '') @php $hasNotes=true; @endphp <div class="request-note-card"><span>{{ $label }}</span><strong>{!! nl2br(e($value)) !!}</strong></div>@endforeach @unless($hasNotes)<p class="help-text">No notes or review comments recorded yet.</p>@endunless</div></div>
                @if(!empty($supportFlags))<div class="request-detail-panel" style="margin-top:18px;"><h4>Supporting Documents &amp; Flags</h4><p class="help-text" style="margin:0 0 10px;">Selected request indicators and supporting document types recorded on the leave request.</p><div class="request-chip-list">@foreach($supportFlags as $flag)<span class="request-chip request-chip-muted">{{ $flag }}</span>@endforeach</div></div>@endif
                <div class="request-detail-panel" style="margin-top:18px;"><h4>Uploaded Attachments</h4>@if($row->attachments->isNotEmpty())<div class="request-note-grid">@foreach($row->attachments as $attachment) @php $mime=strtolower((string)$attachment->mime_type); $isPreviewable=str_starts_with($mime,'image/') || $mime==='application/pdf'; $fileUrl=asset($attachment->file_path); @endphp <div class="request-note-card"><span>{{ $attachment->document_type ?: 'supporting_document' }}</span><strong>{{ $attachment->original_name }}</strong><small class="help-text">{{ $attachment->file_size ? number_format($attachment->file_size / 1024 / 1024, 2).' MB' : '—' }}</small><div class="attachment-actions">@if($isPreviewable)<button type="button" class="btn btn-secondary" onclick="openAttachmentPreview(@js($fileUrl), @js($attachment->original_name), @js($mime))">Preview</button>@endif<a class="btn btn-primary" href="{{ $fileUrl }}" target="_blank" rel="noopener">Open File</a></div></div>@endforeach</div>@else<p class="help-text">No uploaded attachment file is linked to this request yet. The chips above only show selected request flags or document types, not actual uploaded files.</p>@endif</div>
                <div class="ls-modal-actions"><button type="button" class="btn btn-secondary" onclick="closeLeaveModal('{{ $modalId }}')">Close</button></div>
            </div></div>

            @if(($row->workflow_status === 'finalized' || $row->status === 'approved') && in_array($role,['personnel','hr','admin']))
                <div id="{{ $openPrintModalId }}" class="ls-modal" onclick="if(event.target===this) closeLeaveModal('{{ $openPrintModalId }}')">
                    <div class="ls-modal-dialog" style="width:min(560px,calc(100vw - 32px));">
                        <button type="button" class="ls-modal-close" onclick="closeLeaveModal('{{ $openPrintModalId }}')">&times;</button>
                        <div class="ls-modal-header"><div><h3 style="margin:0 0 6px;">Customize Signatories</h3><p class="help-text" style="margin:0;">{{ $employeeName }} • {{ $row->leave_type_name }}</p></div><span class="ls-modal-badge">Save &amp; Print</span></div>
                        <form method="POST" action="{{ route('leave.print.signatories', ['leave' => $row->id]) }}" target="_blank">
                            @csrf
                            <div class="print-form-grid">
                                <div class="field"><label>7.A Name</label><input type="text" name="name_a" value="{{ $form?->personnel_signatory_name_a ?: ($signatories['certification']->name ?? '') }}" required></div>
                                <div class="field"><label>7.A Position</label><input type="text" name="position_a" value="{{ $form?->personnel_signatory_position_a ?: ($signatories['certification']->position ?? '') }}" required></div>
                                <div class="field"><label>7.C Name</label><input type="text" name="name_c" value="{{ $form?->personnel_signatory_name_c ?: ($signatories['final_approver']->name ?? '') }}" required></div>
                                <div class="field"><label>7.C Position</label><input type="text" name="position_c" value="{{ $form?->personnel_signatory_position_c ?: ($signatories['final_approver']->position ?? '') }}" required></div>
                            </div>
                            <div class="ls-modal-actions"><button type="submit" class="btn btn-primary">Save &amp; Print</button><button type="button" class="btn btn-secondary" onclick="closeLeaveModal('{{ $openPrintModalId }}')">Cancel</button></div>
                        </form>
                    </div>
                </div>
            @endif
        @empty
            <div class="empty-request-state">No leave requests found for this filter.</div>
        @endforelse
    </div>

    {{ $rows->onEachSide(1)->links('vendor.pagination.clean') }}
</div>

<div id="attachmentPreviewModal" class="ls-modal" onclick="if(event.target===this) closeAttachmentPreview()"><div class="ls-modal-dialog" style="width:min(960px,calc(100vw - 32px));"><button type="button" class="ls-modal-close" onclick="closeAttachmentPreview()">&times;</button><div class="ls-modal-header"><div><h3 id="attachmentPreviewTitle" style="margin:0 0 6px;">Attachment Preview</h3><p class="help-text" style="margin:0;">Quick preview for uploaded request attachments.</p></div><span class="ls-modal-badge">Attachment Preview</span></div><div id="attachmentPreviewBody" class="attachment-preview-body"></div><div class="ls-modal-actions"><button type="button" class="btn btn-secondary" onclick="closeAttachmentPreview()">Close</button><a id="attachmentPreviewOpenLink" class="btn btn-primary" href="#" target="_blank" rel="noopener">Open in New Tab</a></div></div></div>
@endsection

@push('scripts')
<script>
function openLeaveModal(id){ const modal=document.getElementById(id); if(modal) modal.classList.add('open'); }
function closeLeaveModal(id){ const modal=document.getElementById(id); if(modal) modal.classList.remove('open'); }
function openAttachmentPreview(url,name,mimeType){ const modal=document.getElementById('attachmentPreviewModal'); const title=document.getElementById('attachmentPreviewTitle'); const body=document.getElementById('attachmentPreviewBody'); const link=document.getElementById('attachmentPreviewOpenLink'); if(!modal||!title||!body||!link) return; title.textContent=name||'Attachment Preview'; body.innerHTML=''; link.href=url||'#'; const mime=(mimeType||'').toLowerCase(); if(mime==='application/pdf'){ const frame=document.createElement('iframe'); frame.className='attachment-preview-frame'; frame.src=url; frame.title=name||'Attachment Preview'; body.appendChild(frame); } else if(mime.startsWith('image/')) { const img=document.createElement('img'); img.className='attachment-preview-image'; img.src=url; img.alt=name||'Attachment Preview'; body.appendChild(img); } else { const p=document.createElement('p'); p.textContent='This file type cannot be previewed inline. Use “Open in New Tab” instead.'; body.appendChild(p); } modal.classList.add('open'); }
function closeAttachmentPreview(){ const modal=document.getElementById('attachmentPreviewModal'); const body=document.getElementById('attachmentPreviewBody'); if(body) body.innerHTML=''; if(modal) modal.classList.remove('open'); }
(function(){
    const searchInput = document.getElementById('leaveRequestLiveSearch');
    const filterForm = document.getElementById('leaveRequestFilterForm');
    if (!searchInput || !filterForm) return;
    let timer = null;
    searchInput.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(function(){ filterForm.submit(); }, 450);
    });
})();
</script>
@endpush
