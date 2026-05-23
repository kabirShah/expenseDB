<?php

namespace App\Services;

use App\Models\BalanceHistory;
use App\Models\Category;
use App\Models\RoutineExpense;
use App\Models\Transaction;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RoutineSchedulerService
{
    public function run(?Carbon $date = null): int
    {
        $today = ($date ?: now())->copy()->startOfDay();
        $generated = 0;

        RoutineExpense::query()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->orderBy('id')
            ->chunkById(100, function ($routines) use ($today, &$generated) {
                foreach ($routines as $routine) {
                    if ($this->generateIfDue($routine, $today)) {
                        $generated++;
                    }
                }
            });

        return $generated;
    }

    public function generateIfDue(RoutineExpense $routine, ?Carbon $date = null): bool
    {
        $today = ($date ?: now())->copy()->startOfDay();

        if (!$this->isDueOn($routine, $today) || $this->wasGeneratedToday($routine, $today) || $this->duplicateExists($routine, $today)) {
            return false;
        }

        return DB::transaction(function () use ($routine, $today) {
            $wallet = $this->resolveWallet($routine);
            $category = $this->resolveCategory($routine);

            $transaction = Transaction::create($this->filterTransactionColumns([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $routine->user_id,
                'wallet_id' => $wallet?->id,
                'amount' => $routine->amount,
                'type' => 'debit',
                'category_id' => $category?->id,
                'category' => $category?->name ?? 'Others',
                'source_type' => 'routine',
                'entry_type' => 'routine',
                'transaction_date' => $today->toDateString(),
                'status' => 'completed',
                'currency' => $wallet?->currency ?? 'INR',
                'description' => $routine->title,
                'note' => $routine->notes,
                'payment_method' => 'routine',
                'recurring_id' => $routine->id,
                'metadata' => [
                    'routine_expense_id' => $routine->id,
                    'frequency' => $routine->frequency,
                ],
            ]));

            if ($wallet) {
                $this->applyWalletBalance($wallet, $transaction);
            }

            $routine->forceFill(['last_generated_at' => now()])->save();

            return true;
        });
    }

    public function nextDueDate(RoutineExpense $routine, ?Carbon $from = null): ?Carbon
    {
        if ($routine->status !== 'active') {
            return null;
        }

        $cursor = max(
            ($from ?: now())->copy()->startOfDay(),
            $routine->start_date->copy()->startOfDay()
        );

        for ($i = 0; $i < 370; $i++) {
            if ($routine->end_date && $cursor->gt($routine->end_date->copy()->startOfDay())) {
                return null;
            }

            if ($this->isDueOn($routine, $cursor) && !$this->wasGeneratedToday($routine, $cursor)) {
                return $cursor->copy();
            }

            $cursor->addDay();
        }

        return null;
    }

    private function isDueOn(RoutineExpense $routine, Carbon $date): bool
    {
        $start = $routine->start_date->copy()->startOfDay();

        if ($date->lt($start)) {
            return false;
        }

        if ($routine->end_date && $date->gt($routine->end_date->copy()->startOfDay())) {
            return false;
        }

        return match ($routine->frequency) {
            'daily' => true,
            'weekly' => $date->dayOfWeek === $start->dayOfWeek,
            'monthly' => $date->day === min($start->day, $date->daysInMonth),
            default => false,
        };
    }

    private function wasGeneratedToday(RoutineExpense $routine, Carbon $date): bool
    {
        return $routine->last_generated_at
            && $routine->last_generated_at->copy()->startOfDay()->equalTo($date->copy()->startOfDay());
    }

    private function duplicateExists(RoutineExpense $routine, Carbon $date): bool
    {
        return Transaction::query()
            ->where('user_id', $routine->user_id)
            ->where('amount', $routine->amount)
            ->whereDate('transaction_date', $date->toDateString())
            ->where('source_type', 'routine')
            ->exists();
    }

    private function resolveWallet(RoutineExpense $routine): ?Wallet
    {
        $wallet = $routine->wallet_id
            ? Wallet::query()->where('user_id', $routine->user_id)->whereKey($routine->wallet_id)->first()
            : null;

        return $wallet ?: Wallet::query()
            ->where('user_id', $routine->user_id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function resolveCategory(RoutineExpense $routine): ?Category
    {
        $category = $routine->category_id
            ? Category::query()
                ->whereKey($routine->category_id)
                ->where(function ($query) use ($routine) {
                    $query->whereNull('user_id')->orWhere('user_id', $routine->user_id);
                })
                ->first()
            : null;

        return $category ?: Category::query()
            ->where(function ($query) use ($routine) {
                $query->whereNull('user_id')->orWhere('user_id', $routine->user_id);
            })
            ->whereIn('name', ['Others', 'Other'])
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 0 ELSE 1 END')
            ->first();
    }

    private function applyWalletBalance(Wallet $wallet, Transaction $transaction): void
    {
        $previous = (float) $wallet->balance;
        $amount = (float) $transaction->amount;
        $wallet->balance = $previous - $amount;
        $wallet->save();

        if (Schema::hasTable('balance_histories')) {
            BalanceHistory::create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'previous_balance' => $previous,
                'new_balance' => (float) $wallet->balance,
                'change_amount' => $amount,
                'change_type' => 'debit',
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
