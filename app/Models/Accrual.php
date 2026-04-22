<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Accrual extends Model
{
    use HasFactory;

    protected $table = 'accruals';
    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'amount',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'created_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
