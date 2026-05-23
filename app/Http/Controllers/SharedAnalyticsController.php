<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\GroupMember;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SharedAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $groupIds = GroupMember::query()->where('user_id', $userId)->pluck('group_id');

        $sharedExpenseTotal = (float) Expense::query()
            ->whereIn('group_id', $groupIds)
            ->whereNotNull('group_id')
            ->sum('amount');

        $owed = (float) ExpenseSplit::query()
            ->whereIn('group_id', $groupIds)
            ->where('user_id', $userId)
            ->sum('amount_owed');

        $paid = (float) ExpenseSplit::query()
            ->whereIn('group_id', $groupIds)
            ->where('payer_user_id', $userId)
            ->sum('amount_paid');

        $settledOut = (float) Settlement::query()
            ->whereIn('group_id', $groupIds)
            ->where('from_user_id', $userId)
            ->sum('settled_amount');

        $settledIn = (float) Settlement::query()
            ->whereIn('group_id', $groupIds)
            ->where('to_user_id', $userId)
            ->sum('settled_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'group_count' => $groupIds->count(),
                'shared_expense_total' => round($sharedExpenseTotal, 2),
                'total_owed' => round(max(0, $owed - $paid - $settledIn + $settledOut), 2),
                'total_receivable' => round(max(0, $paid - $owed + $settledIn - $settledOut), 2),
                'net_balance' => round(($paid + $settledIn) - ($owed + $settledOut), 2),
                'monthly_trend' => $this->monthlyTrend($groupIds->all()),
                'top_groups' => $this->topGroups($groupIds->all()),
                'category_totals' => $this->categoryTotals($groupIds->all()),
            ],
        ]);
    }

    private function monthlyTrend(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Expense::query()
            ->selectRaw('YEAR(expense_date) as year, MONTH(expense_date) as month, SUM(amount) as total')
            ->whereIn('group_id', $groupIds)
            ->whereNotNull('group_id')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'period' => sprintf('%04d-%02d', $row->year, $row->month),
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function topGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Expense::query()
            ->select('group_id', DB::raw('SUM(amount) as total'))
            ->with('group:id,name,type')
            ->whereIn('group_id', $groupIds)
            ->whereNotNull('group_id')
            ->groupBy('group_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'group' => $row->group,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }

    private function categoryTotals(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return Expense::query()
            ->selectRaw('COALESCE(category_name, "Others") as category, SUM(amount) as total')
            ->whereIn('group_id', $groupIds)
            ->whereNotNull('group_id')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => round((float) $row->total, 2),
            ])
            ->all();
    }
}
