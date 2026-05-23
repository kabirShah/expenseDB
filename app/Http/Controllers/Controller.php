<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\BalanceHistory;
use App\Models\Category;
use App\Models\Wallet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function resolveCategorySelection(
        int $userId,
        mixed $categoryId = null,
        ?string $categoryName = null,
        ?string $customCategoryName = null
    ): array {
        $category = null;

        if ($categoryId) {
            $category = Category::query()
                ->whereKey($categoryId)
                ->where(function ($query) use ($userId) {
                    $query->whereNull('user_id')->orWhere('user_id', $userId);
                })
                ->first();
        }

        if ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
            ];
        }

        $customCategoryName = $this->normalizeCategoryText($customCategoryName);
        $categoryName = $this->normalizeCategoryText($categoryName);
        $selectedName = $customCategoryName ?: ($categoryName !== 'Other' ? $categoryName : null);

        if (!$selectedName) {
            return ['id' => null, 'name' => null];
        }

        $slug = Str::slug($selectedName);
        $category = Category::query()
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->where(function ($query) use ($slug, $selectedName) {
                $query->where('slug', $slug)->orWhere('name', $selectedName);
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        if (!$category) {
            $category = Category::create([
                'user_id' => $userId,
                'name' => $selectedName,
                'slug' => $slug,
            ]);
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
        ];
    }

    protected function normalizeCategoryText(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function resolveUserWallet(int $userId, ?int $walletId = null): ?Wallet
    {
        if (!Schema::hasTable('wallets')) {
            return null;
        }

        if ($walletId) {
            return Wallet::query()
                ->where('user_id', $userId)
                ->find($walletId);
        }

        return Wallet::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    protected function applyWalletBalanceChange(
        Wallet $wallet,
        float $amount,
        string $changeType,
        ?int $transactionId = null
    ): Wallet {
        $amount = round($amount, 2);
        $normalizedType = strtolower($changeType) === 'debit' ? 'debit' : 'credit';
        $previousBalance = (float) $wallet->balance;

        $wallet->balance = $normalizedType === 'debit'
            ? $previousBalance - $amount
            : $previousBalance + $amount;
        $wallet->save();

        if (Schema::hasTable('balance_histories')) {
            BalanceHistory::create([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $transactionId,
                'previous_balance' => $previousBalance,
                'new_balance' => (float) $wallet->balance,
                'change_amount' => $amount,
                'change_type' => $normalizedType,
            ]);
        }

        return $wallet->refresh();
    }

    protected function recordAddedBalance(int $userId, string $source, float $amount): void
    {
        if (!Schema::hasTable('balances')) {
            return;
        }

        Balance::create([
            'user_id' => $userId,
            'source' => $source,
            'amount' => round($amount, 2),
            'date_added' => now(),
        ]);
    }
}
