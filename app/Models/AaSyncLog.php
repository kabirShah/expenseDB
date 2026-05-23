<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AaSyncLog extends Model
{
    protected $table = 'aa_sync_logs';

    protected $fillable = [
        'user_id',
        'consent_id',
        'status',
        'response_code',
        'message',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * Sync status constants
     */
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';

    /**
     * Get the user that owns this sync log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the consent this sync log belongs to
     */
    public function aaConsent(): BelongsTo
    {
        return $this->belongsTo(AaConsent::class, 'consent_id');
    }

    /**
     * Scope to filter successful syncs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    /**
     * Scope to filter failed syncs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to filter recent syncs
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('synced_at', '>=', now()->subHours($hours));
    }
}