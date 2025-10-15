<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'group_id',
        'paid_by',
        'paid_to',
        'amount',
        'currency_id',
        'interest_rate',
        'due_date',
        'interest_accrued',
        'interest_last_calculated',
        'payment_method',
        'metadata',
        'description',
        'status',
        'settled_at',
        'related_expenses',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_accrued' => 'decimal:2',
        'settled_at' => 'datetime',
        'due_date' => 'date',
        'interest_last_calculated' => 'datetime',
        'related_expenses' => 'array',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->settlement_id)) {
                $model->settlement_id = Str::uuid();
            }
        });
    }

    // Relationships
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_to');
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Helper methods
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'settled_at' => now()
        ]);
    }

    public function getRelatedExpenseIds()
    {
        return $this->related_expenses ?? [];
    }

    public function getRelatedExpenses()
    {
        if (empty($this->related_expenses)) {
            return collect();
        }

        return ExpenseSplit::whereIn('id', $this->related_expenses)->get();
    }
}
