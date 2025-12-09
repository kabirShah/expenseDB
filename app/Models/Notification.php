<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'expense_id',
        'channel',
        'type',
        'payload',
        'status',
        'attempts',
        'last_attempt_at'
    ];

    protected $casts = [
        'payload' => 'array'
    ];

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }
}
