<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Balance;
use App\Models\Expense;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Default to current month/year if not provided
        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = (clone $startOfMonth)->endOfMonth();
        $startOfYear  = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear    = (clone $startOfYear)->endOfYear();

        // -------- Balances --------
        $totalBalance = (float) Balance::where('user_id', $user->id)->sum('amount');

        // -------- Expenses --------
        $todayExpense = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->sum('amount');

        $monthExpense = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $yearExpense = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->sum('amount');

        // -------- Savings calculation --------
        $monthSaving = $totalBalance - $monthExpense;
        $yearSaving  = $totalBalance - $yearExpense;

        // -------- Recent Lists --------
        $recentBalances = Balance::where('user_id', $user->id)
            ->orderByDesc('date_added')
            ->limit(5)
            ->get(['id', 'amount', 'source', 'date_added']);

        $recentExpenses = Expense::active()
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->limit(5)
            ->get(['id', 'amount', 'category', 'transaction_type', 'description', 'date']);

        // (Optional) If you want transactions, ensure Transaction model exists with user_id + date
        $recentTransactions = class_exists(Transaction::class)
            ? Transaction::where('user_id', $user->id)
            ->orderByDesc('transaction_date')
            ->limit(5)
            ->get(['id', 'type', 'amount', 'status', 'transaction_date'])
            : [];

        // -------- Breakdown by category (current month) --------
        $categoryBreakdown = Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'success' => true,
            'user' => [
                'id'   => $user->id,
                'name' => $user->name,
                'email'=> $user->email,
            ],
            'filters' => [
                'month' => $month,
                'year'  => $year,
            ],
            'totals' => [
                'balance'        => $totalBalance,
                'today_expense'  => $todayExpense,
                'month_expense'  => $monthExpense,
                'year_expense'   => $yearExpense,
                'month_saving'   => $monthSaving,
                'year_saving'    => $yearSaving,
            ],
            'recent' => [
                'balances'     => $recentBalances,
                'expenses'     => $recentExpenses,
                'transactions' => $recentTransactions,
            ],
            'breakdowns' => [
                'category_month' => $categoryBreakdown,
            ],
        ]);
    }
}
