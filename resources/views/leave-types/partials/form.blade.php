<form method="POST" action="{{ $action }}" id="{{ $prefix }}-leave-type-form">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="leave-type-form-grid">
        <div class="leave-type-panel">
            <h4 style="margin:0 0 12px;">Core Settings</h4>
            <div class="field"><label>Name</label><input type="text" name="name" id="{{ $prefix }}_name" value="{{ old('name', $type->name ?? '') }}" required></div>
            <div class="field" style="margin-top:12px;"><label>Law Title</label><input type="text" name="law_title" id="{{ $prefix }}_law_title" value="{{ old('law_title', $type->law_title ?? '') }}"></div>
            <div class="field" style="margin-top:12px;"><label>Max Days Per Year</label><input type="number" step="0.001" name="max_days_per_year" id="{{ $prefix }}_max_days_per_year" value="{{ old('max_days_per_year', $type->max_days_per_year ?? '') }}"></div>
            <div class="field" style="margin-top:12px;"><label>Max Duration Days</label><input type="number" step="0.001" name="max_duration_days" id="{{ $prefix }}_max_duration_days" value="{{ old('max_duration_days', $type->max_duration_days ?? '') }}"></div>
            <div class="field" style="margin-top:12px;"><label>Max Days (int)</label><input type="number" name="max_days" id="{{ $prefix }}_max_days" value="{{ old('max_days', $type->max_days ?? '') }}"></div>
        </div>

        <div class="leave-type-panel">
            <h4 style="margin:0 0 12px;">Deduction Rules</h4>
            <div class="field"><label>Balance Bucket</label>
                <select name="balance_bucket" id="{{ $prefix }}_balance_bucket">
                    @foreach(['annual' => 'Annual / Vacational','sick' => 'Sick','force' => 'Force','none' => 'None'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('balance_bucket', $type->balance_bucket ?? 'annual') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-top:12px;"><label>Deduct Behavior</label>
                <select name="deduct_behavior" id="{{ $prefix }}_deduct_behavior">
                    @foreach(['deduct_full' => 'Deduct Full','no_deduct' => 'Do Not Deduct','with_pay_only' => 'Deduct Only With Pay','force_special' => 'Force Leave Special'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('deduct_behavior', $type->deduct_behavior ?? 'deduct_full') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-top:12px;"><label>Notice (days)</label><input type="number" name="min_days_notice" id="{{ $prefix }}_min_days_notice" value="{{ old('min_days_notice', $type->min_days_notice ?? '') }}"></div>
            <div class="field" style="margin-top:12px;"><label>Advance (days)</label><input type="number" name="min_days_advance" id="{{ $prefix }}_min_days_advance" value="{{ old('min_days_advance', $type->min_days_advance ?? '') }}"></div>
        </div>

        <div class="leave-type-panel full">
            <h4 style="margin:0 0 12px;">Quick Toggles</h4>
            <div class="leave-type-check-grid">
                @foreach([
                    'deduct_balance' => 'Deduct balance',
                    'requires_approval' => 'Requires approval',
                    'auto_approve' => 'Auto approve',
                    'allow_emergency' => 'Allow emergency',
                    'allow_emergency_filing' => 'Allow emergency filing',
                    'allow_half_day' => 'Allow half day',
                    'with_pay_default' => 'With pay by default',
                    'requires_documents' => 'Requires documents',
                    'requires_medical_certificate' => 'Requires medical certificate',
                    'requires_affidavit_if_no_medcert' => 'Requires affidavit if no med cert',
                    'requires_affidavit_if_no_medical' => 'Requires affidavit if no medical',
                    'requires_travel_details' => 'Requires travel details',
                    'requires_proof_of_pregnancy' => 'Requires proof of pregnancy',
                    'requires_marriage_certificate' => 'Requires marriage certificate',
                    'requires_child_delivery_proof' => 'Requires child delivery proof',
                    'requires_solo_parent_id' => 'Requires solo parent ID',
                    'requires_police_report' => 'Requires police report',
                    'requires_barangay_protection_order' => 'Requires barangay protection order',
                    'requires_medical_report' => 'Requires medical report',
                    'requires_letter_request' => 'Requires letter request',
                    'requires_dswd_proof' => 'Requires DSWD proof',
                ] as $field => $label)
                    <label class="inline-check"><input type="checkbox" name="{{ $field }}" id="{{ $prefix }}_{{ $field }}" value="1" @checked(old($field, $type->{$field} ?? false))> <span>{{ $label }}</span></label>
                @endforeach
            </div>
        </div>

        <div class="leave-type-panel full">
            <div class="field full"><label>Law Text</label><textarea name="law_text" id="{{ $prefix }}_law_text">{{ old('law_text', $type->law_text ?? '') }}</textarea></div>
            <div class="field full" style="margin-top:12px;"><label>Rules Text</label><textarea name="rules_text" id="{{ $prefix }}_rules_text">{{ old('rules_text', $type->rules_text ?? '') }}</textarea></div>
            <div class="field full" style="margin-top:12px;"><label>Special Rules Text</label><textarea name="special_rules_text" id="{{ $prefix }}_special_rules_text">{{ old('special_rules_text', $type->special_rules_text ?? '') }}</textarea></div>
            <div class="field full" style="margin-top:12px;"><label>Details Schema JSON</label><textarea name="details_schema_json" id="{{ $prefix }}_details_schema_json">{{ old('details_schema_json', $type->details_schema_json ?? '') }}</textarea></div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
        <button type="submit" class="btn btn-primary">{{ $method === 'POST' ? 'Create Leave Type' : 'Save Changes' }}</button>
    </div>
</form>
