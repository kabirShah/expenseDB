<?php

namespace App\Services;

use App\Models\ExpenseCore;
use App\Models\ExpenseShare;
use App\Repositories\ExpenseRepository;
use App\Repositories\ExpenseShareRepository;
use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class ExpenseService
{
    protected $expenseRepository;
    protected $expenseShareRepository;
    protected $categoryRepository;
    protected $autoSplitterService;
    protected $aiTaggingService;
    protected $confidenceScoreService;

    public function __construct(
        ExpenseRepository $expenseRepository,
        ExpenseShareRepository $expenseShareRepository,
        CategoryRepository $categoryRepository,
        AutoSplitterService $autoSplitterService,
        AITaggingService $aiTaggingService,
        ConfidenceScoreService $confidenceScoreService
    ) {
        $this->expenseRepository = $expenseRepository;
        $this->expenseShareRepository = $expenseShareRepository;
        $this->categoryRepository = $categoryRepository;
        $this->autoSplitterService = $autoSplitterService;
        $this->aiTaggingService = $aiTaggingService;
        $this->confidenceScoreService = $confidenceScoreService;
    }

    public function createExpense(array $data): ExpenseCore
    {
        DB::beginTransaction();
        try {
            // Auto-tag categories
            $data['tags'] = $this->aiTaggingService->tagExpense($data['description'] ?? $data['title']);

            // Calculate confidence score
            $data['confidence_score'] = $this->confidenceScoreService->calculateScore($data);

            // Create expense
            $expense = $this->expenseRepository->create($data);

            // Create expense shares based on split type
            $this->createExpenseShares($expense, $data['participants'], $data['split_type']);

            // Auto-suggest splits for future expenses
            $this->autoSplitterService->learnFromExpense($expense);

            DB::commit();
            return $expense->load('expenseShares.user');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateExpense($id, array $data): ?ExpenseCore
    {
        DB::beginTransaction();
        try {
            $expense = $this->expenseRepository->find($id);
            if (!$expense) {
                return null;
            }

            // Recalculate confidence score
            $data['confidence_score'] = $this->confidenceScoreService->calculateScore($data);

            $this->expenseRepository->update($id, $data);

            // Update shares if participants changed
            if (isset($data['participants'])) {
                $this->updateExpenseShares($expense, $data['participants'], $data['split_type']);
            }

            DB::commit();
            return $expense->fresh(['expenseShares.user']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteExpense($id): bool
    {
        $expense = $this->expenseRepository->find($id);
        if (!$expense) {
            return false;
        }

        // Delete associated shares
        $expense->expenseShares()->delete();

        return $this->expenseRepository->delete($id);
    }

    public function getExpensesByGroup($groupId, $perPage = 15)
    {
        return $this->expenseRepository->getByGroup($groupId, $perPage);
    }

    public function getExpensesByPayer($payerId, $perPage = 15)
    {
        return $this->expenseRepository->getByPayer($payerId, $perPage);
    }

    public function settleExpense($expenseId): bool
    {
        $expense = $this->expenseRepository->find($expenseId);
        if (!$expense) {
            return false;
        }

        $expense->markAsSettled();
        return true;
    }

    public function getAutoSplitSuggestions($groupId, $description): array
    {
        return $this->autoSplitterService->getSuggestions($groupId, $description);
    }

    public function detectDuplicateExpenses($groupId, $amount, $description, $date): Collection
    {
        return $this->confidenceScoreService->findDuplicates($groupId, $amount, $description, $date);
    }

    public function getExpenseAnalytics($groupId, $startDate = null, $endDate = null): array
    {
        $query = ExpenseCore::byGroup($groupId);

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        $expenses = $query->get();

        return [
            'total_expenses' => $expenses->count(),
            'total_amount' => $expenses->sum('amount'),
            'average_expense' => $expenses->avg('amount'),
            'settled_amount' => $expenses->where('status', 'settled')->sum('amount'),
            'unsettled_amount' => $expenses->where('status', '!=', 'settled')->sum('amount'),
            'category_breakdown' => $expenses->groupBy('tags')->map->sum('amount'),
            'monthly_trend' => $expenses->groupBy(function ($expense) {
                return $expense->expense_date->format('Y-m');
            })->map->sum('amount'),
        ];
    }

    protected function createExpenseShares(ExpenseCore $expense, array $participants, string $splitType): void
    {
        $totalAmount = $expense->amount;
        $participantCount = count($participants);

        foreach ($participants as $participant) {
            $amountOwed = $this->calculateShareAmount($totalAmount, $participant, $splitType, $participantCount);

            $this->expenseShareRepository->create([
                'expense_id' => $expense->id,
                'user_id' => $participant['user_id'],
                'amount_owed' => $amountOwed,
                'share_details' => $participant['share_details'] ?? null,
            ]);
        }
    }

    protected function updateExpenseShares(ExpenseCore $expense, array $participants, string $splitType): void
    {
        // Delete existing shares
        $expense->expenseShares()->delete();

        // Create new shares
        $this->createExpenseShares($expense, $participants, $splitType);
    }

    protected function calculateShareAmount(float $totalAmount, array $participant, string $splitType, int $participantCount): float
    {
        switch ($splitType) {
            case 'equal':
                return round($totalAmount / $participantCount, 2);

            case 'exact':
                return $participant['amount'] ?? 0;

            case 'percentage':
                return round($totalAmount * ($participant['percentage'] / 100), 2);

            case 'ratio':
                $totalRatio = array_sum(array_column($participants, 'ratio'));
                return round($totalAmount * ($participant['ratio'] / $totalRatio), 2);

            case 'income-based':
                // This would require additional income data
                return round($totalAmount / $participantCount, 2);

            default:
                return round($totalAmount / $participantCount, 2);
        }
    }
}
