<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveAttachment extends Model
{
    use HasFactory;
    protected $table = 'leave_attachments';
    public $timestamps = false;
    protected $fillable = ['leave_request_id','original_name','stored_name','file_path','mime_type','file_size','document_type','uploaded_by_user_id','created_at'];
    protected $casts = ['file_size' => 'integer', 'created_at' => 'datetime'];
}
