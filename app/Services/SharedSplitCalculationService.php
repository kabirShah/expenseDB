<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SharedSplitCalculationService
{
    public function calculate(float $totalAmount, string $splitType, array $participants, array $items = []): array
    {
        $totalAmount = round($totalAmount, 2);
        $participants = collect($participants)->map(fn (array $participant) => [
            'user_id' => (int) $participant['user_id'],
            'amount' => isset($participant['amount']) ? (float) $participant['amount'] : null,
            'percentage' => isset($participant['percentage']) ? (float) $participant['percentage'] : null,
            'shares' => isset($participant['shares']) ? (float) $participant['shares'] : null,
            'items' => $participant['items'] ?? [],
        ]);

        if ($participants->isEmpty()) {
            throw ValidationException::withMessages(['participants' => ['At least one participant is required.']]);
        }

        $owedMap = match ($splitType) {
            'equal' => $this->equalSplit($totalAmount, $participants),
            'exact', 'custom' => $this->exactSplit($totalAmount, $participants),
            'percentage' => $this->percentageSplit($totalAmount, $participants),
            'shares', 'share' => $this->shareSplit($totalAmount, $participants),
            'item', 'itemized', 'item_based' => $this->itemizedSplit($totalAmount, $participants, $items),
            default => throw ValidationException::withMessages(['split_type' => ['Unsupported split type.']]),
        };

        return $participants
            ->map(fn (array $participant) => [
                'user_id' => $participant['user_id'],
                'amount_owed' => round($owedMap[$participant['user_id']] ?? 0, 2),
                'shares' => $participant['shares'],
                'percentage' => $participant['percentage'],
                'items' => $participant['items'],
            ])
            ->values()
            ->all();
    }

    private function equalSplit(float $totalAmount, Collection $participants): array
    {
        $share = round($totalAmount / max(1, $participants->count()), 2);
        $owedMap = $participants->mapWithKeys(fn ($participant) => [$participant['user_id'] => $share])->all();

        return $this->rebalanceRounding($totalAmount, $owedMap);
    }

    private function exactSplit(float $totalAmount, Collection $participants): array
    {
        $owedMap = $participants
            ->mapWithKeys(fn ($participant) => [$participant['user_id'] => round((float) $participant['amount'], 2)])
            ->all();

        $this->assertMatchesTotal($totalAmount, $owedMap, 'participants');

        return $owedMap;
    }

    private function percentageSplit(float $totalAmount, Collection $participants): array
    {
        $percentageTotal = round($participants->sum('percentage'), 2);
        if (abs($percentageTotal - 100) > 0.01) {
            throw ValidationException::withMessages(['participants' => ['Percentages must total 100.']]);
        }

        $owedMap = $participants
            ->mapWithKeys(fn ($participant) => [
                $participant['user_id'] => round($totalAmount * ((float) $participant['percentage'] / 100), 2),
            ])
            ->all();

        return $this->rebalanceRounding($totalAmount, $owedMap);
    }

    private function shareSplit(float $totalAmount, Collection $participants): array
    {
        $totalShares = (float) $participants->sum('shares');
        if ($totalShares <= 0) {
            throw ValidationException::withMessages(['participants' => ['Shares must be greater than zero.']]);
        }

        $owedMap = $participants
            ->mapWithKeys(fn ($participant) => [
                $participant['user_id'] => round($totalAmount * ((float) $participant['shares'] / $totalShares), 2),
            ])
            ->all();

        return $this->rebalanceRounding($totalAmount, $owedMap);
    }

    private function itemizedSplit(float $totalAmount, Collection $participants, array $items): array
    {
        $owedMap = $participants->mapWithKeys(fn ($participant) => [$participant['user_id'] => 0.0])->all();

        foreach ($items as $item) {
            $amount = round((float) ($item['amount'] ?? 0), 2);
            $userIds = collect($item['user_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            if ($amount <= 0 || $userIds->isEmpty()) {
                continue;
            }

            $share = round($amount / $userIds->count(), 2);
            foreach ($userIds as $userId) {
                if (array_key_exists($userId, $owedMap)) {
                    $owedMap[$userId] = round($owedMap[$userId] + $share, 2);
                }
            }
        }

        $this->assertMatchesTotal($totalAmount, $owedMap, 'items');

        return $this->rebalanceRounding($totalAmount, $owedMap);
    }

    private function rebalanceRounding(float $totalAmount, array $owedMap): array
    {
        $difference = round($totalAmount - array_sum($owedMap), 2);
        if (abs($difference) > 0 && $owedMap !== []) {
            $firstKey = array_key_first($owedMap);
            $owedMap[$firstKey] = round($owedMap[$firstKey] + $difference, 2);
        }

        return $owedMap;
    }

    private function assertMatchesTotal(float $totalAmount, array $owedMap, string $field): void
    {
        if (abs(round(array_sum($owedMap), 2) - $totalAmount) > 0.01) {
            throw ValidationException::withMessages([$field => ['Split total does not match expense amount.']]);
        }
    }
}
