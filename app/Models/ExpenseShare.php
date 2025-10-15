<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExpenseShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_id',
        'expense_id',
        'user_id',
        'amount_owed',
        'amount_paid',
        'share_details',
        'status',
    ];

    protected $casts = [
        'amount_owed' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'share_details' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->share_id)) {
                $model->share_id = Str::uuid();
            }
        });
    }

    // Relationships
    public function expense(): BelongsTo
    {
        return $this->belongsTo(ExpenseCore::class, 'expense_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper methods
    public function getRemainingAmount()
    {
        return $this->amount_owed - $this->amount_paid;
    }

    public function isSettled()
    {
        return $this->status === 'settled' || $this->getRemainingAmount() <= 0;
    }

    public function markAsSettled()
    {
        $this->update(['status' => 'settled']);
    }

    public function addPayment($amount)
    {
        $this->amount_paid += $amount;
        if ($this->amount_paid >= $this->amount_owed) {
            $this->status = 'settled';
        } elseif ($this->amount_paid > 0) {
            $this->status = 'partially_paid';
        }
        $this->save();
    }
}
