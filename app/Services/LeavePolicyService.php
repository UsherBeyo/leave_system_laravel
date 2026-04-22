<?php

namespace App\Services;

use App\Models\LeaveType;

class LeavePolicyService
{
    public function normalizeLeaveTypeKey(string $name): string
    {
        $key = strtolower(trim($name));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;
        $key = str_replace([' / ', ' /', '/ '], '/', $key);
        $aliases = [
            'vacation' => 'vacation leave', 'vacational' => 'vacation leave', 'annual' => 'vacation leave',
            'sick' => 'sick leave', 'mandatory/force leave' => 'mandatory/forced leave', 'mandatory force leave' => 'mandatory/forced leave',
            'force' => 'mandatory/forced leave', 'force leave' => 'mandatory/forced leave', 'forced' => 'mandatory/forced leave',
            'forced leave' => 'mandatory/forced leave', 'mandatory leave' => 'mandatory/forced leave', 'mandatory' => 'mandatory/forced leave',
        ];
        return $aliases[$key] ?? $key;
    }

    public function uiPreset(string $typeName, array $usedByYear = []): array
    {
        $key = $this->normalizeLeaveTypeKey($typeName);
        $base = ['bucket'=>'annual','bucket_label'=>'Vacational Balance','secondary_bucket'=>null,'secondary_bucket_label'=>null,'min_days_notice'=>0,'max_days'=>null,'max_days_per_year'=>null,'deduct_balance'=>true,'required_doc_count'=>0,'allow_emergency'=>false,'show_force_balance_only'=>false,'subtype_label'=>'','subtypes'=>[],'show_location_text'=>false,'location_label'=>'Specify','show_illness_text'=>false,'show_other_purpose'=>false,'show_expected_delivery'=>false,'show_calamity_location'=>false,'show_surgery_details'=>false,'show_monetization_reason'=>false,'show_terminal_reason'=>false,'documents'=>[],'rules_text'=>[],'used_by_year'=>$usedByYear];
        return match ($key) {
            'vacation leave' => array_merge($base,['min_days_notice'=>5,'subtype_label'=>'Vacation Details','subtypes'=>['within_ph'=>'Within the Philippines','abroad'=>'Abroad'],'show_location_text'=>true,'location_label'=>'Location / Destination','rules_text'=>['Vacation leave must be filed five (5) days before the start date of leave.','This leave deducts from the Vacational Balance.']]),
            'mandatory/forced leave' => array_merge($base,['bucket'=>'force','bucket_label'=>'Force Balance','secondary_bucket'=>'annual','secondary_bucket_label'=>'Vacational Balance','min_days_notice'=>5,'show_force_balance_only'=>true,'rules_text'=>['Force leave must be filed five (5) days before the start date of leave.','Standard force leave deduction affects both Force Balance and Vacational Balance.','Tick the checkbox for seminar/work-aligned attendance so it deducts only from Force Balance.']]),
            'sick leave' => array_merge($base,['bucket'=>'sick','bucket_label'=>'Sick Balance','allow_emergency'=>true,'subtype_label'=>'Sick Leave Details','subtypes'=>['in_hospital'=>'In Hospital','out_patient'=>'Out Patient'],'show_illness_text'=>true,'documents'=>['medical_certificate'=>'Medical Certificate (required when the sick leave covers more than five (5) continuous working days)'],'required_doc_count'=>1,'rules_text'=>['If the sick leave covers more than five (5) continuous working days, a medical certificate is required.','Sick leave should be filed within one (1) calendar month from the date of leave.','If filed beyond one (1) calendar month, personnel will decide whether it will be recorded as with pay or without pay.']]),
            'maternity leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>105,'show_expected_delivery'=>true,'documents'=>['proof_of_pregnancy'=>'Proof of pregnancy'],'required_doc_count'=>1,'rules_text'=>['Proof of pregnancy attachment is required.','This leave does not deduct from any leave balance.']]),
            'paternity leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>7,'max_days_per_year'=>7,'documents'=>['child_delivery_proof'=>'Proof of child\'s delivery (Birth Certificate or Medical Certificate)','marriage_contract'=>'Marriage Contract'],'required_doc_count'=>2,'rules_text'=>['Paternity leave is limited to seven (7) days per year.','If the total requested or already filed days exceed seven (7), the request should be filed as Vacation Leave instead.','This leave does not deduct from any leave balance.']]),
            'special privilege leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>3,'max_days_per_year'=>3,'min_days_notice'=>5,'subtype_label'=>'Special Privilege Leave Details','subtypes'=>['within_ph'=>'Within the Philippines','abroad'=>'Abroad'],'show_location_text'=>true,'location_label'=>'Location / Destination','documents'=>['special_privilege_supporting_document'=>'Special Privilege Leave supporting document'],'required_doc_count'=>1,'rules_text'=>['Special Privilege Leave must be filed five (5) days before the start date.','Only up to three (3) days may be consumed in one year.','Supporting document attachment is required.','This leave does not deduct from any leave balance.']]),
            'solo parent leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>7,'max_days_per_year'=>7,'min_days_notice'=>5,'documents'=>['solo_parent_id'=>'Solo Parent Identification Card'],'required_doc_count'=>1,'rules_text'=>['Solo Parent Leave is limited to seven (7) days per year.','It must be filed five (5) to seven (7) days before the start date of leave.','Solo Parent Identification Card attachment is required.','This leave does not deduct from any leave balance.']]),
            'study leave' => array_merge($base,['max_days'=>180,'subtype_label'=>'Study Leave Details','subtypes'=>['masters'=>'Completion of Master\'s Degree','bar_review'=>'BAR / Board Examination Review'],'show_other_purpose'=>true,'documents'=>['study_contract'=>'Study Leave Contract'],'required_doc_count'=>1,'rules_text'=>['Study leave can be granted for a maximum of six (6) months.','The study leave contract attachment is required.']]),
            'vawc leave', '10-day vawc leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>10,'max_days_per_year'=>10,'allow_emergency'=>true,'documents'=>['barangay_protection_order'=>'Barangay Protection Order (BPO)','court_protection_order'=>'Temporary/Permanent Protection Order (TPO/PPO)','bpo_tpo_ppo_filing_certification'=>'Certification that the BPO/TPO/PPO application has been filed','police_report_or_medical_certificate'=>'Police Report and/or Medical Certificate'],'required_doc_count'=>1,'rules_text'=>['VAWC leave is limited to ten (10) days.','At least one supporting document from the list is required.','This leave does not deduct from any leave balance.']]),
            'rehabilitation leave', 'rehabilitation privilege' => array_merge($base,['max_days'=>300,'documents'=>['medical_proof'=>'Medical proof / accident report'],'required_doc_count'=>1,'rules_text'=>['Rehabilitation leave may be granted up to three hundred (300) days.','A rehabilitation leave supporting document is required.']]),
            'special leave benefits for women' => array_merge($base,['max_days'=>60,'show_surgery_details'=>true,'documents'=>['medical_certificate'=>'Medical Certificate / surgery certification'],'required_doc_count'=>1,'rules_text'=>['Special Leave Benefits for Women may be granted up to sixty (60) days.','A supporting medical document is required.']]),
            'special emergency (calamity) leave' => array_merge($base,['deduct_balance'=>false,'max_days'=>5,'max_days_per_year'=>5,'show_calamity_location'=>true,'documents'=>['proof_of_calamity'=>'Proof of calamity / residence'],'required_doc_count'=>1,'rules_text'=>['Special Emergency (Calamity) Leave is limited to five (5) days per year.','Proof that the employee is eligible for Special Emergency (Calamity) Leave is required.','This leave does not deduct from any leave balance.']]),
            'monetization of leave credits' => array_merge($base,['deduct_balance'=>false,'show_monetization_reason'=>true,'rules_text'=>['Provide the reason for monetization of leave credits.','This request does not deduct balance through the leave approval workflow.']]),
            'terminal leave' => array_merge($base,['deduct_balance'=>false,'show_terminal_reason'=>true,'documents'=>['proof_of_separation'=>'Proof of retirement / resignation / separation'],'required_doc_count'=>1,'rules_text'=>['Terminal leave requires proof of retirement, resignation, or separation.','This request does not deduct balance through the leave approval workflow.']]),
            'adoption leave' => array_merge($base,['deduct_balance'=>false,'documents'=>['adoption_papers'=>'Adoption papers'],'required_doc_count'=>1,'rules_text'=>['Adoption leave requires supporting adoption papers.','This leave does not deduct from any leave balance.']]),
            default => $base,
        };
    }

    public function policyFromLeaveType(LeaveType|array|string|null $identifier): array
    {
        $name='';$deduct=true;$maxDaysPerYear=null;$autoApprove=false;$leaveTypeId=null;
        if ($identifier instanceof LeaveType) { $name=(string)$identifier->name; $deduct=(bool)$identifier->deduct_balance; $maxDaysPerYear=$identifier->max_days_per_year; $autoApprove=(bool)$identifier->auto_approve; $leaveTypeId=$identifier->id; }
        elseif (is_array($identifier)) { $name=(string)($identifier['name']??''); $deduct=!empty($identifier['deduct_balance']); $maxDaysPerYear=$identifier['max_days_per_year']??null; $autoApprove=!empty($identifier['auto_approve']); $leaveTypeId=$identifier['id']??null; }
        else { $name=(string)$identifier; }
        $preset = $this->uiPreset($name);
        return ['name'=>$name,'leave_type_id'=>$leaveTypeId,'deduct_balance'=>array_key_exists('deduct_balance',$preset)?(bool)$preset['deduct_balance']:$deduct,'min_days_notice'=>(int)($preset['min_days_notice']??0),'max_days'=>isset($preset['max_days'])?(float)$preset['max_days']:null,'max_days_per_year'=>$preset['max_days_per_year']??$maxDaysPerYear,'required_doc_count'=>(int)($preset['required_doc_count']??0),'auto_approve'=>$autoApprove,'preset'=>$preset];
    }
}
