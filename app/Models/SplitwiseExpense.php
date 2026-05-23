<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SplitwiseExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'splitwise_group_id',
        'paid_by_member_id',
        'created_by',
        'title',
        'description',
        'amount',
        'currency',
        'expense_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroup::class, 'splitwise_group_id');
    }

    public function paidByMember(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroupMember::class, 'paid_by_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(SplitwiseExpenseSplit::class);
    }
}
