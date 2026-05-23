<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    private const SOURCES = ['single', 'multi', 'scan', 'voice', 'auto'];

    public function getTotalExpense(int $userId, ?Carbon $from = null, ?Carbon $to = null): float
    {
        return round((float) $this->debitQuery($userId, $from, $to)->sum('amount'), 2);
    }

    public function getExpenseBySource(int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $breakdown = array_fill_keys(self::SOURCES, 0.0);

        $rows = $this->debitQuery($userId, $from, $to)
            ->selectRaw('COALESCE(source_type, entry_type, "single") as source, SUM(amount) as total')
            ->groupBy('source')
            ->get();

        foreach ($rows as $row) {
            $source = $this->normalizeSource((string) $row->source);
            $breakdown[$source] += round((float) $row->total, 2);
        }

        return array_map(static fn (float $value): float => round($value, 2), $breakdown);
    }

    public function getExpenseByCategory(int $userId, ?Carbon $from = null, ?Carbon $to = null)
    {
        $categoryExpression = Schema::hasColumn('transactions', 'category')
            ? 'COALESCE(categories.name, transactions.category, "Others")'
            : 'COALESCE(categories.name, "Others")';

        return $this->debitQuery($userId, $from, $to)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw($categoryExpression . ' as category, SUM(transactions.amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();
    }

    public function getRecentDebits(int $userId, int $limit = 5)
    {
        return $this->debitQuery($userId)
            ->with(['wallet', 'category'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function debitQuery(int $userId, ?Carbon $from = null, ?Carbon $to = null)
    {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', 'debit')
            ->where('transactions.status', 'completed')
            ->when($from && $to, fn ($query) => $query->whereBetween('transactions.transaction_date', [$from, $to]));
    }

    private function normalizeSource(string $source): string
    {
        return match (strtolower($source)) {
            'manual', 'expense', 'single', 'single_expense' => 'single',
            'notification', 'sms', 'auto_detect', 'auto' => 'auto',
            'receipt', 'scan' => 'scan',
            'voice' => 'voice',
            'multi', 'split', 'group' => 'multi',
            default => 'single',
        };
    }
}
