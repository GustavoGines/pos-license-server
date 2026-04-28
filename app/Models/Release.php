<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $fillable = [
        'version',
        'component',
        'download_url',
        'changelog',
        'is_critical',
        'channel',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
    ];
}
