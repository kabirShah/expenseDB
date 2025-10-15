<?php

namespace App\Repositories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;

class CurrencyRepository
{
    protected $model;

    public function __construct(Currency $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find($id): ?Currency
    {
        return $this->model->find($id);
    }

    public function findByCode($code): ?Currency
    {
        return $this->model->byCode($code)->first();
    }

    public function create(array $data): Currency
    {
        return $this->model->create($data);
    }

    public function update($id, array $data): bool
    {
        $currency = $this->find($id);
        return $currency ? $currency->update($data) : false;
    }

    public function delete($id): bool
    {
        $currency = $this->find($id);
        return $currency ? $currency->delete() : false;
    }

    public function getActive(): Collection
    {
        return $this->model->active()->get();
    }

    public function getBaseCurrency(): ?Currency
    {
        return $this->model->where('code', 'USD')->first();
    }

    public function convertAmount($amount, $fromCurrency, $toCurrency): float
    {
        $from = is_string($fromCurrency) ? $this->findByCode($fromCurrency) : $fromCurrency;
        $to = is_string($toCurrency) ? $this->findByCode($toCurrency) : $toCurrency;

        if (!$from || !$to) {
            throw new \Exception('Currency not found');
        }

        return $from->convertTo($amount, $to);
    }

    public function getExchangeRate($fromCurrency, $toCurrency): ?float
    {
        $from = is_string($fromCurrency) ? $this->findByCode($fromCurrency) : $fromCurrency;
        return $from ? $from->getExchangeRateTo($toCurrency) : null;
    }

    public function updateExchangeRates(array $rates): void
    {
        foreach ($rates as $code => $rate) {
            $this->model->byCode($code)->update([
                'exchange_rate' => $rate,
                'last_updated' => now(),
            ]);
        }
    }

    public function formatAmount($amount, $currencyCode): string
    {
        $currency = $this->findByCode($currencyCode);
        return $currency ? $currency->formatAmount($amount) : number_format($amount, 2);
    }
}
