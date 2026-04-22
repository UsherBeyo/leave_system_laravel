<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalanceLog extends Model
{
    use HasFactory;

    protected $table = 'leave_balance_logs';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'change_amount',
        'reason',
        'leave_id',
        'created_at',
    ];

    protected $casts = [
        'change_amount' => 'float',
        'created_at' => 'datetime',
    ];
}
