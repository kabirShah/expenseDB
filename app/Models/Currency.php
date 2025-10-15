<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'exchange_rate',
        'last_updated',
        'is_active',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'exchange_rate' => 'decimal:6',
        'last_updated' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // Helper methods
    public function convertTo($amount, $targetCurrency)
    {
        if (!$targetCurrency instanceof self) {
            $targetCurrency = self::byCode($targetCurrency)->first();
        }

        if (!$targetCurrency) {
            throw new \Exception("Target currency not found");
        }

        // Convert to base currency (USD) first, then to target
        $usdAmount = $amount / $this->exchange_rate;
        return $usdAmount * $targetCurrency->exchange_rate;
    }

    public function formatAmount($amount)
    {
        return $this->symbol . ' ' . number_format($amount, $this->decimal_places);
    }

    public function getExchangeRateTo($targetCurrencyCode)
    {
        $targetCurrency = self::byCode($targetCurrencyCode)->first();
        return $targetCurrency ? $targetCurrency->exchange_rate / $this->exchange_rate : null;
    }

    public function isBaseCurrency()
    {
        return $this->code === 'USD';
    }
}
