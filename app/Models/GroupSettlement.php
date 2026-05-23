<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GroupSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'payer_id',
        'payee_id',
        'amount',
        'notes',
        'settled_at',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(ExpenseGroup::class, 'group_id');
    }

    public function payer()
    {
        return $this->belongsTo(GroupMember::class, 'payer_id');
    }

    public function payee()
    {
        return $this->belongsTo(GroupMember::class, 'payee_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}