<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;

class DuplicateCheckService
{
    public function findDuplicate(int $userId, array $transactionData): ?Transaction
    {
        if (empty($transactionData['amount']) || empty($transactionData['transaction_date']) || empty($transactionData['reference_id'])) {
            return null;
        }

        $date = Carbon::parse($transactionData['transaction_date'])->toDateString();

        return Transaction::query()
            ->where('user_id', $userId)
            ->where('amount', round((float) $transactionData['amount'], 2))
            ->whereDate('transaction_date', $date)
            ->where('reference_id', $transactionData['reference_id'])
            ->first();
    }
}
