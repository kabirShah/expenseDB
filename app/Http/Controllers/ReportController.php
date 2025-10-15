<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get balance summary for user in a group
     */
    public function getUserBalance(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        try {
            $balanceSummary = $this->reportService->getUserBalanceSummary($userId, $group->id);

            return response()->json([
                'success' => true,
                'data' => $balanceSummary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate balance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get balance summary for all members in a group
     */
    public function getGroupBalances(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        try {
            $groupBalances = $this->reportService->getGroupBalanceSummary($group->id);

            return response()->json([
                'success' => true,
                'data' => $groupBalances
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate group balance summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expense history for user in a group
     */
    public function getExpenseHistory(Group $group, Request $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $limit = $request->get('limit', 20);
            $expenseHistory = $this->reportService->getExpenseHistory($userId, $group->id, $limit);

            return response()->json([
                'success' => true,
                'data' => $expenseHistory
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expense history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate monthly report for a group
     */
    public function getMonthlyReport(Group $group, Request $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'year_month' => 'required|regex:/^\d{4}-\d{2}$/'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $monthlyReport = $this->reportService->generateMonthlyReport($group->id, $request->year_month);

            return response()->json([
                'success' => true,
                'data' => $monthlyReport
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate monthly report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settlement suggestions for a group
     */
    public function getSettlementSuggestions(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        try {
            $suggestions = $this->reportService->getSettlementSuggestions($group->id);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate settlement suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive dashboard data for user
     */
    public function getDashboard(): JsonResponse
    {
        $userId = Auth::id();

        try {
            $userGroups = Group::whereHas('members', function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('status', 'active');
            })->with(['members' => function ($query) {
                $query->active();
            }])->get();

            $dashboardData = [
                'total_groups' => $userGroups->count(),
                'total_balance' => 0,
                'groups_owing_you' => 0,
                'groups_you_owe' => 0,
                'recent_activity' => [],
                'group_summaries' => []
            ];

            foreach ($userGroups as $group) {
                $balanceSummary = $this->reportService->getUserBalanceSummary($userId, $group->id);

                $dashboardData['total_balance'] += $balanceSummary['balance'];

                if ($balanceSummary['balance'] > 0) {
                    $dashboardData['groups_owing_you']++;
                } elseif ($balanceSummary['balance'] < 0) {
                    $dashboardData['groups_you_owe']++;
                }

                $dashboardData['group_summaries'][] = [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'balance' => $balanceSummary['balance'],
                    'member_count' => $group->members->count(),
                    'total_expenses' => $group->getTotalExpenses(),
                    'unsettled_expenses' => count($balanceSummary['unsettled_expenses'])
                ];

                // Add recent expenses to activity
                $recentExpenses = $group->expenseSplits()
                    ->active()
                    ->with('payer:id,name')
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->map(function ($expense) use ($group) {
                        return [
                            'type' => 'expense',
                            'group_name' => $group->name,
                            'title' => $expense->title,
                            'amount' => $expense->total_amount,
                            'paid_by' => $expense->payer->name,
                            'date' => $expense->expense_date,
                            'created_at' => $expense->created_at
                        ];
                    });

                $dashboardData['recent_activity'] = array_merge($dashboardData['recent_activity'], $recentExpenses->toArray());
            }

            // Sort recent activity by date
            usort($dashboardData['recent_activity'], function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            $dashboardData['recent_activity'] = array_slice($dashboardData['recent_activity'], 0, 10);
            $dashboardData['total_balance'] = round($dashboardData['total_balance'], 2);

            return response()->json([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
