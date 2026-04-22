@extends('layouts.app')
@section('title', 'Apply Leave - Leave System')
@php
    $actions = ['<a href="'.route('dashboard').'" class="btn btn-secondary">Back to Dashboard</a>'];
@endphp
@push('head')
<style>
.supporting-doc-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:14px 16px;
    margin-bottom:16px;
    align-items:stretch
}
.supporting-doc-empty{
    grid-column:1 / -1;
    padding:18px 20px;
    border:1px dashed #cfd9ea;
    border-radius:16px;
    background:#f8fbff;
    color:#64748b;
    line-height:1.6
}
.apply-doc-item{
    position:relative;
    display:grid;
    grid-template-columns:20px minmax(0,1fr);
    align-items:start;
    justify-content:stretch;
    column-gap:12px;
    padding:16px 18px;
    border:1px solid #dbe3f0;
    border-radius:16px;
    background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);
    min-height:84px;
    margin:0;
    cursor:pointer;
    transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease, background-color .18s ease
}
.apply-doc-item:hover{
    border-color:#bfd0ee;
    box-shadow:0 10px 24px rgba(15,23,42,.06);
    transform:translateY(-1px)
}
.apply-doc-item:has(input:checked){
    border-color:#2563eb;
    background:#eff6ff;
    box-shadow:0 0 0 3px rgba(37,99,235,.12)
}
.apply-doc-item input{
    width:18px !important;
    height:18px !important;
    margin:2px 0 0 0 !important;
    padding:0 !important;
    accent-color:#2563eb
}
.apply-doc-item span{
    display:block;
    min-width:0;
    text-align:left;
    white-space:normal;
    overflow-wrap:anywhere;
    word-break:normal;
    line-height:1.55;
    font-weight:600;
    color:#1f2937
}
.supporting-doc-flags{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:10px 14px;
    margin-top:14px
}
.supporting-doc-flags .inline-check{
    display:grid;
    grid-template-columns:20px minmax(0,1fr);
    align-items:start;
    justify-content:stretch;
    column-gap:12px;
    padding:10px 0;
    margin:0
}
.supporting-doc-flags .inline-check input{
    width:18px !important;
    height:18px !important;
    margin:2px 0 0 0 !important;
    padding:0 !important
}
.supporting-doc-flags .inline-check span{
    display:block;
    min-width:0;
    line-height:1.55;
    white-space:normal;
    overflow-wrap:anywhere;
    word-break:normal
}
@media (max-width:760px){
    .supporting-doc-grid,
    .supporting-doc-flags{
        grid-template-columns:1fr
    }
}
</style>
@endpush

