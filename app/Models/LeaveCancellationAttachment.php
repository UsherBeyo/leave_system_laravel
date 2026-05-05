<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCancellationAttachment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'leave_cancellation_id',
        'original_name',
        'stored_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function cancellation()
    {
        return $this->belongsTo(LeaveCancellation::class, 'leave_cancellation_id');
    }
}
