<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TransactionMapper
{
    public function map(array $payload, int $userId, int $consentId): array
    {
        $accounts = $this->extractAccounts($payload);
        $transactions = [];

        foreach ($accounts as $account) {
            foreach ($this->extractTransactions($account) as $entry) {
                $narration = $this->cleanMerchant(
                    Arr::get($entry, 'narration')
                    ?? Arr::get($entry, 'description')
                    ?? Arr::get($entry, 'merchant')
                );

                $amount = (float) (
                    Arr::get($entry, 'amount')
                    ?? Arr::get($entry, 'txnAmount')
                    ?? 0
                );

                $date = $this->normalizeDate(
                    Arr::get($entry, 'transactionDate')
                    ?? Arr::get($entry, 'valueDate')
                    ?? Arr::get($entry, 'date')
                );

                if ($amount <= 0 || !$date) {
                    continue;
                }

                $hash = md5($amount . $date . ($narration ?? ''));

                $transactions[] = [
                    'user_id' => $userId,
                    'account_ref' => (string) (Arr::get($account, 'accountRef') ?? Arr::get($account, 'maskedAccNumber') ?? ''),
                    'amount' => round($amount, 2),
                    'type' => $this->mapType($entry),
                    'merchant' => $narration,
                    'reference_id' => Arr::get($entry, 'referenceId')
                        ?? Arr::get($entry, 'txnId')
                        ?? Arr::get($entry, 'utr')
                        ?? null,
                    'transaction_date' => $date,
                    'raw_data' => $entry,
                    'hash' => $hash,
                    'consent_id' => $consentId,
                ];
            }
        }

        return [
            'accounts' => array_map(fn (array $account) => $this->mapAccount($account), $accounts),
            'transactions' => $transactions,
        ];
    }

    private function extractAccounts(array $payload): array
    {
        $accounts = Arr::get($payload, 'accounts')
            ?? Arr::get($payload, 'data.accounts')
            ?? Arr::get($payload, 'fi', []);

        return array_values(array_filter(is_array($accounts) ? $accounts : [], 'is_array'));
    }

    private function extractTransactions(array $account): array
    {
        $transactions = Arr::get($account, 'transactions')
            ?? Arr::get($account, 'txns')
            ?? Arr::get($account, 'summary.transactions')
            ?? [];

        return array_values(array_filter(is_array($transactions) ? $transactions : [], 'is_array'));
    }

    private function mapAccount(array $account): array
    {
        return [
            'account_ref' => Arr::get($account, 'accountRef')
                ?? Arr::get($account, 'linkRefNumber')
                ?? Arr::get($account, 'maskedAccNumber')
                ?? null,
            'masked_account_number' => Arr::get($account, 'maskedAccNumber')
                ?? Arr::get($account, 'maskedAccountNumber')
                ?? null,
            'bank_name' => Arr::get($account, 'bank')
                ?? Arr::get($account, 'bankName')
                ?? Arr::get($account, 'fipName')
                ?? 'Unknown Bank',
            'type' => $this->normalizeAccountType(
                Arr::get($account, 'type')
                ?? Arr::get($account, 'accountType')
                ?? 'savings'
            ),
        ];
    }

    private function mapType(array $entry): string
    {
        $rawType = strtoupper((string) (
            Arr::get($entry, 'type')
            ?? Arr::get($entry, 'transactionType')
            ?? Arr::get($entry, 'mode')
            ?? ''
        ));

        $narration = strtoupper((string) (
            Arr::get($entry, 'narration')
            ?? Arr::get($entry, 'description')
            ?? ''
        ));

        return match (true) {
            Str::contains($rawType, ['CREDIT', 'CR']),
            Str::contains($narration, ['SALARY', 'REFUND', 'CREDIT', 'RECEIVED']) => 'income',
            default => 'expense',
        };
    }

    private function cleanMerchant(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $merchant = preg_replace('/\s+/', ' ', trim($value));
        $merchant = preg_replace('/[^A-Za-z0-9 .&\/-]/', ' ', $merchant ?? '');
        $merchant = trim((string) $merchant);

        return $merchant !== '' ? $merchant : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeAccountType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, ['savings', 'current'], true) ? $normalized : 'savings';
    }
}