@section('content')
    @include('partials.page-header', ['title' => 'Apply Leave', 'subtitle' => 'Create a leave request using the same rulings carried over from your original capstone repository.', 'actions' => $actions])

    <div class="ui-card">
        <form method="POST" action="{{ route('leave.apply.store') }}" enctype="multipart/form-data" id="leaveForm" style="display:flex;flex-direction:column;gap:18px;">
            @csrf

            <div class="metric-grid">
                <div class="metric-card"><div class="metric-label">Vacational Balance</div><div class="metric-value">{{ number_format((float)$employee->annual_balance,3) }}</div></div>
                <div class="metric-card"><div class="metric-label">Sick Balance</div><div class="metric-value">{{ number_format((float)$employee->sick_balance,3) }}</div></div>
                <div class="metric-card"><div class="metric-label">Force Balance</div><div class="metric-value">{{ number_format((float)$employee->force_balance,3) }}</div></div>
            </div>

            <div class="form-grid">
                <div class="field"><label>Leave Type</label><select name="leave_type_id" id="leave_type_id" required><option value="">Select leave type</option>@foreach($leaveTypes as $leaveType)<option value="{{ $leaveType->id }}" @selected(old('leave_type_id')==$leaveType->id)>{{ $leaveType->name }}</option>@endforeach</select></div>
                <div class="field"><label>Filing Date</label><input type="date" name="filing_date" id="filing_date" value="{{ old('filing_date', now()->toDateString()) }}" required></div>
                <div class="field"><label>Start Date</label><input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required></div>
                <div class="field"><label>End Date</label><input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required></div>
            </div>

            <div class="metric-grid">
                <div class="metric-card"><div class="metric-label">Working Days</div><div class="metric-value" id="days_value">0</div></div>
                <div class="metric-card"><div class="metric-label">Calendar Days</div><div class="metric-value" id="calendar_days_value">0</div></div>
                <div class="metric-card"><div class="metric-label">Weekend Days</div><div class="metric-value" id="weekend_days_value">0</div></div>
                <div class="metric-card"><div class="metric-label">Holiday Days</div><div class="metric-value" id="holiday_days_value">0</div></div>
            </div>

            <div class="rule-box">
                <strong>Leave type rules</strong>
                <ul id="rule_list" style="margin:10px 0 0 18px;"></ul>
                <div id="rule_warning" class="danger-text" style="display:none;"></div>
            </div>

            <div class="form-grid">
                <div class="field" id="subtype_wrap" style="display:none;"><label id="subtype_label">Details</label><select name="leave_subtype" id="leave_subtype"></select></div>
                <div class="field" id="location_wrap" style="display:none;"><label id="location_label">Location</label><input type="text" name="details[location]" value="{{ old('details.location') }}"></div>
                <div class="field" id="illness_wrap" style="display:none;"><label>Illness / Condition</label><input type="text" name="details[illness]" value="{{ old('details.illness') }}"></div>
                <div class="field" id="other_purpose_wrap" style="display:none;"><label>Other Purpose</label><input type="text" name="details[other_purpose]" value="{{ old('details.other_purpose') }}"></div>
                <div class="field" id="expected_delivery_wrap" style="display:none;"><label>Expected Delivery</label><input type="date" name="details[expected_delivery]" value="{{ old('details.expected_delivery') }}"></div>
                <div class="field" id="calamity_location_wrap" style="display:none;"><label>Calamity Location</label><input type="text" name="details[calamity_location]" value="{{ old('details.calamity_location') }}"></div>
                <div class="field" id="surgery_details_wrap" style="display:none;"><label>Surgery Details</label><input type="text" name="details[surgery_details]" value="{{ old('details.surgery_details') }}"></div>
                <div class="field" id="monetization_reason_wrap" style="display:none;"><label>Monetization Reason</label><input type="text" name="details[monetization_reason]" value="{{ old('details.monetization_reason') }}"></div>
                <div class="field" id="terminal_reason_wrap" style="display:none;"><label>Terminal Leave Reason</label><input type="text" name="details[terminal_reason]" value="{{ old('details.terminal_reason') }}"></div>
            </div>

            <div class="ui-card" style="margin-bottom:0;">
                <h3 style="margin-top:0;">Supporting documents</h3>
                <div id="document_checklist" class="supporting-doc-grid"></div>
                <div class="field"><label>Attachments (up to 5 files, 10MB each)</label><input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"><div class="help-text">Upload the actual file required by the selected leave type. Selecting a supporting-document checkbox alone is not enough when the leave rule requires uploaded attachment(s).</div><div id="attachment_file_list" class="request-chip-list" style="margin-top:10px;"></div></div>
                <div class="supporting-doc-flags">
                    <label class="inline-check"><input type="checkbox" name="medical_certificate_attached" value="1" @checked(old('medical_certificate_attached'))> Medical certificate attached</label>
                    <label class="inline-check"><input type="checkbox" name="affidavit_attached" value="1" @checked(old('affidavit_attached'))> Affidavit attached</label>
                    <label class="inline-check" id="emergency_case_wrap" style="display:none;"><input type="checkbox" name="emergency_case" value="1" @checked(old('emergency_case'))> Emergency case</label>
                    <label class="inline-check" id="force_balance_only_wrap" style="display:none;"><input type="checkbox" name="details[force_balance_only]" value="1" @checked(old('details.force_balance_only'))> Deduct only from Force Balance for seminar/work-aligned attendance</label>
                </div>
            </div>

            <div class="field"><label>Reason / Remarks</label><textarea name="reason">{{ old('reason') }}</textarea></div>
            <div class="page-actions"><button type="submit" class="btn btn-primary">Submit Leave Request</button></div>
        </form>
    </div>
