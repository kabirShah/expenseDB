<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ExpenseCore extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_id',
        'payer_id',
        'group_id',
        'title',
        'amount',
        'description',
        'split_type',
        'participants',
        'currency',
        'confidence_score',
        'tags',
        'status',
        'expense_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'participants' => 'array',
        'tags' => 'array',
        'confidence_score' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->expense_id)) {
                $model->expense_id = Str::uuid();
            }
        });
    }

    // Relationships
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function expenseShares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class, 'expense_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'expense_categories');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    public function scopeByGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByPayer($query, $payerId)
    {
        return $query->where('payer_id', $payerId);
    }

    // Helper methods
    public function getParticipantsCount()
    {
        return count($this->participants ?? []);
    }

    public function getTotalShares()
    {
        return $this->expenseShares()->sum('amount_owed');
    }

    public function getUnsettledAmount()
    {
        return $this->amount - $this->expenseShares()->sum('amount_paid');
    }

    public function isSettled()
    {
        return $this->status === 'settled' || $this->getUnsettledAmount() <= 0;
    }

    public function markAsSettled()
    {
        $this->update(['status' => 'settled']);
    }

    public function getFormattedAmount()
    {
        return $this->currency . ' ' . number_format($this->amount, 2);
    }
}
