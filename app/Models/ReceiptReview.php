<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'receipt_reviews';

    protected $fillable = [
        'receipt_id',
        'manual_override_json',
        'field_status_json',
        'version_notes',
        'confidence',
        'status',
        'confirmed_by_user_id',
    ];

    protected $casts = [
        'manual_override_json' => 'array',
        'field_status_json' => 'array',
        'confidence' => 'integer',
        'confirmed_by_user_id' => 'integer',
    ];
}

