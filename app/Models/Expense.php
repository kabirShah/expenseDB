<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_id',
        'user_id',
        'group_id',
        'linked_transaction_id',
        'split_type',
        'wallet_id',
        'source',
        'source_type',
        'source_ref_id',
        'reference_id',
        'category_id',
        'category_name',
        'merchant_name',
        'payment_method',
        'payment_source',
        'transaction_type',
        'description',
        'amount',
        'currency',
        'date',
        'expense_date',
        'notes',
        'paid_by',
        'location',
        'receipt_url',
        'raw_hash',
        'hash',
        'aa_transaction_id',
        'duplicate_of',
        'is_duplicate',
        'metadata',
        'shared_metadata',
        'duplicate_key',
        'status',
        'is_recurring',
        'recurrence_pattern',
        'next_recurrence_date',
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
        'expense_date' => 'datetime',
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'is_duplicate' => 'boolean',
        'metadata' => 'array',
        'shared_metadata' => 'array',
        'next_recurrence_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($expense) {
            $expense->expense_id = $expense->expense_id ?? (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ExpenseComment::class);
    }

    public function duplicateParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of');
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
        return 'Rs ' . number_format((float) $this->amount, 2);
    }

    public function getIsOverdueAttribute()
    {
        return $this->date->lt(now());
    }

    public function shouldRecur()
    {
        return $this->is_recurring
            && $this->next_recurrence_date
            && $this->next_recurrence_date->lte(now());
    }

    public function scopeByPeriod($query, $period)
    {
        switch ($period) {
            case 'today':
                return $query->whereDate('date', now());

            case 'week':
                return $query->whereBetween('date', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);

            case '6months':
                return $query->whereBetween('date', [
                    now()->subMonths(6)->startOfDay(),
                    now(),
                ]);

            case 'month':
            default:
                return $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
        }
    }
}
