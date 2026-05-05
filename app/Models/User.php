<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';
    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'password',
        'role',
        'is_active',
        'can_approve_leave_requests',
        'activation_token',
        'created_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_approve_leave_requests' => 'boolean',
            'created_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function canApproveLeaveRequests(): bool
    {
        return (bool) $this->can_approve_leave_requests || in_array((string) $this->role, ['admin', 'manager', 'department_head'], true);
    }
}
