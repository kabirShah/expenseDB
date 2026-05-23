<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitwiseExpenseSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'splitwise_expense_id',
        'member_id',
        'amount_owed',
        'is_settled',
    ];

    protected $casts = [
        'amount_owed' => 'decimal:2',
        'is_settled' => 'boolean',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(SplitwiseExpense::class, 'splitwise_expense_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroupMember::class, 'member_id');
    }
}
