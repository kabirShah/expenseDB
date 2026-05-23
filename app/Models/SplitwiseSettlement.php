<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitwiseSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'splitwise_group_id',
        'payer_member_id',
        'payee_member_id',
        'created_by',
        'amount',
        'settled_at',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_at' => 'date',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroup::class, 'splitwise_group_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroupMember::class, 'payer_member_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(SplitwiseGroupMember::class, 'payee_member_id');
    }
}
