<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';
    public $timestamps = false;

    protected $fillable = [
        'user_id','first_name','middle_name','last_name','department','department_id','manager_id','leave_balance',
        'annual_balance','sick_balance','force_balance','profile_pic','position','status','civil_status',
        'entrance_to_duty','unit','gsis_policy_no','national_reference_card_no','salary'
    ];

    protected $casts = [
        'annual_balance' => 'float',
        'sick_balance' => 'float',
        'force_balance' => 'float',
        'leave_balance' => 'float',
        'salary' => 'float',
        'created_at' => 'datetime',
        'entrance_to_duty' => 'date',
    ];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function departmentRelation() { return $this->belongsTo(Department::class, 'department_id'); }
    public function leaveRequests() { return $this->hasMany(LeaveRequest::class, 'employee_id'); }
    public function manager() { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function subordinates() { return $this->hasMany(Employee::class, 'manager_id'); }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function fullName(): string
    {
        return $this->full_name;
    }
}
