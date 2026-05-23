<?php

namespace App\Services;

use App\Models\Consent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SetuService
{
    public function createConsent(array $payload): array
    {
        $response = $this->request()
            ->post('/consents', $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function fetchData(string $consentId): array
    {
        $response = $this->request()
            ->get("/consents/{$consentId}/data")
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }

    public function handleWebhook(array $payload, ?string $signature = null): array
    {
        $this->validateWebhookSignature($payload, $signature);

        Log::info('Setu AA webhook received', [
            'event' => $payload['event'] ?? null,
            'consent_id' => data_get($payload, 'data.consentId'),
            'consent_handle' => data_get($payload, 'data.consentHandle'),
        ]);

        return $payload;
    }

    public function validateWebhookSignature(array $payload, ?string $signature = null): void
    {
        $secret = (string) config('services.setu.webhook_secret');

        if ($secret === '') {
            return;
        }

        $expected = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $secret);

        if (!$signature || !hash_equals($expected, $signature)) {
            throw new HttpException(401, 'Invalid webhook signature.');
        }
    }

    public function normalizeConsentStatus(array $payload): string
    {
        $status = strtoupper((string) (
            data_get($payload, 'data.status')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'event')
            ?? ''
        ));

        return match (true) {
            Str::contains($status, ['ACTIVE', 'APPROVED', 'READY']) => Consent::STATUS_ACTIVE,
            Str::contains($status, ['REVOKED', 'EXPIRED', 'FAILED', 'REJECTED']) => Consent::STATUS_REVOKED,
            default => Consent::STATUS_PENDING,
        };
    }

    private function request()
    {
        $baseUrl = rtrim((string) config('services.setu.base_url'), '/');
        $apiKey = (string) config('services.setu.api_key');
        $clientId = (string) config('services.setu.client_id');
        $clientSecret = (string) config('services.setu.client_secret');

        $request = Http::baseUrl($baseUrl)
            ->timeout((int) config('services.setu.timeout', 30))
            ->acceptJson()
            ->asJson();

        if ($apiKey !== '') {
            $request = $request->withHeaders(['x-api-key' => $apiKey]);
        }

        if ($clientId !== '' && $clientSecret !== '') {
            $request = $request->withBasicAuth($clientId, $clientSecret);
        }

        return $request;
    }
}
