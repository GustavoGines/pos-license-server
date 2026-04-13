<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $fillable = [
        'version',
        'download_url',
        'changelog',
        'is_critical',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
    ];
}
