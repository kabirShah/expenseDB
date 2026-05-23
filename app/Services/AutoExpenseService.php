<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AutoExpenseService
{
    public function ingestNotification(int $userId, array $payload): array
    {
        $data = validator($payload, [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'string', 'in:DEBIT,CREDIT,debit,credit'],
            'date' => ['required', 'date'],
            'narration' => ['required', 'string', 'max:1000'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'source' => ['required', 'string', 'in:NOTIFICATION'],
            'merchant' => ['nullable', 'string', 'max:255'],
            'package_name' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'hash' => ['nullable', 'string', 'max:64'],
        ])->validate();

        if (strtoupper($data['type']) !== 'DEBIT') {
            return ['created' => false, 'ignored' => true, 'message' => 'Only expenses are auto-created.'];
        }

        $date = Carbon::parse($data['date']);
        $amount = round((float) $data['amount'], 2);
        $referenceId = $this->cleanReference($data['reference_id'] ?? null);
        $hash = $this->buildHash($userId, $amount, $date, $referenceId);

        $duplicate = $this->findDuplicate($userId, $amount, $date, $referenceId, $hash);
        if ($duplicate) {
            return [
                'created' => false,
                'duplicate' => true,
                'message' => 'Duplicate auto-detected expense ignored.',
                'data' => $duplicate,
                'hash' => $hash,
            ];
        }

        return DB::transaction(function () use ($userId, $data, $date, $amount, $referenceId, $hash) {
            $category = $this->defaultCategory($userId);
            $wallet = $this->defaultWallet($userId);
            $paymentMethod = $data['payment_method'] ?? $this->paymentMethodFromPackage($data['package_name'] ?? null);
            $description = $this->cleanNarration($data['narration']);

            $expense = Expense::create($this->filterExpenseColumns([
                'expense_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'wallet_id' => $wallet?->id,
                'category_id' => $category?->id,
                'category_name' => $category?->name ?? 'Others',
                'source' => 'NOTIFICATION',
                'source_type' => 'notification',
                'reference_id' => $referenceId,
                'raw_hash' => $hash,
                'hash' => $hash,
                'merchant_name' => $data['merchant'] ?? null,
                'payment_method' => $paymentMethod,
                'payment_source' => $this->paymentSourceFromPackage($data['package_name'] ?? null),
                'transaction_type' => $paymentMethod,
                'description' => $description,
                'amount' => $amount,
                'currency' => 'INR',
                'date' => $date,
                'expense_date' => $date,
                'notes' => $description,
                'status' => Expense::STATUS_ACTIVE,
                'metadata' => [
                    'source' => 'NOTIFICATION',
                    'package_name' => $data['package_name'] ?? null,
                    'reference_id' => $referenceId,
                    'hash' => $hash,
                ],
            ]));

            app(UnifiedTransactionService::class)->syncExpense($expense, 'notification');
            $this->applyWalletBalance($expense);

            return [
                'created' => true,
                'duplicate' => false,
                'message' => 'Expense auto-detected successfully.',
                'data' => $expense->fresh(['wallet', 'category']),
                'hash' => $hash,
            ];
        });
    }

    private function findDuplicate(int $userId, float $amount, Carbon $date, ?string $referenceId, string $hash): Expense|Transaction|null
    {
        $expenseByHash = Expense::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($hash) {
                $query->where('hash', $hash)->orWhere('raw_hash', $hash);
            })
            ->first();

        if ($expenseByHash) {
            return $expenseByHash;
        }

        if ($referenceId) {
            $expenseByReference = Expense::query()
                ->where('user_id', $userId)
                ->where('reference_id', $referenceId)
                ->first();

            if ($expenseByReference) {
                return $expenseByReference;
            }

            $transactionByReference = Transaction::query()
                ->where('user_id', $userId)
                ->where('reference_id', $referenceId)
                ->first();

            if ($transactionByReference) {
                return $transactionByReference;
            }
        }

        $from = $date->copy()->subMinutes(5);
        $to = $date->copy()->addMinutes(5);

        $expenseByWindow = Expense::query()
            ->where('user_id', $userId)
            ->where('amount', $amount)
            ->whereIn('source', ['AA', 'NOTIFICATION'])
            ->whereBetween('expense_date', [$from, $to])
            ->first();

        if ($expenseByWindow) {
            return $expenseByWindow;
        }

        return Transaction::query()
            ->where('user_id', $userId)
            ->where('amount', $amount)
            ->where(function ($query) {
                $query->whereIn('source_type', ['aa', 'notification', 'auto'])
                    ->orWhereIn('entry_type', ['aa', 'notification', 'auto']);
            })
            ->whereBetween('transaction_date', [$from, $to])
            ->first();
    }

    private function buildHash(int $userId, float $amount, Carbon $date, ?string $referenceId): string
    {
        return sha1(implode('|', [
            $userId,
            number_format($amount, 2, '.', ''),
            $date->toDateString(),
            $referenceId ?? '',
        ]));
    }

    private function cleanReference(?string $referenceId): ?string
    {
        $referenceId = trim((string) $referenceId);
        return $referenceId !== '' ? $referenceId : null;
    }

    private function cleanNarration(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return Str::limit($value ?: 'Auto-detected expense', 500, '');
    }

    private function defaultWallet(int $userId): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function defaultCategory(int $userId): ?Category
    {
        return Category::query()
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereIn('name', ['Others', 'Other', 'Shopping'])
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->first();
    }

    private function paymentMethodFromPackage(?string $packageName): string
    {
        $packageName = strtolower((string) $packageName);

        return match (true) {
            str_contains($packageName, 'phonepe'),
            str_contains($packageName, 'paisa'),
            str_contains($packageName, 'paytm') => 'UPI',
            str_contains($packageName, 'mobikwik'),
            str_contains($packageName, 'freecharge') => 'Mobile Wallet',
            default => 'Bank Transfer',
        };
    }

    private function paymentSourceFromPackage(?string $packageName): ?string
    {
        $packageName = strtolower((string) $packageName);

        return match (true) {
            str_contains($packageName, 'phonepe') => 'phonepe',
            str_contains($packageName, 'paisa') => 'gpay',
            str_contains($packageName, 'paytm') => 'paytm',
            str_contains($packageName, 'upi') => 'upi',
            $packageName !== '' => 'bank',
            default => null,
        };
    }

    private function applyWalletBalance(Expense $expense): void
    {
        if (!$expense->wallet_id) {
            return;
        }

        $wallet = Wallet::find($expense->wallet_id);
        if (!$wallet) {
            return;
        }

        $wallet->balance = (float) $wallet->balance - (float) $expense->amount;
        $wallet->save();
    }

    private function filterExpenseColumns(array $attributes): array
    {
        $columns = Schema::hasTable('expenses') ? Schema::getColumnListing('expenses') : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
