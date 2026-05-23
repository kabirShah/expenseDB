<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function generate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:weekly,monthly,half_yearly,custom',
            'date_from' => 'required_if:type,custom|date',
            'date_to' => 'required_if:type,custom|date|after_or_equal:date_from',
        ]);

        $user = $request->user();
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            $request->input('type'),
            $request->input('date_from'),
            $request->input('date_to')
        );

        $data = $this->buildReportData($user->id, $dateFrom, $dateTo);
        $report = Report::create([
            'user_id' => $user->id,
            'type' => $request->input('type'),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'title' => $this->buildTitle($request->input('type'), $dateFrom, $dateTo),
            'data_snapshot' => $data,
            'generated_at' => now(),
        ]);

        return response()->json([
            'report' => $report,
            'data' => $data,
        ]);
    }

    public function index(Request $request)
    {
        return Report::where('user_id', $request->user()->id)
            ->orderByDesc('generated_at')
            ->paginate(20);
    }

    public function show(Request $request, Report $report)
    {
        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'report' => $report,
            'data' => $report->data_snapshot,
        ]);
    }

    private function resolveDateRange(string $type, ?string $from, ?string $to): array
    {
        return match ($type) {
            'weekly' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'monthly' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'half_yearly' => [now()->subMonths(6)->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => [$from, $to],
        };
    }

    private function buildReportData(int $userId, string $dateFrom, string $dateTo): array
    {
        $base = Transaction::where('user_id', $userId)
            ->whereBetween('transaction_date', [$dateFrom, $dateTo]);

        $totalExpense = (clone $base)->whereIn('type', ['expense', 'debit'])->sum('amount');
        $totalIncome = (clone $base)->whereIn('type', ['income', 'credit'])->sum('amount');

        $byCategory = (clone $base)->whereIn('type', ['expense', 'debit'])
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('COALESCE(categories.name, transactions.category, "Uncategorized") as name')
            ->selectRaw('MAX(categories.color) as color')
            ->selectRaw('MAX(categories.icon) as icon')
            ->selectRaw('SUM(transactions.amount) as total')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('name')
            ->orderByDesc('total')
            ->get();

        $byPaymentMethod = (clone $base)->whereIn('type', ['expense', 'debit'])
            ->selectRaw('COALESCE(payment_method, method, "other") as payment_method')
            ->selectRaw('SUM(amount) as total')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        $bySourceApp = (clone $base)->whereIn('type', ['expense', 'debit'])
            ->whereNotNull('source_app')
            ->select('source_app', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('source_app')
            ->orderByDesc('total')
            ->get();

        $dailyTrend = (clone $base)->whereIn('type', ['expense', 'debit'])
            ->selectRaw('DATE(transaction_date) as date')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $topExpenses = (clone $base)->whereIn('type', ['expense', 'debit'])
            ->with(['categoryRel', 'wallet'])
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        return [
            'summary' => [
                'total_expense' => (float) $totalExpense,
                'total_income' => (float) $totalIncome,
                'net' => (float) $totalIncome - (float) $totalExpense,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
            'by_source_app' => $bySourceApp,
            'daily_trend' => $dailyTrend,
            'top_expenses' => $topExpenses,
        ];
    }

    private function buildTitle(string $type, string $dateFrom, string $dateTo): string
    {
        return match ($type) {
            'weekly' => 'Weekly Report - ' . date('d M Y', strtotime($dateFrom)),
            'monthly' => 'Monthly Report - ' . date('M Y', strtotime($dateFrom)),
            'half_yearly' => '6-Month Report - ' . date('M Y', strtotime($dateFrom)) . ' to ' . date('M Y', strtotime($dateTo)),
            default => 'Custom Report - ' . date('d M Y', strtotime($dateFrom)) . ' to ' . date('d M Y', strtotime($dateTo)),
        };
    }
}
