<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceiptItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'receipt_items';

    protected $fillable = [
        'receipt_id',
        'name',
        'qty',
        'price',
        'discount',
        'tax',
        'subtotal',
        'total',
        'confidence',
        'meta',
    ];

    protected $casts = [
        'qty' => 'integer',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'confidence' => 'integer',
        'meta' => 'array',
    ];
}

