<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_id',
        'user_id',
        'expense_id',
        'transaction_id',
        'file_url',
        'amount',
        'tax_amount',
        'discount_amount',
        'description',
        'merchant_name',
        'merchant_address',
        'merchant_gstin',
        'invoice_number',
        'date',
        'ocr_data',
        'verification_status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'date' => 'datetime',
        'ocr_data' => 'array'
    ];

    const VERIFICATION_PENDING = 'pending';
    const VERIFICATION_VERIFIED = 'verified';
    const VERIFICATION_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getTotalAmountAttribute()
    {
        return $this->amount + $this->tax_amount - $this->discount_amount;
    }

    public function getFormattedTotalAmountAttribute()
    {
        return '₹' . number_format($this->total_amount, 2);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    public function scopePendingVerification($query)
    {
        return $query->where('verification_status', self::VERIFICATION_PENDING);
    }

    public function isVerified()
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }
}
