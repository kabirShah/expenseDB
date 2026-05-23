<?php

namespace App\Services;

use App\Models\BalanceHistory;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        private readonly ParsingService $parsingService,
        private readonly DuplicateCheckService $duplicateCheckService,
        private readonly WalletMappingService $walletMappingService
    ) {
    }

    public function parseDetection(int $userId, array $payload): array
    {
        $parsed = $this->parsingService->parse(
            (string) $payload['raw_text'],
            $payload['package_name'] ?? null,
            $payload['received_at'] ?? null
        );

        if (!$parsed['is_financial']) {
            return ['created' => false, 'duplicate' => false, 'ignored' => true, 'parsed' => $parsed];
        }

        $duplicate = $this->duplicateCheckService->findDuplicate($userId, $parsed);
        if ($duplicate) {
            return ['created' => false, 'duplicate' => true, 'transaction' => $duplicate->load(['wallet', 'category'])];
        }

        return DB::transaction(function () use ($userId, $payload, $parsed) {
            $wallet = $this->walletMappingService->resolve($userId, $parsed['source_key'], $parsed['source']);
            $category = $this->defaultCategory($userId, $parsed['type']);

            $transaction = Transaction::create($this->filterTransactionColumns([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'category_id' => $category?->id,
                'type' => $parsed['type'],
                'amount' => $parsed['amount'],
                'category' => $category?->name,
                'description' => $parsed['merchant_name'] ?: 'Auto detected transaction',
                'note' => null,
                'merchant' => $parsed['merchant_name'],
                'merchant_name' => $parsed['merchant_name'],
                'currency' => 'INR',
                'status' => 'completed',
                'payment_method' => $wallet->type === 'upi' ? 'UPI' : 'Bank Transfer',
                'reference_id' => $parsed['reference_id'],
                'reference_no' => $parsed['reference_id'],
                'source_app' => $parsed['source'],
                'source_type' => 'auto',
                'entry_type' => 'auto',
                'raw_text' => config('app.debug') ? $payload['raw_text'] : null,
                'raw_data' => [
                    'source' => $parsed['source'],
                    'source_key' => $parsed['source_key'],
                    'package_name' => $payload['package_name'] ?? null,
                ],
                'metadata' => [
                    'detected_by' => 'background_detection',
                    'source' => $parsed['source'],
                    'reference_id' => $parsed['reference_id'],
                ],
                'transaction_date' => $parsed['transaction_date'],
            ]));

            $this->applyWalletBalance($wallet, $transaction);

            return [
                'created' => true,
                'duplicate' => false,
                'transaction' => $transaction->fresh(['wallet', 'category']),
                'parsed' => $parsed,
            ];
        });
    }

    public function summary(int $userId): array
    {
        $monthStart = now()->startOfMonth();

        $monthlyCredits = Transaction::where('user_id', $userId)
            ->where('type', 'credit')
            ->where('transaction_date', '>=', $monthStart)
            ->sum('amount');

        $monthlyDebits = Transaction::where('user_id', $userId)
            ->where('type', 'debit')
            ->where('transaction_date', '>=', $monthStart)
            ->sum('amount');

        return [
            'monthly_totals' => [
                'credit' => round((float) $monthlyCredits, 2),
                'debit' => round((float) $monthlyDebits, 2),
                'net' => round((float) $monthlyCredits - (float) $monthlyDebits, 2),
            ],
            'category_spending' => Transaction::query()
                ->selectRaw('COALESCE(categories.name, transactions.category, "Others") as category, SUM(transactions.amount) as total')
                ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
                ->where('transactions.user_id', $userId)
                ->where('transactions.type', 'debit')
                ->where('transactions.transaction_date', '>=', $monthStart)
                ->groupBy('category')
                ->get(),
            'wallet_balances' => Wallet::where('user_id', $userId)
                ->select('id', 'name', 'type', 'balance', 'currency')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function defaultCategory(int $userId, string $type): ?Category
    {
        $names = $type === 'credit' ? ['Other Income', 'Others', 'Other'] : ['Others', 'Other', 'Shopping'];

        return Category::query()
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->whereIn('name', $names)
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->first();
    }

    private function applyWalletBalance(Wallet $wallet, Transaction $transaction): void
    {
        $previous = (float) $wallet->balance;
        $amount = (float) $transaction->amount;
        $wallet->balance = $transaction->type === 'debit' ? $previous - $amount : $previous + $amount;
        $wallet->save();

        if (Schema::hasTable('balance_histories')) {
            BalanceHistory::create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'previous_balance' => $previous,
                'new_balance' => (float) $wallet->balance,
                'change_amount' => $amount,
                'change_type' => $transaction->type,
            ]);
        }
    }

    private function filterTransactionColumns(array $attributes): array
    {
        $columns = Schema::hasTable('transactions') ? Schema::getColumnListing('transactions') : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
