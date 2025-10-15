<?php

namespace App\Services;

use App\Models\ExpenseCore;
use App\Repositories\ExpenseRepository;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ConfidenceScoreService
{
    protected $expenseRepository;

    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    public function calculateScore(array $expenseData): float
    {
        $score = 1.0; // Start with perfect score

        // Reduce score for potential issues
        $score -= $this->checkAmountOutlier($expenseData);
        $score -= $this->checkDuplicatePotential($expenseData);
        $score -= $this->checkDescriptionQuality($expenseData);
        $score -= $this->checkDateAnomaly($expenseData);

        return max(0.0, min(1.0, $score)); // Clamp between 0 and 1
    }

    public function findDuplicates($groupId, $amount, $description, $date, $threshold = 0.8): Collection
    {
        $expenses = $this->expenseRepository->getByGroup($groupId, 100);

        $duplicates = collect();

        foreach ($expenses as $expense) {
            $similarity = $this->calculateSimilarity($expense, $amount, $description, $date);

            if ($similarity >= $threshold) {
                $duplicates->push([
                    'expense' => $expense,
                    'similarity_score' => $similarity,
                    'reasons' => $this->getSimilarityReasons($expense, $amount, $description, $date),
                ]);
            }
        }

        return $duplicates->sortByDesc('similarity_score');
    }

    protected function checkAmountOutlier(array $expenseData): float
    {
        $groupId = $expenseData['group_id'] ?? null;
        if (!$groupId) return 0;

        $recentExpenses = $this->expenseRepository->getByGroup($groupId, 50);
        if ($recentExpenses->isEmpty()) return 0;

        $amounts = $recentExpenses->pluck('amount')->toArray();
        $mean = array_sum($amounts) / count($amounts);
        $stdDev = $this->calculateStandardDeviation($amounts, $mean);

        $amount = $expenseData['amount'];
        $zScore = abs($amount - $mean) / ($stdDev ?: 1);

        // Reduce score for outliers (z-score > 2)
        if ($zScore > 3) return 0.5; // Major outlier
        if ($zScore > 2) return 0.3; // Moderate outlier
        if ($zScore > 1.5) return 0.1; // Minor outlier

        return 0;
    }

    protected function checkDuplicatePotential(array $expenseData): float
    {
        $groupId = $expenseData['group_id'] ?? null;
        if (!$groupId) return 0;

        $duplicates = $this->findDuplicates(
            $groupId,
            $expenseData['amount'],
            $expenseData['description'] ?? $expenseData['title'],
            $expenseData['expense_date'] ?? now(),
            0.7
        );

        if ($duplicates->isNotEmpty()) {
            return 0.4; // Potential duplicate
        }

        return 0;
    }

    protected function checkDescriptionQuality(array $expenseData): float
    {
        $description = $expenseData['description'] ?? $expenseData['title'] ?? '';

        if (empty($description)) return 0.3;

        $wordCount = str_word_count($description);

        if ($wordCount < 2) return 0.2; // Too short
        if ($wordCount > 50) return 0.1; // Too long, might be spam

        return 0;
    }

    protected function checkDateAnomaly(array $expenseData): float
    {
        $date = $expenseData['expense_date'] ?? now();

        if ($date instanceof Carbon) {
            $date = $date->toDateTime();
        } else {
            $date = Carbon::parse($date);
        }

        $now = now();

        // Future date
        if ($date->isFuture()) return 0.5;

        // Too far in the past (more than 1 year)
        if ($date->diffInYears($now) > 1) return 0.2;

        // Weekend vs weekday patterns could be checked here
        // For now, just check if it's a reasonable date

        return 0;
    }

    protected function calculateSimilarity(ExpenseCore $expense, $amount, $description, $date): float
    {
        $score = 0;
        $totalFactors = 4;

        // Amount similarity (exact match gets high score)
        if (abs($expense->amount - $amount) < 0.01) {
            $score += 1;
        } elseif (abs($expense->amount - $amount) / $amount < 0.1) {
            $score += 0.5; // Within 10%
        }

        // Description similarity
        $descSimilarity = $this->calculateTextSimilarity(
            $expense->description ?? $expense->title,
            $description
        );
        $score += $descSimilarity;

        // Date proximity (same day gets high score)
        $expenseDate = Carbon::parse($expense->expense_date);
        $checkDate = Carbon::parse($date);

        $daysDiff = abs($expenseDate->diffInDays($checkDate));
        if ($daysDiff === 0) {
            $score += 1;
        } elseif ($daysDiff <= 7) {
            $score += max(0, 1 - ($daysDiff / 7));
        }

        // Same payer
        if (isset($expense->payer_id) && $expense->payer_id === ($expense['payer_id'] ?? null)) {
            $score += 0.5;
        }

        return $score / $totalFactors;
    }

    protected function calculateTextSimilarity($text1, $text2): float
    {
        if (empty($text1) || empty($text2)) return 0;

        $text1 = strtolower($text1);
        $text2 = strtolower($text2);

        // Exact match
        if ($text1 === $text2) return 1.0;

        // Contains check
        if (strpos($text1, $text2) !== false || strpos($text2, $text1) !== false) {
            return 0.8;
        }

        // Word overlap
        $words1 = explode(' ', $text1);
        $words2 = explode(' ', $text2);
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($intersection) / count($union);
    }

    protected function getSimilarityReasons(ExpenseCore $expense, $amount, $description, $date): array
    {
        $reasons = [];

        if (abs($expense->amount - $amount) < 0.01) {
            $reasons[] = 'Same amount';
        }

        if ($this->calculateTextSimilarity($expense->description ?? $expense->title, $description) > 0.5) {
            $reasons[] = 'Similar description';
        }

        $expenseDate = Carbon::parse($expense->expense_date);
        $checkDate = Carbon::parse($date);
        if ($expenseDate->isSameDay($checkDate)) {
            $reasons[] = 'Same date';
        }

        return $reasons;
    }

    protected function calculateStandardDeviation(array $values, float $mean): float
    {
        if (count($values) <= 1) return 0;

        $variance = array_sum(array_map(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values)) / (count($values) - 1);

        return sqrt($variance);
    }
}
