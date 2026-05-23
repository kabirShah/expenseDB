<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'user_id',
        'onboarding_state_id',
        'status',
        'sync_hash',
        'message_count',
        'processed_count',
        'financial_count',
        'error_message',
        'meta',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onboardingState(): BelongsTo
    {
        return $this->belongsTo(OnboardingState::class);
    }
}
