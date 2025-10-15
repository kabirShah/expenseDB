<?php

namespace App\Services;

use App\Repositories\CurrencyRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class CurrencyService
{
    protected $currencyRepository;
    protected $apiKey;
    protected $baseUrl;

    public function __construct(CurrencyRepository $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
        $this->apiKey = config('services.currency.api_key');
        $this->baseUrl = config('services.currency.base_url', 'https://api.exchangerate-api.com/v4/latest/');
    }

    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRate($fromCurrency, $toCurrency);

        if (!$rate) {
            throw new Exception("Exchange rate not available for {$fromCurrency} to {$toCurrency}");
        }

        return round($amount * $rate, 2);
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        return Cache::remember("exchange_rate_{$fromCurrency}_{$toCurrency}", 3600, function () use ($fromCurrency, $toCurrency) {
            return $this->currencyRepository->getExchangeRate($fromCurrency, $toCurrency);
        });
    }

    public function updateExchangeRates(): void
    {
        try {
            $response = Http::get($this->baseUrl . 'USD');

            if ($response->successful()) {
                $data = $response->json();
                $rates = $data['rates'] ?? [];

                $this->currencyRepository->updateExchangeRates($rates);

                Cache::flush(); // Clear cache to force refresh
            }
        } catch (Exception $e) {
            // Log error but don't throw - we can still use cached rates
            \Log::error('Failed to update exchange rates: ' . $e->getMessage());
        }
    }

    public function formatAmount(float $amount, string $currencyCode): string
    {
        return $this->currencyRepository->formatAmount($amount, $currencyCode);
    }

    public function getSupportedCurrencies(): array
    {
        return $this->currencyRepository->getActive()->pluck('code', 'name')->toArray();
    }

    public function validateCurrency(string $currencyCode): bool
    {
        return $this->currencyRepository->findByCode($currencyCode) !== null;
    }

    public function getCurrencyInfo(string $currencyCode): ?array
    {
        $currency = $this->currencyRepository->findByCode($currencyCode);

        if (!$currency) {
            return null;
        }

        return [
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => $currency->decimal_places,
            'exchange_rate' => $currency->exchange_rate,
            'is_active' => $currency->is_active,
        ];
    }

    public function convertExpenseToBaseCurrency(array $expenseData): array
    {
        $currency = $expenseData['currency'] ?? 'INR';
        $amount = $expenseData['amount'];

        if ($currency !== 'USD') {
            $convertedAmount = $this->convertAmount($amount, $currency, 'USD');
            $expenseData['original_amount'] = $amount;
            $expenseData['original_currency'] = $currency;
            $expenseData['amount'] = $convertedAmount;
            $expenseData['currency'] = 'USD';
        }

        return $expenseData;
    }

    public function convertExpenseToUserCurrency(array $expenseData, string $userCurrency): array
    {
        if ($expenseData['currency'] === $userCurrency) {
            return $expenseData;
        }

        $amount = $expenseData['amount'];
        $convertedAmount = $this->convertAmount($amount, 'USD', $userCurrency);

        $expenseData['display_amount'] = $convertedAmount;
        $expenseData['display_currency'] = $userCurrency;

        return $expenseData;
    }
}
