<?php

namespace App\Services\Parsing;

use Carbon\Carbon;

class ExpenseNormalizerService
{
    public function normalize(int $userId, string $sourceType, array $payload): array
    {
        $merchantName = $this->cleanText($payload['merchant_name'] ?? $payload['merchant'] ?? null);
        $description = $this->cleanText($payload['description'] ?? $payload['title'] ?? $merchantName ?? null);
        $notes = $this->cleanText($payload['notes'] ?? $payload['note'] ?? null);
        $paymentMethod = $this->cleanText($payload['payment_method'] ?? $payload['transaction_type'] ?? null);
        $paymentSource = $this->resolvePaymentSource($payload, $paymentMethod, $sourceType);
        $expenseDate = $this->normalizeDate($payload['expense_date'] ?? $payload['date'] ?? null);
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $currency = strtoupper((string) ($payload['currency'] ?? 'INR'));

        $identity = [
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_ref_id' => $payload['source_ref_id'] ?? null,
            'amount' => $amount,
            'merchant_name' => $merchantName,
            'expense_date' => $expenseDate,
            'payment_method' => $paymentMethod,
        ];

        return [
            'user_id' => $userId,
            'wallet_id' => $payload['wallet_id'] ?? null,
            'source_type' => $sourceType,
            'source_ref_id' => $payload['source_ref_id'] ?? null,
            'merchant_name' => $merchantName,
            'description' => $description,
            'notes' => $notes,
            'amount' => $amount,
            'currency' => $currency !== '' ? $currency : 'INR',
            'payment_method' => $paymentMethod,
            'payment_source' => $paymentSource,
            'transaction_type' => $paymentMethod ?: 'Cash',
            'expense_date' => $expenseDate,
            'date' => Carbon::parse($expenseDate),
            'category_id' => $payload['category_id'] ?? null,
            'category_name' => $this->cleanText($payload['category_name'] ?? $payload['category'] ?? null),
            'receipt_url' => $payload['receipt_url'] ?? null,
            'status' => $payload['status'] ?? 'active',
            'metadata' => $payload['metadata'] ?? [],
            'raw_hash' => $payload['raw_hash'] ?? hash('sha256', json_encode($identity)),
        ];
    }

    private function normalizeDate(mixed $value): string
    {
        try {
            return Carbon::parse($value ?: now())->toDateTimeString();
        } catch (\Throwable $e) {
            return now()->toDateTimeString();
        }
    }

    private function cleanText(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function resolvePaymentSource(array $payload, ?string $paymentMethod, string $sourceType): ?string
    {
        if (!config('features.enable_payment_source_detection', true)) {
            return null;
        }

        $explicit = $this->normalizePaymentSourceValue($payload['payment_source'] ?? null);
        if ($explicit !== null) {
            return $explicit;
        }

        $parts = array_filter([
            $payload['source_app'] ?? null,
            $payload['merchant_name'] ?? null,
            $payload['merchant'] ?? null,
            $payload['description'] ?? null,
            $payload['notes'] ?? null,
            $payload['note'] ?? null,
            $paymentMethod,
            $sourceType,
            $payload['metadata']['sender'] ?? null,
            $payload['metadata']['reference'] ?? null,
        ], static fn ($value) => is_scalar($value) && trim((string) $value) !== '');

        $haystack = strtoupper(implode(' ', array_map(static fn ($value) => (string) $value, $parts)));

        if ($haystack === '') {
            return null;
        }

        return match (true) {
            str_contains($haystack, 'GOOGLE PAY'),
            str_contains($haystack, 'GPAY') => 'gpay',
            str_contains($haystack, 'PHONEPE') => 'phonepe',
            str_contains($haystack, 'PAYTM') => 'paytm',
            str_contains($haystack, 'UPI') => 'upi',
            str_contains($haystack, 'BANK'),
            str_contains($haystack, 'A/C'),
            str_contains($haystack, 'ACCOUNT'),
            str_contains($haystack, 'NEFT'),
            str_contains($haystack, 'IMPS') => 'bank',
            default => 'unknown',
        };
    }

    private function normalizePaymentSourceValue(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['gpay', 'phonepe', 'paytm', 'upi', 'bank', 'unknown'], true)
            ? $normalized
            : null;
    }
}
