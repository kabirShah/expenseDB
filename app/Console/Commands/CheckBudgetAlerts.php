<?php

namespace App\Console\Commands;

use App\Models\BudgetPlan;
use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Console\Command;

class CheckBudgetAlerts extends Command
{
    protected $signature = 'budgets:check-alerts';
    protected $description = 'Check budget thresholds and create alerts';

    public function handle(): int
    {
        $budgets = BudgetPlan::where('is_active', true)->get();

        foreach ($budgets as $budget) {
            $spent = Transaction::where('user_id', $budget->user_id)
                ->whereIn('type', ['expense', 'debit'])
                ->when($budget->category_id, fn ($q) => $q->where('category_id', $budget->category_id))
                ->whereBetween('transaction_date', [
                    $budget->start_date?->toDateString() ?? now()->startOfMonth()->toDateString(),
                    $budget->end_date?->toDateString() ?? now()->endOfMonth()->toDateString(),
                ])
                ->sum('amount');

            $percentage = ((float) $budget->amount) > 0
                ? ((float) $spent / (float) $budget->amount) * 100
                : 0;

            if ($percentage >= (int) $budget->alert_at) {
                Notification::firstOrCreate(
                    [
                        'user_id' => $budget->user_id,
                        'type' => 'budget_alert',
                        'title' => 'Budget Alert: ' . $budget->name,
                    ],
                    [
                        'message' => "You've used " . round($percentage) . "% of your budget for {$budget->name}.",
                        'body' => "You've used " . round($percentage) . "% of your budget for {$budget->name}.",
                        'data' => [
                            'budget_id' => $budget->id,
                            'percentage' => round($percentage),
                        ],
                        'is_read' => false,
                    ]
                );
            }
        }

        $this->info('Budget alert check completed.');
        return self::SUCCESS;
    }
}
