<?php

namespace App\Services;

use App\Models\OnboardingState;
use App\Models\SmsEntry;
use App\Models\SyncLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FinancialSyncService
{
    public function process(OnboardingState $state, SyncLog $log, array $messages): array
    {
        $processedCount = 0;
        $financialCount = 0;

        foreach ($messages as $message) {
            $normalized = $this->normalizeMessage($message);
            $isFinancial = $this->isFinancialMessage($normalized);

            $entry = SmsEntry::firstOrNew([
                'user_id' => $state->user_id,
                'external_id' => $normalized['external_id'],
            ]);

            if (!$entry->exists) {
                $processedCount++;
            }

            $entry->fill([
                'sender' => $normalized['sender'],
                'sms_body' => $normalized['sms_body'],
                'parsed_data' => [
                    'amount' => $normalized['amount'],
                    'merchant' => $normalized['merchant'],
                    'reference' => $normalized['reference'],
                    'payment_source' => $normalized['payment_source'],
                    'currency' => $normalized['currency'],
                    'received_at' => $normalized['received_at'],
                ],
                'status' => $entry->status ?: 'pending',
                'is_financial' => $isFinancial,
                'received_at' => $normalized['received_at'],
                'source_app' => $normalized['source_app'],
                'external_id' => $normalized['external_id'],
            ]);
            $entry->save();

            if ($isFinancial) {
                $financialCount++;
            }
        }

        $state->forceFill([
            'sync_status' => OnboardingState::SYNC_DONE,
            'sync_completed_at' => now(),
            'last_sync_hash' => $log->sync_hash,
            'current_step' => 'SYNC_PROGRESS',
        ])->save();

        $log->forceFill([
            'status' => 'done',
            'processed_count' => $processedCount,
            'financial_count' => $financialCount,
            'completed_at' => now(),
        ])->save();

        return [
            'processed_count' => $processedCount,
            'financial_count' => $financialCount,
        ];
    }

    public function syncHash(array $messages): string
    {
        $normalized = array_map(fn (array $message) => Arr::only($this->normalizeMessage($message), [
            'external_id',
            'sender',
            'sms_body',
            'received_at',
        ]), $messages);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function markFailed(OnboardingState $state, SyncLog $log, \Throwable $exception): void
    {
        DB::transaction(function () use ($state, $log, $exception): void {
            $state->forceFill([
                'sync_status' => OnboardingState::SYNC_FAILED,
                'current_step' => 'SYNC_INIT',
            ])->save();

            $log->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();
        });
    }

    private function normalizeMessage(array $message): array
    {
        $sender = trim((string) ($message['sender'] ?? ''));
        $body = trim((string) ($message['sms_body'] ?? ''));
        $receivedAt = $message['received_at'] ?? now()->toISOString();

        return [
            'sender' => $sender !== '' ? $sender : null,
            'sms_body' => $body,
            'received_at' => $receivedAt,
            'source_app' => $message['source_app'] ?? 'android_sms',
            'external_id' => $message['external_id']
                ?? hash('sha256', implode('|', [$sender, $body, $receivedAt])),
            'amount' => isset($message['amount']) ? (float) $message['amount'] : null,
            'merchant' => $message['merchant'] ?? null,
            'reference' => $message['reference'] ?? null,
            'payment_source' => $message['payment_source'] ?? 'unknown',
            'currency' => strtoupper((string) ($message['currency'] ?? 'INR')),
        ];
    }

    private function isFinancialMessage(array $message): bool
    {
        if (!empty($message['amount'])) {
            return true;
        }

        $text = strtolower(($message['sender'] ?? '') . ' ' . ($message['sms_body'] ?? ''));

        foreach (['debited', 'credited', 'upi', 'paid', 'inr', 'rs', 'wallet', 'bank'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
