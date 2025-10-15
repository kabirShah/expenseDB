<?php

namespace App\Services;

use App\Repositories\CategoryRepository;
use Illuminate\Support\Collection;

class AITaggingService
{
    protected $categoryRepository;

    // Predefined category mappings
    protected $categoryMappings = [
        'food' => ['pizza', 'burger', 'restaurant', 'cafe', 'coffee', 'lunch', 'dinner', 'meal', 'eat', 'food'],
        'travel' => ['uber', 'taxi', 'bus', 'train', 'flight', 'hotel', 'travel', 'trip', 'cab'],
        'entertainment' => ['movie', 'cinema', 'game', 'party', 'event', 'concert', 'show'],
        'shopping' => ['clothes', 'shopping', 'store', 'mall', 'market', 'buy'],
        'utilities' => ['electricity', 'water', 'gas', 'internet', 'phone', 'bill', 'utility'],
        'healthcare' => ['doctor', 'hospital', 'medicine', 'pharmacy', 'medical', 'health'],
        'education' => ['book', 'course', 'school', 'college', 'education', 'study'],
        'transport' => ['fuel', 'petrol', 'diesel', 'parking', 'toll'],
        'home' => ['rent', 'mortgage', 'maintenance', 'repair', 'home', 'house'],
        'personal' => ['gift', 'donation', 'charity', 'personal'],
    ];

    public function __construct(CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function tagExpense(string $description): array
    {
        $tags = [];
        $confidence = 0.0;

        $normalizedDesc = $this->normalizeText($description);

        foreach ($this->categoryMappings as $category => $keywords) {
            $matchScore = $this->calculateMatchScore($normalizedDesc, $keywords);

            if ($matchScore > 0.3) { // Threshold for tagging
                $tags[] = $category;
                $confidence = max($confidence, $matchScore);
            }
        }

        // If no tags found, assign 'other'
        if (empty($tags)) {
            $tags[] = 'other';
            $confidence = 0.1;
        }

        // Update category usage if categories exist
        foreach ($tags as $tag) {
            $category = $this->categoryRepository->findOrCreateByName($tag, true);
            $this->categoryRepository->incrementUsage($category->id);
        }

        return [
            'tags' => $tags,
            'confidence' => round($confidence, 2),
            'primary_category' => $tags[0] ?? 'other',
        ];
    }

    public function suggestCategories(string $description, int $limit = 5): Collection
    {
        $normalizedDesc = $this->normalizeText($description);
        $suggestions = collect();

        foreach ($this->categoryMappings as $category => $keywords) {
            $score = $this->calculateMatchScore($normalizedDesc, $keywords);

            if ($score > 0) {
                $suggestions->push([
                    'category' => $category,
                    'score' => $score,
                    'keywords_matched' => $this->getMatchedKeywords($normalizedDesc, $keywords),
                ]);
            }
        }

        return $suggestions->sortByDesc('score')->take($limit);
    }

    public function learnFromManualTagging(string $description, string $category): void
    {
        // Add new keywords to existing categories or create new categories
        $normalizedDesc = $this->normalizeText($description);
        $words = explode(' ', $normalizedDesc);

        // Filter out common words
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $keywords = array_diff($words, $commonWords);

        // Add to category mappings if not already present
        if (!isset($this->categoryMappings[$category])) {
            $this->categoryMappings[$category] = [];
        }

        foreach ($keywords as $keyword) {
            if (!in_array($keyword, $this->categoryMappings[$category]) && strlen($keyword) > 2) {
                $this->categoryMappings[$category][] = $keyword;
            }
        }
    }

    protected function normalizeText(string $text): string
    {
        // Convert to lowercase and remove special characters
        $normalized = strtolower($text);
        $normalized = preg_replace('/[^\w\s]/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    protected function calculateMatchScore(string $description, array $keywords): float
    {
        $descriptionWords = explode(' ', $description);
        $matches = 0;
        $totalKeywords = count($keywords);

        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                $matches++;
            }
        }

        // Calculate score based on matches and keyword density
        $matchRatio = $totalKeywords > 0 ? $matches / $totalKeywords : 0;
        $densityBonus = count($descriptionWords) > 0 ? min($matches / count($descriptionWords), 0.5) : 0;

        return min($matchRatio + $densityBonus, 1.0);
    }

    protected function getMatchedKeywords(string $description, array $keywords): array
    {
        $matched = [];

        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                $matched[] = $keyword;
            }
        }

        return $matched;
    }
}
