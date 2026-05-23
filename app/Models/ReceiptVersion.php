<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'receipt_versions';

    protected $fillable = [
        'receipt_id',
        'version_type',
        'ocr_json',
        'raw_ocr',
        'parsed_json',
        'manual_override_json',
        'field_status_json',
        'confidence',
    ];

    protected $casts = [
        'ocr_json' => 'array',
        'parsed_json' => 'array',
        'manual_override_json' => 'array',
        'field_status_json' => 'array',
        'confidence' => 'integer',
    ];
}

