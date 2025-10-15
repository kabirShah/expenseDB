<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'suggested_amount',
        'category',
        'description',
        'is_shown',
    ];

    protected $casts = [
        'suggested_amount' => 'decimal:2',
        'is_shown' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeUnseen($query)
    {
        return $query->where('is_shown', false);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
