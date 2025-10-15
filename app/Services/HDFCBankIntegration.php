<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HDFCBankIntegration implements BankIntegration
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $callbackUrl;

    public function __construct()
    {
        $this->apiKey = config('services.hdfc.api_key');
        $this->apiSecret = config('services.hdfc.api_secret');
        $this->baseUrl = config('services.hdfc.base_url', 'https://api.hdfcbank.com');
        $this->callbackUrl = config('app.url') . '/api/payments/callback/hdfc';
    }

    public function initiatePayment(array $data): array
    {
        $transactionId = 'HDFC_' . Str::random(10);

        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'payment_url' => $this->baseUrl . '/pay/' . $transactionId,
            'status' => 'pending'
        ];
    }

    public function verifyPayment(string $transactionId): array
    {
        return [
            'success' => true,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'amount' => 100.00,
            'currency' => 'INR'
        ];
    }

    public function handleCallback(array $callbackData): bool
    {
        $transactionId = $callbackData['transaction_id'] ?? null;
        $status = $callbackData['status'] ?? null;

        if (!$transactionId || !$status) {
            return false;
        }

        return true;
    }

    public function getSupportedMethods(): array
    {
        return ['upi', 'net_banking', 'debit_card', 'credit_card'];
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }
}
