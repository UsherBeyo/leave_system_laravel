<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequestForm extends Model
{
    use HasFactory;

    protected $table = 'leave_request_forms';

    protected $fillable = [
        'leave_request_id',
        'office_department',
        'employee_last_name',
        'employee_first_name',
        'employee_middle_name',
        'date_of_filing',
        'position_title',
        'salary',
        'details_of_leave_json',
        'commutation_requested',
        'certification_as_of',
        'cert_vacation_total_earned',
        'cert_vacation_less_this_application',
        'cert_vacation_balance',
        'cert_sick_total_earned',
        'cert_sick_less_this_application',
        'cert_sick_balance',
        'recommendation_status',
        'recommendation_reason',
        'approved_for_days_with_pay',
        'approved_for_days_without_pay',
        'approved_for_others',
        'personnel_signatory_name_a',
        'personnel_signatory_position_a',
        'personnel_signatory_name_c',
        'personnel_signatory_position_c',
    ];

    protected $casts = [
        'date_of_filing' => 'date',
        'certification_as_of' => 'date',
        'salary' => 'float',
        'cert_vacation_total_earned' => 'float',
        'cert_vacation_less_this_application' => 'float',
        'cert_vacation_balance' => 'float',
        'cert_sick_total_earned' => 'float',
        'cert_sick_less_this_application' => 'float',
        'cert_sick_balance' => 'float',
        'approved_for_days_with_pay' => 'float',
        'approved_for_days_without_pay' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
