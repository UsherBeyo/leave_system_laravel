<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSignatory extends Model
{
    use HasFactory;

    protected $table = 'system_signatories';
    public $timestamps = false;

    protected $fillable = [
        'key_name',
        'name',
        'position',
    ];
}
