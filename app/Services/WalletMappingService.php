<?php

namespace App\Services;

use App\Models\Wallet;

class WalletMappingService
{
    public function resolve(int $userId, ?string $sourceKey, ?string $sourceLabel = null): Wallet
    {
        $source = config("transaction_detection.sources.{$sourceKey}", []);
        $walletName = $source['wallet_name'] ?? ($sourceLabel ? "{$sourceLabel} Wallet" : 'Auto Wallet');
        $walletType = $source['wallet_type'] ?? 'other';

        return Wallet::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'name' => $walletName,
            ],
            [
                'type' => $walletType,
                'currency' => 'INR',
                'balance' => 0,
                'icon' => $walletType === 'upi' ? 'phone-portrait-outline' : 'wallet-outline',
                'color' => $walletType === 'upi' ? '#2563EB' : '#059669',
                'is_default' => false,
            ]
        );
    }
}
