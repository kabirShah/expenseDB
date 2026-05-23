<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AaTransaction extends Model
{
    protected $table = 'aa_transactions';

    protected $fillable = [
        'user_id',
        'aa_account_id',
        'transaction_id',
        'amount',
        'type',
        'narration',
        'reference',
        'txn_date',
        'value_date',
        'balance_after',
        'category',
        'raw_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'txn_date' => 'datetime',
        'value_date' => 'datetime',
        'raw_data' => 'array',
    ];

    /**
     * Transaction type constants
     */
    public const TYPE_CREDIT = 'CREDIT';
    public const TYPE_DEBIT = 'DEBIT';

    /**
     * Get the user that owns this transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the AA account this transaction belongs to
     */
    public function aaAccount(): BelongsTo
    {
        return $this->belongsTo(AaAccount::class, 'aa_account_id');
    }

    /**
     * Scope to filter credit transactions
     */
    public function scopeCredits($query)
    {
        return $query->where('type', self::TYPE_CREDIT);
    }

    /**
     * Scope to filter debit transactions
     */
    public function scopeDebits($query)
    {
        return $query->where('type', self::TYPE_DEBIT);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('txn_date', [$fromDate, $toDate]);
    }

    /**
     * Check if this transaction is already linked to an expense
     */
    public function isLinkedToExpense(): bool
    {
        return $this->expense !== null;
    }

    /**
     * Get the linked expense if any
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class, 'id', 'aa_transaction_id');
    }
}