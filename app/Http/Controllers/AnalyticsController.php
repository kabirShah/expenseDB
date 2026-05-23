<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\Expense;
use App\Models\Balance;
use App\Models\MultiExpense;

class AnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /*
    |--------------------------------------------------------------------------
    | BASE EXPENSE QUERY (Reusable)
    |--------------------------------------------------------------------------
    */
    private function expenseQuery($userId)
    {
        return Expense::active()->where('user_id', $userId);
    }

    private function multiExpenseQuery($userId)
    {
        return MultiExpense::where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (Expense + Multi-Expense + Balance)
    |--------------------------------------------------------------------------
    */
    public function summary(Request $request)
    {
        $user = $request->user();

        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        $total = $this->expenseQuery($user->id)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $multiTotal = $this->multiExpenseQuery($user->id)
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $currentBalance = Balance::where('user_id', $user->id)
            ->latest('date_added')
            ->value('amount') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'expense_total' => (float) $total,
                'multi_expense_total' => (float) $multiTotal,
                'total_spend' => (float) $total + (float) $multiTotal,
                'current_balance' => (float) $currentBalance,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MONTHLY TREND (Expense + Multi-Expense)
    |--------------------------------------------------------------------------
    */
    public function monthlyTrend(Request $request)
    {
        $user = $request->user();
        $months = max(3, min(24, (int) $request->query('months', 6)));
        $startMonth = now()->startOfMonth()->subMonths($months - 1);

        $expenseRows = $this->expenseQuery($user->id)
            ->where('date', '>=', $startMonth)
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->get();

        $multiRows = $this->multiExpenseQuery($user->id)
            ->where('created_at', '>=', $startMonth)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total_amount) as total')
            ->groupBy('year', 'month')
            ->get();

        $expenseMap = $expenseRows->mapWithKeys(function ($row) {
            return [sprintf('%04d-%02d', $row->year, $row->month) => (float) $row->total];
        });

        $multiMap = $multiRows->mapWithKeys(function ($row) {
            return [sprintf('%04d-%02d', $row->year, $row->month) => (float) $row->total];
        });

        $data = collect();
        for ($i = 0; $i < $months; $i++) {
            $date = $startMonth->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $expenseTotal = $expenseMap->get($key, 0.0);
            $multiTotal = $multiMap->get($key, 0.0);

            $data->push([
                'year' => (int) $date->year,
                'month' => (int) $date->month,
                'label' => $date->format('M Y'),
                'expense_total' => $expenseTotal,
                'multi_expense_total' => $multiTotal,
                'total_spend' => $expenseTotal + $multiTotal,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DAILY TREND (Expense + Multi-Expense)
    |--------------------------------------------------------------------------
    */
    public function dailyTrend(Request $request)
    {
        $user = $request->user();

        $month = $request->query('month', now()->month);
        $year  = $request->query('year', now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        $expenseRows = $this->expenseQuery($user->id)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('DAY(date) as day, SUM(amount) as total')
            ->groupBy('day')
            ->get();

        $multiRows = $this->multiExpenseQuery($user->id)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DAY(created_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->get();

        $expenseMap = $expenseRows->mapWithKeys(function ($row) {
            return [(int) $row->day => (float) $row->total];
        });

        $multiMap = $multiRows->mapWithKeys(function ($row) {
            return [(int) $row->day => (float) $row->total];
        });

        $data = collect();
        for ($day = 1; $day <= $end->day; $day++) {
            $expenseTotal = $expenseMap->get($day, 0.0);
            $multiTotal = $multiMap->get($day, 0.0);

            $data->push([
                'day' => $day,
                'expense_total' => $expenseTotal,
                'multi_expense_total' => $multiTotal,
                'total_spend' => $expenseTotal + $multiTotal,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BALANCE TREND (Line Chart)
    |--------------------------------------------------------------------------
    */
    public function balanceTrends(Request $request)
    {
        $user = $request->user();

        $startDate = Carbon::parse(
            $request->query('start_date', now()->subYear())
        )->startOfDay();

        $endDate = Carbon::parse(
            $request->query('end_date', now())
        )->endOfDay();

        $balances = Balance::where('user_id', $user->id)
            ->whereBetween('date_added', [$startDate, $endDate])
            ->orderBy('date_added')
            ->get(['amount', 'date_added']);

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }
}
