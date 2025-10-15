<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserBalance extends Model
{
    use HasFactory;

    protected $table = 'user_balances';

    protected $fillable = [
        'user_id',
        'owes_to_user_id',
        'amount',
        'currency_id',
        'original_amount',
        'exchange_rate',
        'group_id',
        'description',
        'last_updated'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'last_updated' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function owesToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owes_to_user_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('owes_to_user_id', $userId);
        });
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopePositive($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('amount', '<', 0);
    }

    // Helper methods
    public function isOwedByUser($userId): bool
    {
        return $this->owes_to_user_id === $userId;
    }

    public function isOwedToUser($userId): bool
    {
        return $this->user_id === $userId;
    }

    public function getNetBalanceForUser($userId)
    {
        if ($this->user_id === $userId) {
            return $this->amount; // User owes this amount
        } elseif ($this->owes_to_user_id === $userId) {
            return -$this->amount; // User is owed this amount
        }
        return 0;
    }

    public function updateAmount($newAmount, $description = null)
    {
        $oldAmount = $this->amount;

        $this->update([
            'amount' => $newAmount,
            'description' => $description ?? $this->description,
            'last_updated' => now()
        ]);

        // Log the change
        TransactionLog::create([
            'transaction_type' => 'balance_update',
            'entity_id' => $this->id,
            'entity_type' => 'balance',
            'user_id' => auth()->id() ?? $this->user_id,
            'group_id' => $this->group_id,
            'amount' => $newAmount - $oldAmount,
            'currency_id' => $this->currency_id,
            'action' => 'updated',
            'old_values' => ['amount' => $oldAmount],
            'new_values' => ['amount' => $newAmount],
            'description' => $description ?? 'Balance updated'
        ]);
    }

    // Static methods for balance management
    public static function getUserNetBalances($userId, $groupId = null)
    {
        $query = self::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('owes_to_user_id', $userId);
        });

        if ($groupId) {
            $query->where('group_id', $groupId);
        }

        $balances = $query->with(['user:id,name,email', 'owesToUser:id,name,email', 'currency'])->get();

        $netBalances = [];
        foreach ($balances as $balance) {
            $otherUserId = $balance->user_id === $userId ? $balance->owes_to_user_id : $balance->user_id;
            $otherUser = $balance->user_id === $userId ? $balance->owesToUser : $balance->user;

            if (!isset($netBalances[$otherUserId])) {
                $netBalances[$otherUserId] = [
                    'user' => $otherUser,
                    'balance' => 0,
                    'currency' => $balance->currency,
                    'group_id' => $balance->group_id
                ];
            }

            $netBalances[$otherUserId]['balance'] += $balance->getNetBalanceForUser($userId);
        }

        return collect($netBalances);
    }

    public static function simplifyDebts($groupId)
    {
        // Get all balances for the group
        $balances = self::forGroup($groupId)->get();

        $debtors = [];
        $creditors = [];

        foreach ($balances as $balance) {
            $net = $balance->getNetBalanceForUser($balance->user_id);
            if ($net > 0) {
                $debtors[$balance->user_id] = ($debtors[$balance->user_id] ?? 0) + $net;
            } elseif ($net < 0) {
                $creditors[$balance->user_id] = ($creditors[$balance->user_id] ?? 0) + abs($net);
            }
        }

        // Implement debt simplification algorithm
        $settlements = [];
        $debtors = collect($debtors)->sortDesc();
        $creditors = collect($creditors)->sortDesc();

        while ($debtors->isNotEmpty() && $creditors->isNotEmpty()) {
            $debtorId = $debtors->keys()->first();
            $debtorAmount = $debtors->shift();

            $creditorId = $creditors->keys()->first();
            $creditorAmount = $creditors->shift();

            $settlementAmount = min($debtorAmount, $creditorAmount);

            $settlements[] = [
                'from_user_id' => $debtorId,
                'to_user_id' => $creditorId,
                'amount' => $settlementAmount
            ];

            // Update remaining amounts
            $remainingDebtor = $debtorAmount - $settlementAmount;
            $remainingCreditor = $creditorAmount - $settlementAmount;

            if ($remainingDebtor > 0) {
                $debtors->prepend($remainingDebtor, $debtorId);
            }
            if ($remainingCreditor > 0) {
                $creditors->prepend($remainingCreditor, $creditorId);
            }
        }

        return $settlements;
    }
}
