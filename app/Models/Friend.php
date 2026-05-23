<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Friend extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'friend_user_id',
        'display_name',
        'phone',
        'email',
        'status',
        'is_favorite',
        'usage_count',
        'last_used_at',
        'accepted_at',
        'blocked_at',
        'metadata',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'last_used_at' => 'datetime',
        'accepted_at' => 'datetime',
        'blocked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friendUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_user_id');
    }
}
