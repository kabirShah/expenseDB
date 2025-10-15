<?php

namespace App\Services;

use App\Models\PaymentProvider;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private $integrations = [];

    public function __construct()
    {
        $this->integrations = [
            'kotak' => new KotakBankIntegration(),
            'icici' => new ICICIBankIntegration(),
            'hdfc' => new HDFCBankIntegration(),
            'razorpay' => new RazorpayGatewayIntegration(),
        ];
    }

    public function initiatePayment(string $providerName, array $data): array
    {
        if (!isset($this->integrations[$providerName])) {
            return ['success' => false, 'message' => 'Provider not found'];
        }

        $integration = $this->integrations[$providerName];

        if (!$integration->isConfigured()) {
            return ['success' => false, 'message' => 'Provider not configured'];
        }

        try {
            $result = $integration->initiatePayment($data);

            // Create transaction record
            Transaction::create([
                'transaction_id' => $result['transaction_id'] ?? $result['order_id'],
                'user_id' => $data['user_id'],
                'payment_provider_id' => $this->getProviderId($providerName),
                'type' => 'debit',
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'INR',
                'status' => 'pending',
                'description' => $data['description'] ?? 'Payment',
                'metadata' => $data['metadata'] ?? []
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Payment initiation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment initiation failed'];
        }
    }

    public function verifyPayment(string $providerName, string $transactionId): array
    {
        if (!isset($this->integrations[$providerName])) {
            return ['success' => false, 'message' => 'Provider not found'];
        }

        $integration = $this->integrations[$providerName];

        try {
            $result = $integration->verifyPayment($transactionId);

            // Update transaction status
            Transaction::where('transaction_id', $transactionId)
                ->update(['status' => $result['status']]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment verification failed'];
        }
    }

    public function handleCallback(string $providerName, array $callbackData): bool
    {
        if (!isset($this->integrations[$providerName])) {
            return false;
        }

        $integration = $this->integrations[$providerName];

        try {
            return $integration->handleCallback($callbackData);
        } catch (\Exception $e) {
            Log::error('Callback handling failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getSupportedMethods(string $providerName): array
    {
        if (!isset($this->integrations[$providerName])) {
            return [];
        }

        return $this->integrations[$providerName]->getSupportedMethods();
    }

    private function getProviderId(string $providerName): ?int
    {
        $provider = PaymentProvider::where('name', $providerName)->first();
        return $provider ? $provider->id : null;
    }
}
