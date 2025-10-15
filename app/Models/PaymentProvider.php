<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $table = 'payment_providers';

    protected $fillable = [
        'provider_id',
        'name',
        'type',
        'logo_url',
        'is_active',
        'config',
        'supported_features',
        'transaction_fee_percentage',
        'min_transaction_amount',
        'max_transaction_amount'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'supported_features' => 'array',
        'transaction_fee_percentage' => 'decimal:2',
        'min_transaction_amount' => 'decimal:2',
        'max_transaction_amount' => 'decimal:2'
    ];

    const TYPE_UPI = 'upi';
    const TYPE_BANK = 'bank';
    const TYPE_WALLET = 'wallet';
    const TYPE_CARD_NETWORK = 'card_network';

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeUpiProviders($query)
    {
        return $query->where('type', self::TYPE_UPI);
    }

    public function scopeBankProviders($query)
    {
        return $query->where('type', self::TYPE_BANK);
    }

    public function scopeWalletProviders($query)
    {
        return $query->where('type', self::TYPE_WALLET);
    }

    public function getTransactionFee($amount)
    {
        return ($this->transaction_fee_percentage / 100) * $amount;
    }

    public function isAmountWithinLimits($amount)
    {
        if ($amount < $this->min_transaction_amount) {
            return false;
        }

        if ($this->max_transaction_amount && $amount > $this->max_transaction_amount) {
            return false;
        }

        return true;
    }

    public function supportsFeature($feature)
    {
        return in_array($feature, $this->supported_features ?? []);
    }
}
