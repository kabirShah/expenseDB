<?php

namespace App\Services;

use App\Models\ExpenseCore;
use App\Repositories\ExpenseRepository;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AutoSplitterService
{
    protected $expenseRepository;

    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    public function getSuggestions($groupId, $description): array
    {
        $patterns = $this->analyzeHistoricalPatterns($groupId);

        $suggestions = [];

        foreach ($patterns as $pattern) {
            if ($this->matchesDescription($description, $pattern['keywords'])) {
                $suggestions[] = [
                    'split_type' => $pattern['split_type'],
                    'participants' => $pattern['participants'],
                    'confidence' => $pattern['frequency'] / $patterns->max('frequency'),
                    'reason' => "Based on {$pattern['frequency']} similar expenses",
                ];
            }
        }

        return array_slice($suggestions, 0, 3); // Return top 3 suggestions
    }

    public function learnFromExpense(ExpenseCore $expense): void
    {
        // Learning logic would be implemented here
        // This could involve storing patterns in a separate table
        // or using machine learning algorithms
    }

    protected function analyzeHistoricalPatterns($groupId): Collection
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        $expenses = $this->expenseRepository->getByGroup($groupId, 1000)
            ->where('expense_date', '>=', $sixMonthsAgo);

        $patterns = collect();

        $expenses->groupBy(function ($expense) {
            return $this->normalizeDescription($expense->description ?? $expense->title);
        })->each(function ($group) use ($patterns) {
            if ($group->count() >= 3) { // At least 3 similar expenses
                $mostCommonSplit = $group->groupBy('split_type')->sortByDesc->count()->keys()->first();

                $patterns->push([
                    'keywords' => $this->extractKeywords($group->first()->description ?? $group->first()->title),
                    'split_type' => $mostCommonSplit,
                    'participants' => $this->getCommonParticipants($group),
                    'frequency' => $group->count(),
                    'average_amount' => $group->avg('amount'),
                ]);
            }
        });

        return $patterns->sortByDesc('frequency');
    }

    protected function matchesDescription($description, $keywords): bool
    {
        $normalizedDesc = strtolower($description);

        foreach ($keywords as $keyword) {
            if (strpos($normalizedDesc, strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeDescription($description): string
    {
        // Remove numbers, special characters, and common words
        $normalized = preg_replace('/\d+/', '', $description);
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = strtolower($normalized);

        // Remove common words
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_diff(explode(' ', $normalized), $commonWords);

        return implode(' ', array_slice($words, 0, 5)); // Take first 5 significant words
    }

    protected function extractKeywords($description): array
    {
        $words = explode(' ', $this->normalizeDescription($description));
        return array_filter($words, function ($word) {
            return strlen($word) > 2; // Keywords should be longer than 2 characters
        });
    }

    protected function getCommonParticipants(Collection $expenses): array
    {
        $participantCounts = [];

        foreach ($expenses as $expense) {
            foreach ($expense->participants ?? [] as $participant) {
                $userId = $participant['user_id'];
                if (!isset($participantCounts[$userId])) {
                    $participantCounts[$userId] = 0;
                }
                $participantCounts[$userId]++;
            }
        }

        // Return participants that appear in more than 50% of expenses
        $threshold = $expenses->count() * 0.5;
        $commonParticipants = [];

        foreach ($participantCounts as $userId => $count) {
            if ($count > $threshold) {
                $commonParticipants[] = ['user_id' => $userId];
            }
        }

        return $commonParticipants;
    }
}
