<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Receipt extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;


    protected $table = 'receipts';

    protected $fillable = [
        'receipt_id',
        'user_id',
        'title',
        'file_url',
        'processed_image_url',
        'raw_text',
        'raw_ocr',
        'parsed_items',
        'ocr_json',
        'parsed_json',
        'manual_override_json',
        'field_status_json',
        'total_amount',
        'currency',
        'receipt_date',
        'vendor_name',
        'confidence',
        'status',
        'receipt_hash',
        'linked_expense_id', // ✅ NEW (important)
    ];


    protected $casts = [
        'parsed_items' => 'array',
        'ocr_json' => 'array',
        'parsed_json' => 'array',
        'manual_override_json' => 'array',
        'field_status_json' => 'array',
        'total_amount' => 'decimal:2',
        'confidence' => 'integer',
        'receipt_date' => 'date',
    ];


    /*
    |-----------------------------------------
    | AUTO UUID GENERATION
    |-----------------------------------------
    */
    protected static function booted()
    {
        static::creating(function ($receipt) {
            if (!$receipt->receipt_id) {
                $receipt->receipt_id = (string) Str::uuid();
            }
        });
    }


    /*
    |-----------------------------------------
    | RELATIONSHIPS
    |-----------------------------------------
    */

    // User relation
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔥 Link to expense (VERY IMPORTANT)
    public function expense()
    {
        return $this->belongsTo(Expense::class, 'linked_expense_id');
    }

    /*
    |-----------------------------------------
    | ACCESSORS (UI Friendly)
    |-----------------------------------------
    */

    public function getFormattedAmountAttribute()
    {
        return '₹' . number_format($this->total_amount, 2);
    }

    public function itemsCount(): int
    {
        return count($this->parsed_items ?? []);
    }

    public function getItemCountAttribute()
    {
        return $this->itemsCount();
    }
}
