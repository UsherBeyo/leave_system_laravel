<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentHeadAssignment extends Model
{
    use HasFactory;
    protected $table = 'department_head_assignments';
    protected $fillable = ['department_id','employee_id','is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
