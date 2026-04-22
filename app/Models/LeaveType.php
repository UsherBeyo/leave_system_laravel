<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $table = 'leave_types';
    public $timestamps = false;

    protected $fillable = [
        'name','law_title','law_text','deduct_balance','requires_approval','max_days_per_year','auto_approve',
        'balance_bucket','deduct_behavior','max_days','min_days_notice','allow_emergency','requires_documents',
        'requires_medical_certificate','requires_affidavit_if_no_medcert','requires_travel_details','details_schema_json',
        'rules_text','requires_affidavit_if_no_medical','requires_proof_of_pregnancy','requires_marriage_certificate',
        'requires_child_delivery_proof','requires_solo_parent_id','requires_police_report','requires_barangay_protection_order',
        'requires_medical_report','requires_letter_request','requires_dswd_proof','min_days_advance','max_duration_days',
        'allow_emergency_filing','allow_half_day','with_pay_default','special_rules_text',
    ];

    protected $casts = [
        'deduct_balance' => 'boolean',
        'requires_approval' => 'boolean',
        'auto_approve' => 'boolean',
        'allow_emergency' => 'boolean',
        'requires_documents' => 'boolean',
        'requires_medical_certificate' => 'boolean',
        'requires_affidavit_if_no_medcert' => 'boolean',
        'requires_travel_details' => 'boolean',
        'requires_affidavit_if_no_medical' => 'boolean',
        'requires_proof_of_pregnancy' => 'boolean',
        'requires_marriage_certificate' => 'boolean',
        'requires_child_delivery_proof' => 'boolean',
        'requires_solo_parent_id' => 'boolean',
        'requires_police_report' => 'boolean',
        'requires_barangay_protection_order' => 'boolean',
        'requires_medical_report' => 'boolean',
        'requires_letter_request' => 'boolean',
        'requires_dswd_proof' => 'boolean',
        'allow_emergency_filing' => 'boolean',
        'allow_half_day' => 'boolean',
        'with_pay_default' => 'boolean',
        'max_days_per_year' => 'float',
        'max_duration_days' => 'float',
        'max_days' => 'int',
        'min_days_notice' => 'int',
        'min_days_advance' => 'int',
    ];
}
