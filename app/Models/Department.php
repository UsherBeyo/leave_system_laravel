<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';
    protected $fillable = ['name', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'created_at' => 'datetime'];
    public $timestamps = false;

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function headAssignments()
    {
        return $this->hasMany(DepartmentHeadAssignment::class, 'department_id');
    }

    public function activeHeadAssignments()
    {
        return $this->headAssignments()->where('is_active', 1);
    }
}
