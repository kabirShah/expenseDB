<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Transaction;
use App\Services\Ingestion\UnifiedExpenseIngestionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionParserService
{
    public function __construct(
        private readonly UnifiedExpenseIngestionService $expenseIngestionService
    ) {
    }

    public function process(int $userId, array $payload): array
    {
        if (!config('features.enable_auto_tracking', true)) {
            return [
                'success' => false,
                'status' => 'disabled',
                'message' => 'Auto tracking is disabled.',
            ];
        }

        $validator = Validator::make($payload, [
            'sms_body' => 'nullable|string|max:5000',
            'sender' => 'nullable|string|max:100',
            'source_app' => 'nullable|string|max:100',
            'received_at' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0.01',
            'merchant' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'transaction_type' => 'nullable|in:expense,income',
            'payment_source' => 'nullable|in:gpay,phonepe,paytm,upi,bank,unknown',
        ]);

        $data = $validator->validate();
        $normalized = $this->normalize($data);

        if (!$normalized['is_financial']) {
            return [
                'success' => false,
                'status' => 'ignored',
                'message' => 'Message did not match financial transaction rules.',
                'data' => $normalized,
            ];
        }

        $hash = $this->buildHash($normalized);
        $reference = 'smsauto_' . substr($hash, 0, 24);

        $existingTransaction = Transaction::query()
            ->where('user_id', $userId)
            ->where('reference_no', $reference)
            ->first();

        if ($existingTransaction) {
            return [
                'success' => true,
                'status' => 'duplicate',
                'message' => 'Duplicate auto-detected transaction ignored.',
                'data' => [
                    'transaction' => $existingTransaction->load(['category', 'wallet']),
                    'expense' => $existingTransaction->expense,
                    'hash' => $hash,
                ],
            ];
        }

        $transactionType = $normalized['transaction_type'] === 'income' ? 'income' : 'expense';
        $description = 'Auto detected' . ($normalized['merchant'] ? ' - ' . $normalized['merchant'] : '');

        $transaction = Transaction::create($this->filterTransactionColumns([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $transactionType,
            'amount' => $normalized['amount'],
            'description' => $description,
            'note' => $normalized['sms_body'],
            'payment_method' => 'SMS Auto',
            'reference_no' => $reference,
            'source_app' => $normalized['payment_source'],
            'entry_type' => 'sms',
            'currency' => 'INR',
            'status' => 'completed',
            'transaction_date' => $normalized['date']->toDateTimeString(),
            'metadata' => [
                'source' => 'sms_auto',
                'auto_detected' => true,
                'auto_tracking_hash' => $hash,
                'merchant' => $normalized['merchant'],
                'sms_sender' => $normalized['sender'],
                'sms_body' => $normalized['sms_body'],
                'payment_source' => $normalized['payment_source'],
            ],
        ]));

        $expense = null;
        if ($transactionType === 'expense') {
            $expense = $this->expenseIngestionService->ingest($userId, 'sms', [
                'source_ref_id' => null,
                'merchant_name' => $normalized['merchant'],
                'amount' => $normalized['amount'],
                'currency' => 'INR',
                'payment_method' => 'SMS Auto',
                'payment_source' => $normalized['payment_source'],
                'transaction_type' => $this->mapExpenseTransactionType($normalized['payment_source']),
                'expense_date' => $normalized['date']->toDateTimeString(),
                'date' => $normalized['date']->toDateTimeString(),
                'description' => $description,
                'notes' => $normalized['sms_body'],
                'status' => Expense::STATUS_ACTIVE,
                'raw_hash' => $hash,
                'metadata' => [
                    'source' => 'sms_auto',
                    'auto_detected' => true,
                    'tracking_label' => 'Auto detected',
                    'sender' => $normalized['sender'],
                ],
            ]);

            $transaction->expense_id = $expense->id;
            $transaction->category_id = $expense->category_id;
            $transaction->category = $expense->category_name;
            $transaction->save();
        }

        return [
            'success' => true,
            'status' => 'created',
            'message' => 'Transaction auto-detected successfully.',
            'data' => [
                'transaction' => $transaction->fresh(['category', 'wallet', 'expense']),
                'expense' => $expense?->fresh(['category', 'wallet']),
                'hash' => $hash,
            ],
        ];
    }

    private function normalize(array $data): array
    {
        $smsBody = trim((string) ($data['sms_body'] ?? ''));
        $sender = $this->cleanText($data['sender'] ?? null);
        $text = strtoupper(trim(implode(' ', array_filter([$smsBody, $sender, $data['source_app'] ?? null]))));

        $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : $this->extractAmount($smsBody);
        $merchant = $this->cleanText($data['merchant'] ?? null) ?? $this->extractMerchant($smsBody);
        $date = isset($data['date'])
            ? Carbon::parse($data['date'])
            : $this->extractDate($smsBody, $data['received_at'] ?? null);
        $transactionType = $data['transaction_type'] ?? $this->detectTransactionType($text);
        $paymentSource = $data['payment_source'] ?? $this->detectPaymentSource($text);

        $isFinancial = $amount > 0 && $this->matchesFinancialKeywords($text);

        return [
            'sms_body' => $smsBody,
            'sender' => $sender,
            'amount' => $amount,
            'merchant' => $merchant ?? 'Unknown merchant',
            'date' => $date,
            'transaction_type' => $transactionType,
            'payment_source' => $paymentSource,
            'is_financial' => $isFinancial,
        ];
    }

    private function buildHash(array $normalized): string
    {
        return hash('sha256', implode('|', [
            number_format((float) $normalized['amount'], 2, '.', ''),
            Carbon::parse($normalized['date'])->format('Y-m-d H:i'),
            strtolower(trim((string) $normalized['merchant'])),
        ]));
    }

    private function extractAmount(string $smsBody): float
    {
        if (preg_match('/(?:₹|rs\.?|inr)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i', $smsBody, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }

        return 0.0;
    }

    private function extractMerchant(string $smsBody): ?string
    {
        $patterns = [
            '/(?:paid to|sent to|payment to|purchase at|spent at|at|to)\s+([A-Za-z0-9 .&\\/-]{2,80})/i',
            '/(?:merchant|info)[:\\s-]+([A-Za-z0-9 .&\\/-]{2,80})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $smsBody, $match)) {
                return $this->cleanText($match[1]);
            }
        }

        return null;
    }

    private function extractDate(string $smsBody, mixed $fallback = null): Carbon
    {
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})\b/', $smsBody, $match)) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];
            if ($year < 100) {
                $year += 2000;
            }

            $time = '00:00';
            if (preg_match('/\b(\d{1,2}):(\d{2})(?:\s*([AP]M))?\b/i', $smsBody, $timeMatch)) {
                $hour = (int) $timeMatch[1];
                $minute = (int) $timeMatch[2];
                $suffix = strtoupper($timeMatch[3] ?? '');
                if ($suffix === 'PM' && $hour < 12) {
                    $hour += 12;
                } elseif ($suffix === 'AM' && $hour === 12) {
                    $hour = 0;
                }
                $time = sprintf('%02d:%02d', $hour, $minute);
            }

            return Carbon::parse(sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time));
        }

        return Carbon::parse($fallback ?: now());
    }

    private function detectTransactionType(string $text): string
    {
        if (preg_match('/\b(credited|received|deposit|credit)\b/i', $text)) {
            return 'income';
        }

        return 'expense';
    }

    private function detectPaymentSource(string $text): string
    {
        return match (true) {
            str_contains($text, 'GOOGLE PAY'),
            str_contains($text, 'GPAY') => 'gpay',
            str_contains($text, 'PHONEPE') => 'phonepe',
            str_contains($text, 'PAYTM') => 'paytm',
            str_contains($text, 'UPI') => 'upi',
            str_contains($text, 'BANK'),
            str_contains($text, 'ACCOUNT'),
            str_contains($text, 'A/C'),
            str_contains($text, 'NEFT'),
            str_contains($text, 'IMPS') => 'bank',
            default => 'unknown',
        };
    }

    private function matchesFinancialKeywords(string $text): bool
    {
        foreach (['DEBITED', 'CREDITED', 'UPI', 'PAID', '₹', 'RS', 'INR'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function cleanText(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return $value !== '' ? $value : null;
    }

    private function mapExpenseTransactionType(string $paymentSource): string
    {
        return match ($paymentSource) {
            'upi', 'gpay', 'phonepe', 'paytm' => 'UPI',
            'bank' => 'Bank Transfer',
            default => 'Mobile Wallet',
        };
    }

    private function filterTransactionColumns(array $attributes): array
    {
        $columns = \Illuminate\Support\Facades\Schema::hasTable('transactions')
            ? \Illuminate\Support\Facades\Schema::getColumnListing('transactions')
            : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
