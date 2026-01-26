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
        'onboarding_completed'
    ];

    protected $table='user_preferences';

    protected $casts = [
        'category_budget' => 'array',
        'tips_types' => 'array',
        'tips_enabled' => 'boolean',
        'onboarding_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
