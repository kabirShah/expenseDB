<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupExpenseSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_expense_id',
        'member_id',
        'owed_amount',
        'percentage',
        'shares',
        'is_settled',
        'settled_at',
    ];

    protected $casts = [
        'owed_amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function groupExpense()
    {
        return $this->belongsTo(GroupExpense::class, 'group_expense_id');
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }
}