@endsection
@push('scripts')
<script>
const leavePolicyMap=@json($policyMap),leaveType=document.getElementById('leave_type_id'),filingDate=document.getElementById('filing_date'),startDate=document.getElementById('start_date'),endDate=document.getElementById('end_date'),attachmentInput=document.getElementById('attachments'),attachmentFileList=document.getElementById('attachment_file_list'),daysValue=document.getElementById('days_value'),calendarDaysValue=document.getElementById('calendar_days_value'),weekendDaysValue=document.getElementById('weekend_days_value'),holidayDaysValue=document.getElementById('holiday_days_value'),ruleList=document.getElementById('rule_list'),ruleWarning=document.getElementById('rule_warning'),subtypeWrap=document.getElementById('subtype_wrap'),subtypeLabel=document.getElementById('subtype_label'),subtypeSelect=document.getElementById('leave_subtype'),documentChecklist=document.getElementById('document_checklist');
function currentRule(){return leavePolicyMap[leaveType.value]||null} function setWrap(id,visible){const el=document.getElementById(id); if(!el)return; el.style.display=visible?'flex':'none'}
function renderAttachmentList(){ if(!attachmentInput||!attachmentFileList) return; attachmentFileList.innerHTML=''; Array.from(attachmentInput.files||[]).forEach(file=>{ const chip=document.createElement('span'); chip.className='request-chip request-chip-muted'; chip.textContent=file.name; attachmentFileList.appendChild(chip); }); }
function renderRuleUI(){
    const rule=currentRule();
    ruleList.innerHTML='';
    documentChecklist.innerHTML='';
    ['location_wrap','illness_wrap','other_purpose_wrap','expected_delivery_wrap','calamity_location_wrap','surgery_details_wrap','monetization_reason_wrap','terminal_reason_wrap'].forEach(id=>setWrap(id,false));
    setWrap('emergency_case_wrap',false);
    setWrap('force_balance_only_wrap',false);
    subtypeWrap.style.display='none';

    if(!rule){
        ruleWarning.style.display='none';
        return;
    }

    (rule.rules_text||[]).forEach(text=>{
        const li=document.createElement('li');
        li.textContent=text;
        ruleList.appendChild(li)
    });

    if(rule.subtypes&&Object.keys(rule.subtypes).length){
        subtypeWrap.style.display='flex';
        subtypeLabel.textContent=rule.subtype_label||'Details';
        subtypeSelect.innerHTML='<option value="">Select</option>';
        Object.entries(rule.subtypes).forEach(([value,label])=>{
            const opt=document.createElement('option');
            opt.value=value;
            opt.textContent=label;
            subtypeSelect.appendChild(opt)
        })
    }

    if(rule.show_location_text){setWrap('location_wrap',true); document.getElementById('location_label').textContent=rule.location_label||'Location'}
    if(rule.show_illness_text)setWrap('illness_wrap',true);
    if(rule.show_other_purpose)setWrap('other_purpose_wrap',true);
    if(rule.show_expected_delivery)setWrap('expected_delivery_wrap',true);
    if(rule.show_calamity_location)setWrap('calamity_location_wrap',true);
    if(rule.show_surgery_details)setWrap('surgery_details_wrap',true);
    if(rule.show_monetization_reason)setWrap('monetization_reason_wrap',true);
    if(rule.show_terminal_reason)setWrap('terminal_reason_wrap',true);
    if(rule.allow_emergency)setWrap('emergency_case_wrap',true);
    if(rule.show_force_balance_only)setWrap('force_balance_only_wrap',true);

    const documents=Object.entries(rule.documents||{});
    if(documents.length){
        documents.forEach(([key,label])=>{
            const wrap=document.createElement('label');
            wrap.className='apply-doc-item';
            wrap.innerHTML=`<input type="checkbox" name="supporting_documents[${key}]" value="1"><span>${label}</span>`;
            documentChecklist.appendChild(wrap)
        });
    }else{
        const empty=document.createElement('div');
        empty.className='supporting-doc-empty';
        empty.textContent='No leave-type-specific document checklist for the selected leave type.';
        documentChecklist.appendChild(empty);
    }

    updateRuleWarning();
}
function updateRuleWarning(extraMessage=''){ const rule=currentRule(); const warnings=[]; if(extraMessage) warnings.push(extraMessage); if(rule&&rule.min_days_notice&&filingDate?.value&&startDate?.value){ const filing=new Date(filingDate.value+'T00:00:00'); const start=new Date(startDate.value+'T00:00:00'); const diff=Math.floor((start-filing)/(1000*60*60*24)); if(diff < rule.min_days_notice){ warnings.push(`This leave must be filed at least ${rule.min_days_notice} day(s) before the start date.`); } } if(rule&&rule.required_doc_count&&attachmentInput){ const uploaded=Array.from(attachmentInput.files||[]).filter(file=>file && file.name).length; if(uploaded < rule.required_doc_count){ warnings.push(`Upload at least ${rule.required_doc_count} supporting document file(s) for this leave type.`); } } ruleWarning.textContent=warnings.join(' '); ruleWarning.style.display=warnings.length?'block':'none'; }
async function recalcDays(){const start=startDate.value,end=endDate.value; if(!start||!end){ updateRuleWarning(); return; } const params=new URLSearchParams({start_date:start,end_date:end}); const response=await fetch(`{{ route('api.calc-days') }}?${params.toString()}`,{headers:{'X-Requested-With':'XMLHttpRequest'}}); const data=await response.json(); daysValue.textContent=data.days??0; calendarDaysValue.textContent=data.calendar_days??0; weekendDaysValue.textContent=data.weekend_days??0; holidayDaysValue.textContent=data.holiday_days??0; updateRuleWarning(data.message||''); }
leaveType.addEventListener('change',renderRuleUI); filingDate.addEventListener('change',()=>{ updateRuleWarning(); recalcDays(); }); startDate.addEventListener('change',recalcDays); endDate.addEventListener('change',recalcDays); attachmentInput?.addEventListener('change',()=>{ renderAttachmentList(); updateRuleWarning(); }); window.addEventListener('load',()=>{renderRuleUI(); renderAttachmentList(); recalcDays()});
</script>
@endpush