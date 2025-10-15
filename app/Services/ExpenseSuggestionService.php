<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSuggestion;
use Carbon\Carbon;

class ExpenseSuggestionService
{
    /**
     * Generate expense suggestions for a user based on past expenses.
     *
     * @param int $userId
     * @return void
     */
    public function generateSuggestions($userId)
    {
        // Get expenses from last 6 months
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        $expenses = Expense::where('user_id', $userId)
            ->where('date', '>=', $sixMonthsAgo)
            ->where('status', 'active')
            ->orderBy('date', 'desc')
            ->get();

        if ($expenses->isEmpty()) {
            return;
        }

        // Group expenses by category and analyze patterns
        $categoryGroups = $expenses->groupBy('category');

        foreach ($categoryGroups as $category => $categoryExpenses) {
            $this->analyzeCategoryExpenses($userId, $category, $categoryExpenses);
        }
    }

    /**
     * Analyze expenses for a specific category to detect recurring patterns.
     *
     * @param int $userId
     * @param string $category
     * @param \Illuminate\Support\Collection $expenses
     * @return void
     */
    private function analyzeCategoryExpenses($userId, $category, $expenses)
    {
        // Group by month to check frequency
        $monthlyGroups = $expenses->groupBy(function ($expense) {
            return $expense->date->format('Y-m');
        });

        // Check if category appears in at least 3 different months
        if ($monthlyGroups->count() < 3) {
            return;
        }

        // Calculate average amount
        $averageAmount = $expenses->avg('amount');

        // Get most recent expense description for this category
        $latestExpense = $expenses->first();
        $description = $latestExpense->description ?: "Recurring {$category} expense";

        // Check if suggestion already exists and is unseen
        $existingSuggestion = ExpenseSuggestion::where('user_id', $userId)
            ->where('category', $category)
            ->where('is_shown', false)
            ->first();

        if ($existingSuggestion) {
            // Update existing suggestion with new average
            $existingSuggestion->update([
                'suggested_amount' => round($averageAmount, 2),
                'description' => $description,
            ]);
        } else {
            // Create new suggestion
            ExpenseSuggestion::create([
                'user_id' => $userId,
                'suggested_amount' => round($averageAmount, 2),
                'category' => $category,
                'description' => $description,
                'is_shown' => false,
            ]);
        }
    }

    /**
     * Get unseen suggestions for a user.
     *
     * @param int $userId
     * @return \Illuminate\Support\Collection
     */
    public function getUnseenSuggestions($userId)
    {
        return ExpenseSuggestion::where('user_id', $userId)
            ->unseen()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark suggestion as shown.
     *
     * @param int $suggestionId
     * @param int $userId
     * @return bool
     */
    public function markAsShown($suggestionId, $userId)
    {
        $suggestion = ExpenseSuggestion::where('id', $suggestionId)
            ->where('user_id', $userId)
            ->first();

        if ($suggestion) {
            $suggestion->update(['is_shown' => true]);
            return true;
        }

        return false;
    }
}
