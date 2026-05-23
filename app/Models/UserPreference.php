<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
      protected $fillable = [
        'user_id',
        'budget_mode',
        'monthly_budget',
        'category_budget',
        'warning_threshold',
        'saving_goal',
        'saving_target',
        'tips_enabled',
        'tips_types',
        'notification_frequency',
        'notify_time',
        'storage_preference',
        'theme_mode',
        'use_system_theme',
        'favorite_categories',
        'setup_wallet_name',
        'setup_wallet_type',
        'setup_wallet_balance',
        'setup_budget_name',
        'setup_budget_amount',
        'setup_budget_period',
        'onboarding_completed',
        'onboarding_completed_at',
    ];

    protected $table='user_preferences';

    protected $casts = [
        'category_budget' => 'array',
        'favorite_categories' => 'array',
        'tips_types' => 'array',
        'tips_enabled' => 'boolean',
        'use_system_theme' => 'boolean',
        'onboarding_completed' => 'boolean',
        'onboarding_completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
