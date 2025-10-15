<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebitCard extends Model
{
    use HasFactory;

    protected $table = 'debit_cards';

    protected $fillable = [
        'debit_card_id',
        'user_id',
        'card_number',
        'holder_name',
        'expiry_date',
        'debit_limit',
        'added_date'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'added_date' => 'datetime',
        'debit_limit' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getMaskedCardNumberAttribute()
    {
        return '**** **** **** ' . substr($this->card_number, -4);
    }

    public function getIsExpiredAttribute()
    {
        return now()->gt($this->expiry_date);
    }

    public function getExpiryInMonthsAttribute()
    {
        return now()->diffInMonths($this->expiry_date, false);
    }

    public function scopeActive($query)
    {
        return $query->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<=', now());
    }
}
