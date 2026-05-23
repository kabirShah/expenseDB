<?php

namespace App\Services\Parsing;

use App\Models\Category;
use App\Models\MerchantRule;
use Illuminate\Support\Str;

class CategoryClassifierService
{
    private const CATEGORY_KEYWORDS = [
        'Food' => ['swiggy', 'zomato', 'restaurant', 'cafe', 'food', 'dining'],
        'Travel' => ['uber', 'ola', 'metro', 'fuel', 'petrol', 'diesel', 'taxi', 'bus'],
        'Shopping' => ['amazon', 'flipkart', 'myntra', 'shopping', 'store'],
        'Bills' => ['electricity', 'water', 'broadband', 'bill', 'recharge', 'gas'],
        'Entertainment' => ['movie', 'netflix', 'spotify', 'bookmyshow', 'prime'],
        'Health' => ['pharmacy', 'hospital', 'clinic', 'medicine'],
    ];

    public function classify(
        int $userId,
        ?string $merchantName,
        ?string $description,
        ?string $paymentMethod = null
    ): array {
        $haystack = strtolower(trim(($merchantName ?? '') . ' ' . ($description ?? '')));

        $rule = MerchantRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderBy('priority')
            ->get()
            ->first(function (MerchantRule $rule) use ($haystack) {
                return @preg_match('/' . $rule->pattern . '/i', $haystack) === 1
                    || str_contains($haystack, strtolower($rule->pattern));
            });

        if ($rule) {
            return [
                'category_id' => $rule->category_id,
                'category_name' => $rule->category_name ?? $rule->category?->name,
                'merchant_name' => $rule->merchant_name ?: $merchantName,
                'payment_method' => $rule->payment_method ?: $paymentMethod,
                'matched_by' => 'rule',
            ];
        }

        foreach (self::CATEGORY_KEYWORDS as $categoryName => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $category = $this->resolveCategory($userId, $categoryName);

                    return [
                        'category_id' => $category?->id,
                        'category_name' => $category?->name ?? $categoryName,
                        'merchant_name' => $merchantName,
                        'payment_method' => $paymentMethod,
                        'matched_by' => 'keyword',
                    ];
                }
            }
        }

        $fallback = $this->resolveCategory($userId, 'Other');

        return [
            'category_id' => $fallback?->id,
            'category_name' => $fallback?->name ?? 'Other',
            'merchant_name' => $merchantName,
            'payment_method' => $paymentMethod,
            'matched_by' => 'fallback',
        ];
    }

    private function resolveCategory(int $userId, string $categoryName): ?Category
    {
        $slug = Str::slug($categoryName);

        return Category::query()
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->where(function ($query) use ($slug, $categoryName) {
                $query->where('slug', $slug)->orWhere('name', $categoryName);
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }
}
