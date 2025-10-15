<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Expense;
use App\Models\Balance;

class AnalyticsController extends Controller
{
    /**
     * Get year-wise expense totals for the authenticated user.
     * Optional filters: start_year, end_year
     */
    public function yearWiseExpenses(Request $request)
    {
        $user = $request->user();

        $startYear = $request->query('start_year', 2000);
        $endYear = $request->query('end_year', now()->year);

        $expenses = Expense::active()
            ->where('user_id', $user->id)
            ->whereYear('date', '>=', $startYear)
            ->whereYear('date', '<=', $endYear)
            ->selectRaw('YEAR(date) as year, SUM(amount) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    /**
     * Get category-wise expense breakdown for a given year or date range.
     * Query params: year, start_date, end_date
     */
    public function categoryBreakdown(Request $request)
    {
        $user = $request->user();

        $year = $request->query('year');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Expense::active()->where('user_id', $user->id);

        if ($year) {
            $query->whereYear('date', $year);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('date', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        } else {
            // Default to current year
            $query->whereYear('date', now()->year);
        }

        $categoryData = $query->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categoryData,
        ]);
    }

    /**
     * Get balance trends over time.
     * Query params: start_date, end_date
     */
    public function balanceTrends(Request $request)
    {
        $user = $request->user();

        $startDate = $request->query('start_date', now()->subYear()->startOfDay());
        $endDate = $request->query('end_date', now()->endOfDay());

        $balances = Balance::where('user_id', $user->id)
            ->whereBetween('date_added', [Carbon::parse($startDate), Carbon::parse($endDate)])
            ->orderBy('date_added')
            ->get(['amount', 'date_added']);

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    /**
     * Get analytics data for graphs.
     * Query params: category (optional), month (optional), year (optional)
     */
    public function graphs(Request $request)
    {
        $user = $request->user();

        $category = $request->query('category');
        $month = $request->query('month');
        $year = $request->query('year');

        // Query regular expenses
        $expenseQuery = Expense::active()->where('user_id', $user->id);
        if ($category) $expenseQuery->where('category', $category);
        if ($month && $year) $expenseQuery->whereMonth('date', $month)->whereYear('date', $year);
        elseif ($year) $expenseQuery->whereYear('date', $year);
        else {
            // Default to current month and year
            $expenseQuery->whereMonth('date', now()->month)->whereYear('date', now()->year);
        }
        $expenses = $expenseQuery->get(['category', 'amount', 'date']);

        // Query multi-expense shares
        $multiQuery = \App\Models\MultiExpenseMember::where('user_id', $user->id)->with('multiExpense');
        if ($category) $multiQuery->whereHas('multiExpense', fn($q) => $q->where('category', $category));
        if ($month && $year) $multiQuery->whereHas('multiExpense', fn($q) => $q->whereMonth('created_at', $month)->whereYear('created_at', $year));
        elseif ($year) $multiQuery->whereHas('multiExpense', fn($q) => $q->whereYear('created_at', $year));
        else {
            // Default to current month and year
            $multiQuery->whereHas('multiExpense', fn($q) => $q->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year));
        }
        $multiExpenses = $multiQuery->get()->map(function ($member) {
            return [
                'category' => $member->multiExpense->category,
                'amount' => $member->amount_owed,
                'date' => $member->multiExpense->created_at->toDateString(),
            ];
        });

        return response()->json([
            'success' => true,
            'transactions' => $expenses,
            'multi_transactions' => $multiExpenses,
        ]);
    }

    public function transactionsGraphs(Request $request)
    {
        $user = $request->user();

        $category = $request->query('category');
        $month = $request->query('month');
        $year = $request->query('year');

        // Query regular expenses
        $expenseQuery = Expense::active()->where('user_id', $user->id);
        if ($category) $expenseQuery->where('category', $category);
        if ($month && $year) $expenseQuery->whereMonth('date', $month)->whereYear('date', $year);
        elseif ($year) $expenseQuery->whereYear('date', $year);
        else {
            // Default to current month and year
            $expenseQuery->whereMonth('date', now()->month)->whereYear('date', now()->year);
        }
        $expenses = $expenseQuery->get(['category', 'amount', 'date']);

        return response()->json([
            'success' => true,
            'transactions' => $expenses,
        ]);
    }

    public function multiTransactionsGraphs(Request $request)
    {
        $user = $request->user();

        $category = $request->query('category');
        $month = $request->query('month');
        $year = $request->query('year');

        // Query multi-expenses
        $multiQuery = \App\Models\MultiExpense::where('user_id', $user->id);
        if ($category) $multiQuery->where('category', $category);
        if ($month && $year) $multiQuery->whereMonth('created_at', $month)->whereYear('created_at', $year);
        elseif ($year) $multiQuery->whereYear('created_at', $year);
        else {
            // Default to current month and year
            $multiQuery->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
        }
        $multiExpenses = $multiQuery->get()->map(function ($multiExpense) {
            return [
                'category' => $multiExpense->category,
                'amount' => $multiExpense->total_amount,
                'date' => $multiExpense->created_at->toDateString(),
            ];
        });

        return response()->json([
            'success' => true,
            'multi_transactions' => $multiExpenses,
        ]);
    }
}
