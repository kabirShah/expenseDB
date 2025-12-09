<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    protected $fillable = [
        'receipt_id',
        'user_id',
        'title',
        'file_url',
        'raw_text',
        'parsed_items',
        'total_amount'
    ];

    protected $casts = [
        'parsed_items' => 'array',
        'total_amount' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
