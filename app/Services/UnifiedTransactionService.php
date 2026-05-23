<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\MultiExpense;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UnifiedTransactionService
{
    public function syncExpense(Expense $expense, ?string $sourceType = null): ?Transaction
    {
        if (!$this->isActiveExpense($expense) || (float) $expense->amount <= 0) {
            $this->deleteExpenseTransaction($expense);
            return null;
        }

        $source = $this->normalizeSource($sourceType ?? $expense->source_type ?? 'single');

        return Transaction::query()->updateOrCreate(
            [
                'user_id' => $expense->user_id,
                'reference_no' => 'expense:' . $expense->id,
            ],
            $this->filterColumns([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $expense->user_id,
                'wallet_id' => $expense->wallet_id,
                'category_id' => $expense->category_id,
                'category' => $expense->category_name,
                'type' => 'debit',
                'amount' => round((float) $expense->amount, 2),
                'status' => 'completed',
                'currency' => $expense->currency ?? 'INR',
                'payment_method' => $expense->payment_method ?? $expense->transaction_type,
                'method' => $expense->payment_method ?? $expense->transaction_type,
                'description' => $expense->description ?? $expense->merchant_name ?? ucfirst($source) . ' expense',
                'note' => $expense->notes,
                'merchant' => $expense->merchant_name,
                'merchant_name' => $expense->merchant_name,
                'source_type' => $source,
                'entry_type' => $source,
                'reference_no' => 'expense:' . $expense->id,
                'transaction_date' => Carbon::parse($expense->expense_date ?? $expense->date ?? $expense->created_at),
            ])
        );
    }

    public function deleteExpenseTransaction(Expense $expense): void
    {
        Transaction::query()
            ->where('user_id', $expense->user_id)
            ->where('reference_no', 'expense:' . $expense->id)
            ->delete();
    }

    public function syncMultiExpense(MultiExpense $multiExpense): void
    {
        $reference = 'multi:' . $multiExpense->id;

        Transaction::query()
            ->where('user_id', $multiExpense->user_id)
            ->where('source_type', 'multi')
            ->where('reference_no', $reference)
            ->delete();

        $parent = Transaction::create($this->filterColumns([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $multiExpense->user_id,
            'wallet_id' => $multiExpense->wallet_id,
            'type' => 'debit',
            'amount' => 0,
            'status' => 'completed',
            'currency' => 'INR',
            'description' => $multiExpense->title,
            'category' => $multiExpense->category,
            'source_type' => 'multi',
            'entry_type' => 'multi',
            'reference_no' => $reference,
            'transaction_date' => Carbon::parse($multiExpense->created_at ?? now()),
        ]));

        foreach ($this->parseMultiItems((string) $multiExpense->description) as $item) {
            Transaction::create($this->filterColumns([
                'transaction_id' => (string) Str::uuid(),
                'parent_id' => $parent->id,
                'user_id' => $multiExpense->user_id,
                'wallet_id' => $multiExpense->wallet_id,
                'type' => 'debit',
                'amount' => $item['amount'],
                'status' => 'completed',
                'currency' => 'INR',
                'description' => $item['label'] ?: $multiExpense->title,
                'merchant' => $item['label'],
                'merchant_name' => $item['label'],
                'category' => $multiExpense->category,
                'source_type' => 'multi',
                'entry_type' => 'multi',
                'reference_no' => $reference,
                'transaction_date' => Carbon::parse($multiExpense->created_at ?? now()),
            ]));
        }
    }

    public function deleteMultiExpenseTransactions(MultiExpense $multiExpense): void
    {
        Transaction::query()
            ->where('user_id', $multiExpense->user_id)
            ->where('source_type', 'multi')
            ->where('reference_no', 'multi:' . $multiExpense->id)
            ->delete();
    }

    private function parseMultiItems(string $description): array
    {
        preg_match_all('/(?:₹|rs\.?|inr)?\s*([0-9,]+(?:\.[0-9]{1,2})?)\s*([A-Za-z][A-Za-z0-9 &.-]*)?/i', $description, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match): array => [
                'amount' => round((float) str_replace(',', '', $match[1]), 2),
                'label' => trim((string) ($match[2] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['amount'] > 0)
            ->values()
            ->all();
    }

    private function normalizeSource(string $source): string
    {
        return match (strtolower($source)) {
            'manual', 'single', 'expense' => 'single',
            'notification', 'sms', 'auto' => 'auto',
            'receipt', 'scan' => 'scan',
            'voice' => 'voice',
            'multi', 'split', 'group' => 'multi',
            default => 'single',
        };
    }

    private function isActiveExpense(Expense $expense): bool
    {
        return ($expense->status ?? Expense::STATUS_ACTIVE) === Expense::STATUS_ACTIVE
            && empty($expense->is_duplicate);
    }

    private function filterColumns(array $attributes): array
    {
        $columns = Schema::hasTable('transactions') ? Schema::getColumnListing('transactions') : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
