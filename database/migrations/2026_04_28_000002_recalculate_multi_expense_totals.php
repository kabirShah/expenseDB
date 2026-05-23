<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('multi_expenses')) {
            return;
        }

        DB::table('multi_expenses')
            ->select(['id', 'user_id', 'wallet_id', 'description', 'total_amount'])
            ->orderBy('id')
            ->chunkById(100, function ($multiExpenses) {
                foreach ($multiExpenses as $multiExpense) {
                    $correctTotal = $this->calculateTotal((string) $multiExpense->description);
                    $storedTotal = round((float) $multiExpense->total_amount, 2);

                    if ($correctTotal <= 0 || abs($correctTotal - $storedTotal) < 0.01) {
                        continue;
                    }

                    DB::table('multi_expenses')
                        ->where('id', $multiExpense->id)
                        ->update([
                            'total_amount' => $correctTotal,
                            'updated_at' => now(),
                        ]);

                    if (!$multiExpense->wallet_id || !Schema::hasTable('wallets')) {
                        continue;
                    }

                    $wallet = DB::table('wallets')->where('id', $multiExpense->wallet_id)->first();
                    if (!$wallet) {
                        continue;
                    }

                    $delta = round($correctTotal - $storedTotal, 2);
                    $previousBalance = round((float) $wallet->balance, 2);
                    $newBalance = round($previousBalance - $delta, 2);

                    DB::table('wallets')
                        ->where('id', $wallet->id)
                        ->update([
                            'balance' => $newBalance,
                            'updated_at' => now(),
                        ]);

                    if (Schema::hasTable('balance_histories')) {
                        DB::table('balance_histories')->insert([
                            'user_id' => $multiExpense->user_id,
                            'wallet_id' => $wallet->id,
                            'transaction_id' => null,
                            'previous_balance' => $previousBalance,
                            'new_balance' => $newBalance,
                            'change_amount' => abs($delta),
                            'change_type' => $delta >= 0 ? 'debit' : 'credit',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data correction only. The original descriptions remain available if manual reversal is needed.
    }

    private function calculateTotal(string $description): float
    {
        $total = 0.0;

        if (preg_match_all('/(?:₹|rs\.?|inr)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i', $description, $matches)) {
            foreach ($matches[1] as $amount) {
                $total += (float) str_replace(',', '', $amount);
            }

            return round($total, 2);
        }

        if (preg_match_all('/\b([0-9,]+(?:\.[0-9]{1,2})?)\b/', $description, $matches)) {
            foreach ($matches[1] as $amount) {
                $total += (float) str_replace(',', '', $amount);
            }
        }

        return round($total, 2);
    }
};
