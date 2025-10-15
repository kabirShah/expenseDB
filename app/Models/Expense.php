<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'user_id',
        'expense_id',
        'category',
        'transaction_type',
        'description',
        'amount',
        'date',
        'notes',
        'paid_by',
        'location',
        'receipt_url',
        'status',
        'is_recurring',
        'recurrence_pattern',
        'next_recurrence_date'
    ];

    const TRANSACTION_TYPES = [
        'Cash',
        'Credit Card',
        'Debit Card',
        'UPI',
        'Bank Transfer',
        'Mobile Wallet',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_ARCHIVED = 'archived';
    const STATUS_DELETED = 'deleted';

    const RECURRENCE_DAILY = 'daily';
    const RECURRENCE_WEEKLY = 'weekly';
    const RECURRENCE_MONTHLY = 'monthly';
    const RECURRENCE_YEARLY = 'yearly';

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'next_recurrence_date' => 'date'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
    }

    public function getFormattedAmountAttribute()
    {
        return '₹' . number_format($this->amount, 2);
    }

    public function getIsOverdueAttribute()
    {
        return $this->date->lt(now());
    }

    public function shouldRecur()
    {
        return $this->is_recurring && 
               $this->next_recurrence_date && 
               $this->next_recurrence_date->lte(now());
    }
}
