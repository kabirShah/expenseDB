<?php

namespace App\Services;

use App\Models\Expense;

class BudgetMonitorService
{
    public function checkMonthlyBudget($user)
    {
        $prefs = $user->preferences;
        if (!$prefs || !$prefs->monthly_budget) return null;

        $spent = Expense::where('user_id', $user->id)
            ->whereMonth('date', now()->month)
            ->sum('amount');

        $percent = ($spent / $prefs->monthly_budget) * 100;

        if ($percent >= $prefs->warning_threshold) {
            return [
                'type' => 'overspending',
                'message' => "You've used {$percent}% of your monthly budget"
            ];
        }

        return null;
    }
}

