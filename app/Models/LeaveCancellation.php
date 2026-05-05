<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCancellation extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_request_id',
        'employee_id',
        'requested_by_user_id',
        'reason',
        'status',
        'reviewed_by_user_id',
        'personnel_comments',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function attachments()
    {
        return $this->hasMany(LeaveCancellationAttachment::class, 'leave_cancellation_id');
    }
}
