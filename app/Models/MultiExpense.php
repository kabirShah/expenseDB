<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MultiExpense extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'total_amount',
        'description',
        'category',
        'split_type',
        'multi_expense_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function multiExpenseMembers(): HasMany
    {
        return $this->hasMany(MultiExpenseMember::class);
    }
}
