<?php

namespace App\Services;

use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Get balance summary for a user in a group
     *
     * @param int $userId
     * @param int $groupId
     * @return array
     */
    public function getUserBalanceSummary(int $userId, int $groupId): array
    {
        $group = Group::findOrFail($groupId);
        
        if (!$group->isMember($userId)) {
            return [
                'balance' => 0,
                'total_paid' => 0,
                'total_owed' => 0,
                'unsettled_expenses' => [],
                'settlements' => []
            ];
        }

        // Calculate from expense splits
        $expenseSplits = $group->expenseSplits()->active()->get();
        $totalPaid = 0;
        $totalOwed = 0;
        $unsettledExpenses = [];

        foreach ($expenseSplits as $expense) {
            $userShare = $expense->getUserShare($userId);
            
            if ($userShare) {
                $totalPaid += $userShare['amount_paid'];
                $totalOwed += $userShare['amount_owed'];
                
                if ($userShare['status'] !== 'paid') {
                    $unsettledExpenses[] = [
                        'expense_id' => $expense->id,
                        'title' => $expense->title,
                        'amount_owed' => $userShare['amount_owed'],
                        'amount_paid' => $userShare['amount_paid'],
                        'balance' => $userShare['amount_paid'] - $userShare['amount_owed']
                    ];
                }
            }
        }

        // Calculate from settlements
        $incomingSettlements = $group->settlements()
            ->completed()
            ->where('paid_to', $userId)
            ->sum('amount');

        $outgoingSettlements = $group->settlements()
            ->completed()
            ->where('paid_by', $userId)
            ->sum('amount');

        $netSettlements = $incomingSettlements - $outgoingSettlements;

        // Final balance: (paid - owed) + net settlements
        $balance = ($totalPaid - $totalOwed) + $netSettlements;

        return [
            'user_id' => $userId,
            'group_id' => $groupId,
            'balance' => round($balance, 2),
            'total_paid' => round($totalPaid, 2),
            'total_owed' => round($totalOwed, 2),
            'net_settlements' => round($netSettlements, 2),
            'unsettled_expenses' => $unsettledExpenses,
            'settlements' => $this->getUserSettlements($userId, $groupId)
        ];
    }

    /**
     * Get all users' balance summary for a group
     *
     * @param int $groupId
     * @return Collection
     */
    public function getGroupBalanceSummary(int $groupId): Collection
    {
        $group = Group::findOrFail($groupId);
        $members = $group->activeMembers()->get();
        $summaries = collect();

        foreach ($members as $member) {
            $summary = $this->getUserBalanceSummary($member->user_id, $groupId);
            $summaries->push($summary);
        }

        // Verify total balance is zero (accounting check)
        $totalBalance = $summaries->sum('balance');
        $summaries->put('accounting_check', [
            'total_balance' => round($totalBalance, 2),
            'is_balanced' => abs($totalBalance) < 0.01
        ]);

        return $summaries;
    }

    /**
     * Get settlement suggestions for a group
     *
     * @param int $groupId
     * @return array
     */
    public function getSettlementSuggestions(int $groupId): array
    {
        $balances = $this->getGroupBalanceSummary($groupId);
        $debtors = $balances->filter(function ($summary) {
            return $summary['balance'] < 0;
        })->values();

        $creditors = $balances->filter(function ($summary) {
            return $summary['balance'] > 0;
        })->values();

        $suggestions = [];

        // Simple settlement: match largest debtor with largest creditor
        while ($debtors->isNotEmpty() && $creditors->isNotEmpty()) {
            $debtor = $debtors->first();
            $creditor = $creditors->first();

            $settlementAmount = min(abs($debtor['balance']), $creditor['balance']);

            $suggestions[] = [
                'from_user_id' => $debtor['user_id'],
                'to_user_id' => $creditor['user_id'],
                'amount' => round($settlementAmount, 2),
                'description' => 'Suggested settlement to balance group'
            ];

            // Update balances
            $debtor['balance'] += $settlementAmount;
            $creditor['balance'] -= $settlementAmount;

            // Remove if settled
            if ($debtor['balance'] >= -0.01) {
                $debtors = $debtors->filter(function ($d) use ($debtor) {
                    return $d['user_id'] !== $debtor['user_id'];
                });
            }

            if ($creditor['balance'] <= 0.01) {
                $creditors = $creditors->filter(function ($c) use ($creditor) {
                    return $c['user_id'] !== $creditor['user_id'];
                });
            }
        }

        return $suggestions;
    }

    /**
     * Get user's settlements in a group
     *
     * @param int $userId
     * @param int $groupId
     * @return array
     */
    private function getUserSettlements(int $userId, int $groupId): array
    {
        $incoming = Settlement::where('group_id', $groupId)
            ->where('paid_to', $userId)
            ->completed()
            ->orderBy('settled_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($settlement) {
                return [
                    'id' => $settlement->id,
                    'from_user_id' => $settlement->paid_by,
                    'amount' => $settlement->amount,
                    'description' => $settlement->description,
                    'settled_at' => $settlement->settled_at
                ];
            });

        $outgoing = Settlement::where('group_id', $groupId)
            ->where('paid_by', $userId)
            ->completed()
            ->orderBy('settled_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($settlement) {
                return [
                    'id' => $settlement->id,
                    'to_user_id' => $settlement->paid_to,
                    'amount' => -$settlement->amount,
                    'description' => $settlement->description,
                    'settled_at' => $settlement->settled_at
                ];
            });

        return $incoming->merge($outgoing)->sortByDesc('settled_at')->values()->toArray();
    }

    /**
     * Get expense history for user in group
     *
     * @param int $userId
     * @param int $groupId
     * @param int $limit
     * @return Collection
     */
    public function getExpenseHistory(int $userId, int $groupId, int $limit = 20): Collection
    {
        $expenses = ExpenseSplit::where('group_id', $groupId)
            ->active()
            ->orderBy('expense_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($expense) use ($userId) {
                $userShare = $expense->getUserShare($userId);
                
                return [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'total_amount' => $expense->total_amount,
                    'paid_by' => $expense->paid_by,
                    'user_share' => $userShare ? $userShare['amount_owed'] : 0,
                    'user_paid' => $userShare ? $userShare['amount_paid'] : 0,
                    'status' => $userShare ? $userShare['status'] : 'not_involved',
                    'expense_date' => $expense->expense_date,
                    'category' => $expense->category
                ];
            });

        return $expenses;
    }

    /**
     * Generate monthly report for group
     *
     * @param int $groupId
     * @param string $yearMonth Format: '2024-01'
     * @return array
     */
    public function generateMonthlyReport(int $groupId, string $yearMonth): array
    {
        $startDate = $yearMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $expenses = ExpenseSplit::where('group_id', $groupId)
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->get();

        $totalExpenses = $expenses->sum('total_amount');
        $settlements = Settlement::where('group_id', $groupId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->completed()
            ->get();

        $totalSettled = $settlements->sum('amount');

        $categoryBreakdown = $expenses->groupBy('category')
            ->map(function ($categoryExpenses, $category) {
                return [
                    'category' => $category ?: 'Uncategorized',
                    'total' => $categoryExpenses->sum('total_amount'),
                    'count' => $categoryExpenses->count()
                ];
            })->values();

        return [
            'group_id' => $groupId,
            'period' => $yearMonth,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_expenses' => round($totalExpenses, 2),
            'total_settled' => round($totalSettled, 2),
            'net_spent' => round($totalExpenses - $totalSettled, 2),
            'category_breakdown' => $categoryBreakdown,
            'expense_count' => $expenses->count(),
            'settlement_count' => $settlements->count()
        ];
    }
}
