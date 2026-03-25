<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'client_name',
        'api_key',
        'plan_type',
        'is_active',
        'expiration_date',
        'allowed_addons',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expiration_date' => 'date',
        'allowed_addons' => 'array',
    ];

    /**
     * Automatically generate UUID and API Key on creation if empty
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (empty($license->uuid)) {
                $license->uuid = (string) Str::uuid();
            }
            if (empty($license->api_key)) {
                $license->api_key = 'pos_' . Str::random(32);
            }
        });
    }
}
