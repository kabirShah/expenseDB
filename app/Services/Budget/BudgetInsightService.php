<?php

namespace App\Services\Budget;

use App\Models\BudgetAlert;
use App\Models\BudgetPlan;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BudgetInsightService
{
    public function summaryForUser(int $userId): Collection
    {
        if (!Schema::hasTable('budget_plans')) {
            return collect();
        }

        return BudgetPlan::query()
            ->with('category')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->map(fn (BudgetPlan $budget) => $this->buildBudgetSummary($budget));
    }

    public function syncAlertsForUser(int $userId): Collection
    {
        if (!Schema::hasTable('budget_alerts')) {
            return collect();
        }

        $summaries = $this->summaryForUser($userId);

        foreach ($summaries as $summary) {
            foreach ([80, 100] as $threshold) {
                if (($summary['percentage'] ?? 0) < $threshold) {
                    continue;
                }

                $exists = BudgetAlert::query()
                    ->where('budget_plan_id', $summary['id'])
                    ->where('threshold_percent', $threshold)
                    ->whereDate('budget_period_start', $summary['start_date'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                BudgetAlert::create([
                    'budget_plan_id' => $summary['id'],
                    'user_id' => $userId,
                    'alert_type' => $threshold >= 100 ? 'limit_reached' : 'warning',
                    'threshold_percent' => $threshold,
                    'spent_amount' => $summary['spent'],
                    'budget_amount' => $summary['amount'],
                    'budget_period_start' => $summary['start_date'],
                    'budget_period_end' => $summary['end_date'],
                    'message' => $threshold >= 100
                        ? "Budget reached for {$summary['name']}"
                        : "Budget is above {$threshold}% for {$summary['name']}",
                    'sent_at' => now(),
                ]);
            }
        }

        return BudgetAlert::query()
            ->with('budget.category')
            ->where('user_id', $userId)
            ->latest('sent_at')
            ->get();
    }

    public function predictionsForUser(int $userId): Collection
    {
        return $this->summaryForUser($userId)->map(function (array $summary) {
            $start = Carbon::parse($summary['start_date']);
            $end = Carbon::parse($summary['end_date']);
            $today = now();

            $elapsedDays = max(1, $start->diffInDays(min($today, $end)) + 1);
            $totalDays = max(1, $start->diffInDays($end) + 1);
            $avgPerDay = round(((float) $summary['spent']) / $elapsedDays, 2);
            $projected = round($avgPerDay * $totalDays, 2);

            return [
                'budget_id' => $summary['id'],
                'name' => $summary['name'],
                'category_id' => $summary['category_id'],
                'category_name' => $summary['category_name'],
                'budget_amount' => $summary['amount'],
                'spent' => $summary['spent'],
                'average_per_day' => $avgPerDay,
                'projected_spend' => $projected,
                'overspending_likely' => $projected > (float) $summary['amount'],
                'projected_over_by' => max(0, round($projected - (float) $summary['amount'], 2)),
            ];
        });
    }

    public function dashboardStatusForUser(int $userId): array
    {
        $summaries = $this->summaryForUser($userId);
        $alerts = $this->syncAlertsForUser($userId);

        $nearLimit = $summaries->where('percentage', '>=', 80)->where('percentage', '<', 100)->values();
        $exceeded = $summaries->where('percentage', '>=', 100)->values();
        $message = null;

        if ($exceeded->isNotEmpty()) {
            $budget = $exceeded->sortByDesc('percentage')->first();
            $message = "You exceeded your {$budget['category_name']} budget";
        } elseif ($nearLimit->isNotEmpty()) {
            $budget = $nearLimit->sortByDesc('percentage')->first();
            $message = "You are close to exceeding your {$budget['category_name']} budget";
        }

        return [
            'budgets' => $summaries->values(),
            'remaining_budget' => round((float) $summaries->sum('remaining'), 2),
            'near_limit_count' => $nearLimit->count(),
            'exceeded_count' => $exceeded->count(),
            'message' => $message,
            'alerts' => $alerts->take(5)->values(),
        ];
    }

    private function buildBudgetSummary(BudgetPlan $budget): array
    {
        $startDate = $budget->start_date?->copy() ?? now()->startOfMonth();
        $endDate = $budget->end_date?->copy() ?? now()->endOfMonth();
        $expenseDateColumn = Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'expense_date')
            ? 'expense_date'
            : 'date';
        $canFilterGroups = Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'group_id');
        $includeGroupExpenses = Schema::hasTable('budget_plans') && Schema::hasColumn('budget_plans', 'include_group_expenses')
            ? (bool) $budget->include_group_expenses
            : true;

        $spent = (float) Expense::query()
            ->where('user_id', $budget->user_id)
            ->when(
                Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'is_duplicate'),
                fn ($query) => $query->where('is_duplicate', false)
            )
            ->whereBetween($expenseDateColumn, [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->when(
                $canFilterGroups && !$includeGroupExpenses,
                fn ($query) => $query->whereNull('group_id')
            )
            ->when($budget->category_id, fn ($query) => $query->where('category_id', $budget->category_id))
            ->sum('amount');

        $amount = (float) $budget->amount;
        $percentage = $amount > 0 ? round(($spent / $amount) * 100, 2) : 0;

        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'category_id' => $budget->category_id,
            'category_name' => $budget->category?->name ?? 'Overall',
            'period' => $budget->period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'amount' => $amount,
            'spent' => $spent,
            'remaining' => round(max(0, $amount - $spent), 2),
            'percentage' => $percentage,
            'is_alert_80' => $percentage >= 80,
            'is_alert_100' => $percentage >= 100,
        ];
    }
}
