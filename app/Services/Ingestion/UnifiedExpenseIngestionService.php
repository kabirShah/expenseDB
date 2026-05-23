<?php

namespace App\Services\Ingestion;

use App\Models\Expense;
use App\Services\Parsing\CategoryClassifierService;
use App\Services\Parsing\DuplicateDetectionService;
use App\Services\Parsing\ExpenseNormalizerService;

class UnifiedExpenseIngestionService
{
    public function __construct(
        private readonly ExpenseNormalizerService $normalizer,
        private readonly DuplicateDetectionService $duplicateDetection,
        private readonly CategoryClassifierService $categoryClassifier
    ) {
    }

    public function ingest(int $userId, string $sourceType, array $payload): Expense
    {
        $normalized = $this->normalizer->normalize($userId, $sourceType, $payload);

        if (empty($normalized['category_name'])) {
            $classification = $this->categoryClassifier->classify(
                $userId,
                $normalized['merchant_name'] ?? null,
                $normalized['description'] ?? null,
                $normalized['payment_method'] ?? null
            );

            $normalized['category_id'] = $normalized['category_id'] ?? $classification['category_id'];
            $normalized['category_name'] = $classification['category_name'];
            $normalized['merchant_name'] = $classification['merchant_name'] ?? $normalized['merchant_name'];
            $normalized['payment_method'] = $classification['payment_method'] ?? $normalized['payment_method'];
            $normalized['metadata']['classification'] = $classification['matched_by'] ?? 'unknown';
        }

        $duplicate = $this->duplicateDetection->findDuplicate($userId, $normalized);

        if ($duplicate) {
            $normalized['duplicate_of'] = $duplicate->id;
            $normalized['is_duplicate'] = true;
        }

        return Expense::create($normalized);
    }
}
