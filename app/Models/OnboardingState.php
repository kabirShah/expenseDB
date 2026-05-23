<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingState extends Model
{
    public const SYNC_PENDING = 'pending';
    public const SYNC_IN_PROGRESS = 'in_progress';
    public const SYNC_DONE = 'done';
    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'current_step',
        'sync_status',
        'is_completed',
        'permissions_granted',
        'sync_consent_granted',
        'last_sync_hash',
        'sync_started_at',
        'sync_completed_at',
        'step_payload',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'permissions_granted' => 'boolean',
        'sync_consent_granted' => 'boolean',
        'sync_started_at' => 'datetime',
        'sync_completed_at' => 'datetime',
        'step_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
