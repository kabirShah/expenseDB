<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $fillable = [
        'notification_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'scheduled_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    const TYPE_BUDGET_ALERT = 'budget_alert';
    const TYPE_EXPENSE_THRESHOLD = 'expense_threshold';
    const TYPE_GOAL_REMINDER = 'goal_reminder';
    const TYPE_MONTHLY_SUMMARY = 'monthly_summary';
    const TYPE_LARGE_TRANSACTION = 'large_transaction';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }
}
