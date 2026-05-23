<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_id',
        'user_id',
        'comment',
        'reaction',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
