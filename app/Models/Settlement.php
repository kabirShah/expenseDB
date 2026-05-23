<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'from_user_id',
        'to_user_id',
        'related_expense_id',
        'amount',
        'settled_amount',
        'status',
        'method',
        'reference_id',
        'notes',
        'metadata',
        'settled_at',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'metadata' => 'array',
        'settled_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'related_expense_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
