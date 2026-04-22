<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;
    protected $table = 'leave_requests';
    const UPDATED_AT = null;

    protected $fillable = [
        'employee_id','department_id','leave_type','leave_type_id','leave_subtype','details_json','filing_date','start_date','end_date',
        'total_days','reason','status','approved_by','manager_comments','snapshot_annual_balance','snapshot_sick_balance',
        'snapshot_force_balance','workflow_status','department_head_user_id','personnel_user_id','department_head_approved_at',
        'personnel_checked_at','finalized_at','department_head_comments','personnel_comments','print_status','commutation',
        'supporting_documents_json','medical_certificate_attached','affidavit_attached','emergency_case'
    ];

    protected $casts = [
        'start_date' => 'date','end_date' => 'date','filing_date' => 'date','created_at' => 'datetime',
        'department_head_approved_at' => 'datetime','personnel_checked_at' => 'datetime','finalized_at' => 'datetime',
        'total_days' => 'float','snapshot_annual_balance' => 'float','snapshot_sick_balance' => 'float','snapshot_force_balance' => 'float',
        'medical_certificate_attached' => 'boolean','affidavit_attached' => 'boolean','emergency_case' => 'boolean',
    ];

    public function employee() { return $this->belongsTo(Employee::class, 'employee_id'); }
    public function leaveTypeRelation() { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function attachments() { return $this->hasMany(LeaveAttachment::class, 'leave_request_id'); }
    public function form() { return $this->hasOne(LeaveRequestForm::class, 'leave_request_id'); }
    public function getLeaveTypeNameAttribute(): string { return $this->leaveTypeRelation?->name ?: (string) $this->leave_type; }
    public function getDetailsMetaAttribute(): array { $decoded = json_decode((string) $this->details_json, true); return is_array($decoded) ? $decoded : []; }
}
