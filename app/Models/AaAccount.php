<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AaAccount extends Model
{
    protected $table = 'aa_accounts';

    protected $fillable = [
        'user_id',
        'consent_id',
        'account_ref',
        'masked_account_number',
        'bank_name',
        'account_type',
        'ifsc',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Account status constants
     */
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    /**
     * Account type constants
     */
    public const TYPE_SAVINGS = 'SAVINGS';
    public const TYPE_CURRENT = 'CURRENT';

    /**
     * Get the user that owns this account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the consent this account belongs to
     */
    public function aaConsent(): BelongsTo
    {
        return $this->belongsTo(AaConsent::class, 'consent_id');
    }

    /**
     * Get all transactions for this account
     */
    public function aaTransactions(): HasMany
    {
        return $this->hasMany(AaTransaction::class, 'aa_account_id');
    }

    /**
     * Scope to filter active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Check if account needs syncing (older than 1 hour)
     */
    public function needsSync(): bool
    {
        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at->diffInHours(now()) >= 1;
    }

    /**
     * Mark account as synced
     */
    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }
}