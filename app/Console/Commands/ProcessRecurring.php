<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;

class ProcessRecurring extends Command
{
    protected $signature = 'recurring:process';
    protected $description = 'Process due recurring transactions';

    public function handle(): int
    {
        $due = RecurringTransaction::where('is_active', true)
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->get();

        foreach ($due as $recurring) {
            $type = $recurring->type === 'expense' ? 'debit' : 'credit';

            Transaction::create([
                'user_id' => $recurring->user_id,
                'wallet_id' => $recurring->wallet_id,
                'category_id' => $recurring->category_id,
                'type' => $type,
                'amount' => $recurring->amount,
                'note' => trim(($recurring->note ?? 'Recurring transaction') . ' (Auto)'),
                'description' => trim(($recurring->note ?? 'Recurring transaction') . ' (Auto)'),
                'payment_method' => $recurring->payment_method,
                'method' => $recurring->payment_method,
                'transaction_date' => now()->toDateString(),
                'entry_type' => 'single',
                'recurring_id' => $recurring->id,
                'currency' => 'INR',
                'status' => 'completed',
            ]);

            $recurring->update([
                'last_run_date' => now()->toDateString(),
                'next_run_date' => $this->calculateNextRun($recurring),
            ]);
        }

        $this->info('Processed ' . $due->count() . ' recurring transactions.');
        return self::SUCCESS;
    }

    private function calculateNextRun(RecurringTransaction $r): string
    {
        return match ($r->frequency) {
            'daily' => now()->addDay()->toDateString(),
            'weekly' => now()->addWeek()->toDateString(),
            'monthly' => now()->addMonth()->toDateString(),
            default => now()->addYear()->toDateString(),
        };
    }
}
