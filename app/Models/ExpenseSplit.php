<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExpenseSplit extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_split_id',
        'group_id',
        'paid_by',
        'title',
        'description',
        'total_amount',
        'split_type',
        'split_details',
        'status',
        'expense_date',
        'category',
        'receipt_images',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'split_details' => 'array',
        'expense_date' => 'datetime',
        'receipt_images' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->expense_split_id)) {
                $model->expense_split_id = Str::uuid();
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

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
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

    // Helper methods
    public function getSplitDetails()
    {
        return collect($this->split_details);
    }

    public function getUserShare($userId)
    {
        $details = $this->getSplitDetails();
        $userSplit = $details->firstWhere('user_id', $userId);

        return $userSplit ? [
            'amount_owed' => $userSplit['amount_owed'] ?? 0,
            'amount_paid' => $userSplit['amount_paid'] ?? 0,
            'status' => $userSplit['status'] ?? 'pending'
        ] : null;
    }

    public function getBalanceForUser($userId)
    {
        $share = $this->getUserShare($userId);

        if (!$share) {
            return 0;
        }

        // Balance = paid_amount - share_amount
        // Positive balance means user is owed money
        // Negative balance means user owes money
        return $share['amount_paid'] - $share['amount_owed'];
    }

    public function isSettled()
    {
        return $this->status === 'settled';
    }

    public function markAsSettled()
    {
        $this->update(['status' => 'settled']);
    }

    public function getUnsettledAmount()
    {
        $totalOwed = $this->getSplitDetails()->sum('amount_owed');
        $totalPaid = $this->getSplitDetails()->sum('amount_paid');

        return $totalOwed - $totalPaid;
    }
}
