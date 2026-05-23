<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_split_id',
        'expense_id',
        'group_id',
        'paid_by',
        'title',
        'description',
        'total_amount',
        'split_details',
        'expense_date',
        'category',
        'receipt_images',
        'user_id',
        'payer_user_id',
        'amount_owed',
        'amount_paid',
        'shares',
        'percentage',
        'split_basis',
        'itemized_details',
        'split_type',
        'is_settled',
        'status',
    ];

    protected $casts = [
        'amount_owed' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'shares' => 'decimal:4',
        'percentage' => 'decimal:2',
        'split_basis' => 'array',
        'itemized_details' => 'array',
        'is_settled' => 'boolean',
        'split_details' => 'array',
        'receipt_images' => 'array',
        'expense_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            $model->expense_split_id = $model->expense_split_id ?: (string) Str::uuid();
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }
}
