<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Balance extends Model
{
    use HasFactory;

    protected $table = 'balances';

    protected $fillable = [
        'balance_id',
        'user_id',
        'source',
        'amount',
        'date_added'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_added' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($balance) {
            if (!$balance->balance_id) {
                $balance->balance_id = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}