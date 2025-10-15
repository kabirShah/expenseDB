<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserEvent extends Model
{
    protected $table = 'parser_events';

    protected $fillable = [
        'event_id',
        'user_id',
        'bank_name',
        'file_name',
        'file_path',
        'file_type',
        'status',
        'total_transactions',
        'parsed_transactions',
        'failed_transactions',
        'metadata',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'json',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_transactions' => 'integer',
        'parsed_transactions' => 'integer',
        'failed_transactions' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
