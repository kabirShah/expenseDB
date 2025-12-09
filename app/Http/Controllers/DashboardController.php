<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Balance;
use App\Models\Expense;
use App\Models\MultiExpense;

use App\Models\Transaction;

class DashboardController extends Controller
{
   public function index(Request $request)
    {
        $user = $request->user();

        $month = (int) $request->query('month', now()->month);
        $year  = (int) $request->query('year', now()->year);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = (clone $startOfMonth)->endOfMonth();
        $startOfYear  = Carbon::create($year, 1, 1)->startOfDay();
        $endOfYear    = (clone $startOfYear)->endOfYear();

        // ---------------- Balances ----------------
        $totalBalance = (float) Balance::where('user_id', $user->id)->sum('amount');

        // ---------------- Single Expenses ----------------
        $todaySingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereDate('date', today())
            ->sum('amount');

        $monthSingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $yearSingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->sum('amount');

        // ---------------- Multi Expenses (ADDED) ----------------
        $todayMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->sum('total_amount');

        $monthMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $yearMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->sum('total_amount');

        // ---------------- Savings ----------------
        $monthSaving = $totalBalance - ($monthSingle + $monthMulti);
        $yearSaving  = $totalBalance - ($yearSingle + $yearMulti);

        // ---------------- Recent Balances ----------------
        $recentBalances = Balance::where('user_id', $user->id)
            ->orderByDesc('date_added')
            ->limit(5)
            ->get(['id', 'amount', 'source', 'date_added']);

        // ---------------- Recent Single Expenses ----------------
        $recentSingle = Expense::active()
            ->where('user_id', $user->id)
            ->with('category:id,name,parent_id')
            ->orderByDesc('date')
            ->limit(5)
            ->get([
                'id','amount','category_id','transaction_type',
                'description','date'
            ]);

        // ---------------- Recent Multi Expenses (ADDED) ----------------
        $recentMulti = MultiExpense::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get([
                'id','title','total_amount','created_at','category'
            ]);

        // ---------------- Recent Transactions ----------------
        $recentTransactions =
            class_exists(Transaction::class)
                ? Transaction::where('user_id', $user->id)
                    ->orderByDesc('transaction_date')
                    ->limit(5)
                    ->get(['id','type','amount','status','transaction_date'])
                : [];

        // ---------------- Category Breakdown (Single Only For Now) ----------------
        $categoryBreakdown = Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->join('categories', 'categories.id', '=', 'expenses.category_id')
            ->selectRaw('categories.name as category, SUM(expenses.amount) as total')
            ->groupBy('categories.name')
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
                'today_expense'  => $todaySingle + $todayMulti,
                'month_expense'  => $monthSingle + $monthMulti,
                'year_expense'   => $yearSingle + $yearMulti,
                'month_saving'   => $monthSaving,
                'year_saving'    => $yearSaving,
            ],

            'recent' => [
                'balances'     => $recentBalances,
                'expenses'     => $recentSingle,
                'multi'        => $recentMulti,   // 👈 ADDED
                'transactions' => $recentTransactions,
            ],

            'breakdowns' => [
                'category_month' => $categoryBreakdown,
            ],
        ]);
    }
}
