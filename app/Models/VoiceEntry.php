<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'raw_transcript',
        'parsed_data',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'parsed_data' => 'array',
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
