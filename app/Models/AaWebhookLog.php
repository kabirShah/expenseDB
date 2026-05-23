<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AaWebhookLog extends Model
{
    protected $table = 'aa_webhook_logs';

    protected $fillable = [
        'event_type',
        'consent_id',
        'payload',
        'processed',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

    /**
     * Webhook event type constants
     */
    public const EVENT_CONSENT_APPROVED = 'CONSENT_APPROVED';
    public const EVENT_CONSENT_REJECTED = 'CONSENT_REJECTED';
    public const EVENT_CONSENT_EXPIRED = 'CONSENT_EXPIRED';
    public const EVENT_CONSENT_REVOKED = 'CONSENT_REVOKED';
    public const EVENT_DATA_SESSION = 'DATA_SESSION';
    public const EVENT_FI_SESSION = 'FI_SESSION';

    /**
     * Scope to filter unprocessed webhooks
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Mark webhook as processed
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed' => true]);
    }
}