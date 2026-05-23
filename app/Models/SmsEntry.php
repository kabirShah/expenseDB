<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sender',
        'sms_body',
        'parsed_data',
        'transaction_id',
        'status',
        'is_financial',
        'received_at',
        'source_app',
        'external_id',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'is_financial' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
