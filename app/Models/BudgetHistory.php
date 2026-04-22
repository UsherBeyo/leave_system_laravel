<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetHistory extends Model
{
    use HasFactory;

    protected $table = 'budget_history';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'leave_type',
        'action',
        'old_balance',
        'new_balance',
        'notes',
        'leave_id',
        'leave_request_id',
        'trans_date',
        'created_at',
    ];

    protected $casts = [
        'old_balance' => 'float',
        'new_balance' => 'float',
        'trans_date' => 'date',
        'created_at' => 'datetime',
    ];
}
