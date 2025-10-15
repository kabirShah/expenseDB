<?php

namespace App\Services;

interface BankIntegration
{
    /**
     * Initiate a payment
     *
     * @param array $data Payment data (amount, user_id, etc.)
     * @return array Response with payment URL or details
     */
    public function initiatePayment(array $data): array;

    /**
     * Verify payment status
     *
     * @param string $transactionId
     * @return array Payment verification result
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Handle payment callback/webhook
     *
     * @param array $callbackData
     * @return bool Success status
     */
    public function handleCallback(array $callbackData): bool;

    /**
     * Get supported payment methods
     *
     * @return array
     */
    public function getSupportedMethods(): array;

    /**
     * Check if integration is configured
     *
     * @return bool
     */
    public function isConfigured(): bool;
}
