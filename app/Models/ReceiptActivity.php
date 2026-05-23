<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptActivity extends Model
{
    use HasFactory;

    protected $table = 'receipt_activity';

    protected $fillable = [
        'receipt_id',
        'actor_user_id',
        'event_type',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'actor_user_id' => 'integer',
    ];
}

