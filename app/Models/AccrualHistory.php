<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccrualHistory extends Model
{
    use HasFactory;

    protected $table = 'accrual_history';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'amount',
        'date_accrued',
        'month_reference',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'date_accrued' => 'date',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
