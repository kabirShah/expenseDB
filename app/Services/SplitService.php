<?php

namespace App\Services;

use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Support\Collection;

class SplitService
{
    /**
     * Calculate equal split for an expense
     *
     * @param float $totalAmount
     * @param array $memberIds
     * @return array
     */
    public function calculateEqualSplit(float $totalAmount, array $memberIds): array
    {
        $memberCount = count($memberIds);
        if ($memberCount === 0) {
            return [];
        }

        $shareAmount = round($totalAmount / $memberCount, 2);
        $splits = [];

        foreach ($memberIds as $userId) {
            $splits[] = [
                'user_id' => $userId,
                'amount_owed' => $shareAmount,
                'amount_paid' => 0,
                'status' => 'pending'
            ];
        }

        // Adjust for rounding differences
        $totalSplit = array_sum(array_column($splits, 'amount_owed'));
        $difference = round($totalAmount - $totalSplit, 2);

        if ($difference !== 0.00) {
            $splits[0]['amount_owed'] += $difference;
        }

        return $splits;
    }

    /**
     * Calculate exact split for an expense
     *
     * @param array $exactShares Array of ['user_id' => int, 'amount' => float]
     * @return array
     */
    public function calculateExactSplit(array $exactShares): array
    {
        $splits = [];

        foreach ($exactShares as $share) {
            $splits[] = [
                'user_id' => $share['user_id'],
                'amount_owed' => round($share['amount'], 2),
                'amount_paid' => 0,
                'status' => 'pending'
            ];
        }

        return $splits;
    }

    /**
     * Calculate percentage split for an expense
     *
     * @param float $totalAmount
     * @param array $percentageShares Array of ['user_id' => int, 'percentage' => float]
     * @return array
     */
    public function calculatePercentageSplit(float $totalAmount, array $percentageShares): array
    {
        $splits = [];

        foreach ($percentageShares as $share) {
            $amount = round(($totalAmount * $share['percentage']) / 100, 2);
            $splits[] = [
                'user_id' => $share['user_id'],
                'amount_owed' => $amount,
                'amount_paid' => 0,
                'status' => 'pending'
            ];
        }

        // Adjust for rounding differences
        $totalSplit = array_sum(array_column($splits, 'amount_owed'));
        $difference = round($totalAmount - $totalSplit, 2);

        if ($difference !== 0.00 && count($splits) > 0) {
            $splits[0]['amount_owed'] += $difference;
        }

        return $splits;
    }

    /**
     * Create expense split based on type
     *
     * @param array $data
     * @return ExpenseSplit
     */
    public function createExpenseSplit(array $data): ExpenseSplit
    {
        $group = Group::findOrFail($data['group_id']);
        $members = $group->activeMembers()->pluck('user_id')->toArray();

        $splitDetails = match($data['split_type']) {
            'equal' => $this->calculateEqualSplit($data['total_amount'], $members),
            'exact' => $this->calculateExactSplit($data['exact_shares'] ?? []),
            'percentage' => $this->calculatePercentageSplit($data['total_amount'], $data['percentage_shares'] ?? []),
            default => throw new \InvalidArgumentException('Invalid split type')
        };

        return ExpenseSplit::create([
            'group_id' => $data['group_id'],
            'paid_by' => $data['paid_by'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'total_amount' => $data['total_amount'],
            'split_type' => $data['split_type'],
            'split_details' => $splitDetails,
            'status' => 'active',
            'expense_date' => $data['expense_date'] ?? now(),
            'category' => $data['category'] ?? null,
            'receipt_images' => $data['receipt_images'] ?? null,
        ]);
    }

    /**
     * Update user payment in expense split
     *
     * @param ExpenseSplit $expenseSplit
     * @param int $userId
     * @param float $amount
     * @return bool
     */
    public function updateUserPayment(ExpenseSplit $expenseSplit, int $userId, float $amount): bool
    {
        $splitDetails = $expenseSplit->getSplitDetails();

        foreach ($splitDetails as &$split) {
            if ($split['user_id'] == $userId) {
                $split['amount_paid'] = round($amount, 2);
                $split['status'] = $amount >= $split['amount_owed'] ? 'paid' : 'partial';
                break;
            }
        }

        return $expenseSplit->update(['split_details' => $splitDetails->toArray()]);
    }

    /**
     * Check if expense is fully settled
     *
     * @param ExpenseSplit $expenseSplit
     * @return bool
     */
    public function isExpenseSettled(ExpenseSplit $expenseSplit): bool
    {
        $splitDetails = $expenseSplit->getSplitDetails();

        foreach ($splitDetails as $split) {
            if ($split['amount_paid'] < $split['amount_owed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get unsettled amount for expense
     *
     * @param ExpenseSplit $expenseSplit
     * @return float
     */
    public function getUnsettledAmount(ExpenseSplit $expenseSplit): float
    {
        $splitDetails = $expenseSplit->getSplitDetails();
        $totalOwed = $splitDetails->sum('amount_owed');
        $totalPaid = $splitDetails->sum('amount_paid');

        return round($totalOwed - $totalPaid, 2);
    }

    /**
     * Validate split data
     *
     * @param array $data
     * @return array
     */
    public function validateSplitData(array $data): array
    {
        $errors = [];

        if (!isset($data['total_amount']) || $data['total_amount'] <= 0) {
            $errors[] = 'Total amount must be greater than 0';
        }

        if (!isset($data['split_type']) || !in_array($data['split_type'], ['equal', 'exact', 'percentage'])) {
            $errors[] = 'Invalid split type';
        }

        if ($data['split_type'] === 'exact' && (!isset($data['exact_shares']) || empty($data['exact_shares']))) {
            $errors[] = 'Exact shares are required for exact split type';
        }

        if ($data['split_type'] === 'percentage' && (!isset($data['percentage_shares']) || empty($data['percentage_shares']))) {
            $errors[] = 'Percentage shares are required for percentage split type';
        }

        if ($data['split_type'] === 'percentage') {
            $totalPercentage = array_sum(array_column($data['percentage_shares'], 'percentage'));
            if (abs($totalPercentage - 100) > 0.01) {
                $errors[] = 'Percentage shares must total 100%';
            }
        }

        return $errors;
    }
}
