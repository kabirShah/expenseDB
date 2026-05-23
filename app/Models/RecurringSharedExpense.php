<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringSharedExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'created_by',
        'category_id',
        'wallet_id',
        'title',
        'amount',
        'frequency',
        'split_type',
        'payers',
        'participants',
        'start_date',
        'end_date',
        'next_run_at',
        'last_generated_at',
        'status',
        'auto_generate',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payers' => 'array',
        'participants' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'datetime',
        'last_generated_at' => 'datetime',
        'auto_generate' => 'boolean',
        'metadata' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
