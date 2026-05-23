<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AaConsent extends Model
{
    protected $table = 'aa_consents';

    protected $fillable = [
        'user_id',
        'consent_id',
        'consent_handle',
        'status',
        'purpose_code',
        'purpose_text',
        'fi_types',
        'data_from',
        'data_to',
        'frequency_unit',
        'frequency_value',
        'expiry_at',
        'approved_at',
        'revoked_at',
        'raw_response',
    ];

    protected $casts = [
        'fi_types' => 'array',
        'data_from' => 'date',
        'data_to' => 'date',
        'expiry_at' => 'datetime',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
        'raw_response' => 'array',
    ];

    /**
     * Consent status constants
     */
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REVOKED = 'REVOKED';

    /**
     * FI Types constants
     */
    public const FI_TRANSACTIONS = 'TRANSACTIONS';
    public const FI_BALANCES = 'BALANCES';
    public const FI_PROFILE = 'PROFILE';
    public const FI_SUMMARY = 'SUMMARY';

    /**
     * Frequency unit constants
     */
    public const FREQUENCY_DAY = 'DAY';
    public const FREQUENCY_MONTH = 'MONTH';

    /**
     * Get the user that owns this consent
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all bank accounts linked to this consent
     */
    public function aaAccounts(): HasMany
    {
        return $this->hasMany(AaAccount::class, 'consent_id');
    }

    /**
     * Check if consent is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED]) && 
               ($this->expiry_at === null || $this->expiry_at->isFuture());
    }

    /**
     * Check if consent is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_at !== null && $this->expiry_at->isPast();
    }

    /**
     * Scope to filter by status
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where(function ($q) {
                $q->whereNull('expiry_at')
                    ->orWhere('expiry_at', '>', now());
            });
    }

    /**
     * Scope to filter pending consents
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}