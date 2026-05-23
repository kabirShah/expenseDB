<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'body',
        'is_read',
        'data',
        'member_id',
        'expense_id',
        'channel',
        'type',
        'payload',
        'status',
        'attempts',
        'last_attempt_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'data' => 'array',
        'is_read' => 'boolean',
        'last_attempt_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /*
    |--------------------------------------------------------------------------
    | Business Logic Helpers
    |--------------------------------------------------------------------------
    */

    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markAsFailed()
    {
        $this->update([
            'status' => 'failed',
            'attempts' => $this->attempts + 1,
            'last_attempt_at' => now(),
        ]);
    }

    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }
}
