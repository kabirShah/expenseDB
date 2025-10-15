<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultiExpenseMember extends Model
{
    protected $fillable = [
        'multi_expense_id',
        'user_id',
        'amount_owed',
        'amount_paid',
        'status',
        'multi_expense_member_id',
    ];

    protected $casts = [
        'amount_owed' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function multiExpense(): BelongsTo
    {
        return $this->belongsTo(MultiExpense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
