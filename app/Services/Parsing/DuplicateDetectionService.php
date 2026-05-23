<?php

namespace App\Services\Parsing;

use App\Models\Expense;
use Carbon\Carbon;

class DuplicateDetectionService
{
    public function findDuplicate(int $userId, array $normalized): ?Expense
    {
        if (!empty($normalized['raw_hash'])) {
            $hashMatch = Expense::query()
                ->where('user_id', $userId)
                ->where('raw_hash', $normalized['raw_hash'])
                ->first();

            if ($hashMatch) {
                return $hashMatch;
            }
        }

        if (empty($normalized['amount']) || empty($normalized['expense_date'])) {
            return null;
        }

        $expenseDate = Carbon::parse($normalized['expense_date']);

        return Expense::query()
            ->where('user_id', $userId)
            ->where('amount', round((float) $normalized['amount'], 2))
            ->when(
                !empty($normalized['merchant_name']),
                fn ($query) => $query->where('merchant_name', $normalized['merchant_name'])
            )
            ->whereBetween('expense_date', [
                $expenseDate->copy()->subMinutes(15),
                $expenseDate->copy()->addMinutes(15),
            ])
            ->first();
    }
}
