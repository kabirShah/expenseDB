<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionLog extends Model
{
    use HasFactory;

    protected $table = 'transactions_log';

    protected $fillable = [
        'transaction_type',
        'entity_id',
        'entity_type',
        'user_id',
        'group_id',
        'amount',
        'currency_id',
        'original_amount',
        'exchange_rate',
        'action',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'old_values' => 'array',
        'new_values' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByEntity($query, $entityType, $entityId = null)
    {
        $query->where('entity_type', $entityType);
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        return $query;
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Helper methods
    public function getEntity()
    {
        $modelClass = 'App\\Models\\' . ucfirst($this->entity_type);
        if (class_exists($modelClass)) {
            return $modelClass::find($this->entity_id);
        }
        return null;
    }

    public function getChangeDescription()
    {
        $changes = [];

        if ($this->old_values && $this->new_values) {
            foreach ($this->new_values as $key => $newValue) {
                $oldValue = $this->old_values[$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[] = "{$key}: {$oldValue} → {$newValue}";
                }
            }
        }

        return $changes ? implode(', ', $changes) : 'No changes recorded';
    }

    // Static methods for logging
    public static function logExpense($expenseId, $userId, $groupId, $amount, $action = 'created', $oldValues = null, $newValues = null, $description = null)
    {
        return self::create([
            'transaction_type' => 'expense',
            'entity_id' => $expenseId,
            'entity_type' => 'expense',
            'user_id' => $userId,
            'group_id' => $groupId,
            'amount' => $amount,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description ?? "Expense {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logSettlement($settlementId, $userId, $groupId, $amount, $action = 'created', $oldValues = null, $newValues = null, $description = null)
    {
        return self::create([
            'transaction_type' => 'settlement',
            'entity_id' => $settlementId,
            'entity_type' => 'settlement',
            'user_id' => $userId,
            'group_id' => $groupId,
            'amount' => $amount,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description ?? "Settlement {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logBalanceUpdate($balanceId, $userId, $groupId, $amount, $action = 'updated', $oldValues = null, $newValues = null, $description = null)
    {
        return self::create([
            'transaction_type' => 'balance_update',
            'entity_id' => $balanceId,
            'entity_type' => 'balance',
            'user_id' => $userId,
            'group_id' => $groupId,
            'amount' => $amount,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description ?? "Balance {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    public static function logInterest($entityId, $userId, $groupId, $amount, $description = null)
    {
        return self::create([
            'transaction_type' => 'interest',
            'entity_id' => $entityId,
            'entity_type' => 'settlement',
            'user_id' => $userId,
            'group_id' => $groupId,
            'amount' => $amount,
            'action' => 'accrued',
            'description' => $description ?? 'Interest accrued',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
