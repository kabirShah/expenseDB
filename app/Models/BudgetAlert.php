<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_plan_id',
        'user_id',
        'alert_type',
        'threshold_percent',
        'spent_amount',
        'budget_amount',
        'budget_period_start',
        'budget_period_end',
        'message',
        'sent_at',
    ];

    protected $casts = [
        'spent_amount' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'budget_period_start' => 'date',
        'budget_period_end' => 'date',
        'sent_at' => 'datetime',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(BudgetPlan::class, 'budget_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
