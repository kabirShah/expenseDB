<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitExpense extends Model
{
    use HasFactory;

    protected $table = 'split_expenses';

    protected $fillable = [
        'split_expense_id',
        'user_id',
        'title',
        'total_amount',
        'participants', // JSON array [{user_id, amount_owed, amount_paid, status}]
    ];

    protected $casts = [
        'participants' => 'array',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
