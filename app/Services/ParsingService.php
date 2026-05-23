<?php

namespace App\Services;

use Carbon\Carbon;

class ParsingService
{
    public function parse(string $rawText, ?string $packageName = null, mixed $receivedAt = null): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $rawText));
        $haystack = strtolower(trim($text . ' ' . ($packageName ?? '')));

        $amount = $this->extractAmount($text);
        $source = $this->extractSource($haystack);

        return [
            'amount' => $amount,
            'type' => $this->extractType($haystack),
            'merchant_name' => $this->extractMerchant($text),
            'source' => $source['label'],
            'source_key' => $source['key'],
            'reference_id' => $this->extractReferenceId($text),
            'transaction_date' => $this->extractDate($text, $receivedAt)->toDateTimeString(),
            'is_financial' => $amount > 0 && $this->looksFinancial($haystack),
        ];
    }

    private function extractAmount(string $text): float
    {
        foreach (config('transaction_detection.amount_patterns', []) as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return round((float) str_replace(',', '', $match[1]), 2);
            }
        }

        return 0.0;
    }

    private function extractType(string $text): string
    {
        if (preg_match('/\b(credited|received|refund|cashback|deposited|credit)\b/i', $text)) {
            return 'credit';
        }

        return 'debit';
    }

    private function extractMerchant(string $text): ?string
    {
        foreach (config('transaction_detection.merchant_patterns', []) as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $merchant = preg_replace('/\b(on|via|using|ref|txn|upi|a\/c).*/i', '', $match[1]);
                $merchant = trim((string) preg_replace('/\s+/', ' ', $merchant), " .,-");

                return $merchant !== '' ? mb_substr($merchant, 0, 120) : null;
            }
        }

        return null;
    }

    private function extractReferenceId(string $text): ?string
    {
        foreach (config('transaction_detection.reference_patterns', []) as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return strtoupper($match[1]);
            }
        }

        return null;
    }

    private function extractSource(string $haystack): array
    {
        foreach (config('transaction_detection.sources', []) as $key => $source) {
            foreach ($source['keywords'] as $keyword) {
                if (str_contains($haystack, strtolower($keyword))) {
                    return ['key' => $key, 'label' => $source['label']];
                }
            }
        }

        return ['key' => 'unknown', 'label' => 'Unknown'];
    }

    private function extractDate(string $text, mixed $fallback): Carbon
    {
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?:\s*([AP]M))?)?/i', $text, $match)) {
            $year = (int) $match[3];
            $hour = isset($match[4]) ? (int) $match[4] : 0;
            $minute = isset($match[5]) ? (int) $match[5] : 0;
            $suffix = strtoupper($match[6] ?? '');

            if ($year < 100) {
                $year += 2000;
            }
            if ($suffix === 'PM' && $hour < 12) {
                $hour += 12;
            }
            if ($suffix === 'AM' && $hour === 12) {
                $hour = 0;
            }

            return Carbon::create($year, (int) $match[2], (int) $match[1], $hour, $minute);
        }

        return Carbon::parse($fallback ?: now());
    }

    private function looksFinancial(string $text): bool
    {
        return preg_match('/\b(debited|credited|paid|received|upi|imps|neft|rtgs|txn|transaction|rs|inr)\b|₹/i', $text) === 1;
    }
}
