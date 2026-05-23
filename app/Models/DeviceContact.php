<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_contact_id',
        'name',
        'phone',
        'email',
        'matched_user_id',
        'is_registered',
        'is_invited',
        'last_synced_at',
        'metadata',
    ];

    protected $casts = [
        'is_registered' => 'boolean',
        'is_invited' => 'boolean',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }
}
