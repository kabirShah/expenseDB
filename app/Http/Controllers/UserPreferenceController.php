<?php

namespace App\Http\Controllers;

use App\Models\Balance;
use App\Models\UserPreference;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPreferenceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'budget_mode' => 'nullable|string',
            'monthly_budget' => 'nullable|numeric',
            'category_budget' => 'nullable|array',
            'warning_threshold' => 'nullable|integer',
            'saving_goal' => 'nullable|string',
            'saving_target' => 'nullable|numeric',
            'tips_enabled' => 'boolean',
            'tips_types' => 'nullable|array',
            'notification_frequency' => 'nullable|string',
            'notify_time' => 'nullable',
            'storage_preference' => 'nullable|in:cloud_sync,device_only,hybrid',
            'theme_mode' => 'nullable|in:light,dark,system',
            'use_system_theme' => 'nullable|boolean',
            'favorite_categories' => 'nullable|array',
            'favorite_categories.*' => 'string|max:50',
            'setup_wallet_name' => 'nullable|string|max:100',
            'setup_wallet_type' => 'nullable|in:cash,bank,upi,credit_card,debit_card,other',
            'setup_wallet_balance' => 'nullable|numeric|min:0',
            'setup_budget_name' => 'nullable|string|max:100',
            'setup_budget_amount' => 'nullable|numeric|min:0',
            'setup_budget_period' => 'nullable|in:weekly,monthly,yearly,custom',
            'onboarding_completed' => 'boolean'
        ]);

        if (($data['onboarding_completed'] ?? false) === true && !isset($data['onboarding_completed_at'])) {
            $data['onboarding_completed_at'] = now();
        }

        if (isset($data['theme_mode'])) {
            $data['use_system_theme'] = $data['theme_mode'] === 'system';
        } elseif (array_key_exists('use_system_theme', $data) && $data['use_system_theme'] === true) {
            $data['theme_mode'] = 'system';
        }

        $prefs = DB::transaction(function () use ($user, $data) {
            $data = $this->filterPreferenceColumns($data);

            $prefs = UserPreference::updateOrCreate(
                ['user_id' => $user->id],
                $data
            );

            $this->syncOnboardingWallet($user->id, $user->currency ?? 'INR', $data);

            return $prefs;
        });

        return response()->json([
            'success' => true,
            'data' => $prefs
        ]);
    }

    public function show(): JsonResponse
    {
        $prefs = auth()->user()->preferences;

        return response()->json([
            'success' => true,
            'data' => $prefs,
        ]);
    }

    private function syncOnboardingWallet(int $userId, string $currency, array $data): void
    {
        $hasWalletSetup = isset($data['setup_wallet_name'])
            || isset($data['setup_wallet_type'])
            || array_key_exists('setup_wallet_balance', $data);

        if (!$hasWalletSetup) {
            return;
        }

        $wallet = Wallet::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        $walletName = $data['setup_wallet_name'] ?? $wallet?->name ?? 'Cash';
        $walletType = $data['setup_wallet_type'] ?? $wallet?->type ?? 'cash';
        $walletBalance = array_key_exists('setup_wallet_balance', $data)
            ? (float) $data['setup_wallet_balance']
            : (float) ($wallet?->balance ?? 0);

        if (!$wallet) {
            Wallet::create([
                'user_id' => $userId,
                'name' => $walletName,
                'type' => $walletType,
                'currency' => strtoupper($currency ?: 'INR'),
                'balance' => $walletBalance,
                'is_default' => true,
            ]);

            $this->syncOnboardingBalanceHistory($userId, $walletName, $walletBalance);

            return;
        }

        $shouldSyncBalanceHistory = array_key_exists('setup_wallet_balance', $data)
            && $wallet->transactions()->count() === 0;

        $wallet->update([
            'name' => $walletName,
            'type' => $walletType,
            'currency' => strtoupper($wallet->currency ?: $currency ?: 'INR'),
            'balance' => $wallet->transactions()->count() === 0
                ? $walletBalance
                : $wallet->balance,
            'is_default' => true,
        ]);

        Wallet::where('user_id', $userId)
            ->where('id', '!=', $wallet->id)
            ->update(['is_default' => false]);

        if ($shouldSyncBalanceHistory) {
            $this->syncOnboardingBalanceHistory($userId, $walletName, $walletBalance);
        }
    }

    private function syncOnboardingBalanceHistory(int $userId, string $walletName, float $walletBalance): void
    {
        if (!Schema::hasTable('balances')) {
            return;
        }

        Balance::updateOrCreate(
            [
                'user_id' => $userId,
                'source' => 'Onboarding - ' . $walletName,
            ],
            [
                'amount' => $walletBalance,
                'date_added' => now(),
            ]
        );
    }

    private function filterPreferenceColumns(array $attributes): array
    {
        if (!Schema::hasTable('user_preferences')) {
            return [];
        }

        $columns = Schema::getColumnListing('user_preferences');

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

}
