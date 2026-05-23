<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFinancialSyncJob;
use App\Models\OnboardingState;
use App\Models\SyncLog;
use App\Services\FinancialSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function __construct(
        private readonly FinancialSyncService $financialSyncService
    ) {
    }

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => 'nullable|array',
            'messages.*.sms_body' => 'required_with:messages|string|max:5000',
            'messages.*.sender' => 'nullable|string|max:100',
            'messages.*.received_at' => 'nullable|date',
            'messages.*.source_app' => 'nullable|string|max:100',
            'messages.*.external_id' => 'nullable|string|max:191',
            'messages.*.amount' => 'nullable|numeric',
            'messages.*.merchant' => 'nullable|string|max:255',
            'messages.*.reference' => 'nullable|string|max:255',
            'messages.*.payment_source' => 'nullable|string|max:50',
            'messages.*.currency' => 'nullable|string|max:10',
        ]);

        $messages = $data['messages'] ?? [];
        $syncHash = $this->financialSyncService->syncHash($messages);

        $result = DB::transaction(function () use ($request, $messages, $syncHash) {
            $state = OnboardingState::lockForUpdate()->firstOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'current_step' => 'START',
                    'sync_status' => OnboardingState::SYNC_PENDING,
                    'is_completed' => false,
                ]
            );

            if ($state->sync_status === OnboardingState::SYNC_IN_PROGRESS) {
                return ['state' => $state, 'log' => $state->syncLogs()->latest()->first(), 'dispatched' => false];
            }

            if ($state->sync_status === OnboardingState::SYNC_DONE && $state->last_sync_hash === $syncHash) {
                return ['state' => $state, 'log' => $state->syncLogs()->latest()->first(), 'dispatched' => false];
            }

            $log = SyncLog::create([
                'user_id' => $request->user()->id,
                'onboarding_state_id' => $state->id,
                'status' => 'queued',
                'sync_hash' => $syncHash,
                'message_count' => count($messages),
                'meta' => [
                    'source' => 'onboarding',
                ],
            ]);

            $state->forceFill([
                'current_step' => 'SYNC_PROGRESS',
                'sync_status' => OnboardingState::SYNC_IN_PROGRESS,
                'sync_started_at' => now(),
                'sync_consent_granted' => true,
            ])->save();

            return ['state' => $state->refresh(), 'log' => $log, 'dispatched' => true];
        });

        if ($result['dispatched']) {
            ProcessFinancialSyncJob::dispatch($result['state']->id, $result['log']->id, $messages);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'current_step' => $result['state']->current_step,
                'sync_status' => $result['state']->sync_status,
                'is_completed' => $result['state']->is_completed,
                'latest_sync_log' => $result['log'],
            ],
        ], $result['dispatched'] ? 202 : 200);
    }

    public function status(Request $request): JsonResponse
    {
        $state = OnboardingState::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'current_step' => 'START',
                'sync_status' => OnboardingState::SYNC_PENDING,
                'is_completed' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'current_step' => $state->current_step,
                'sync_status' => $state->sync_status,
                'is_completed' => $state->is_completed,
                'sync_started_at' => optional($state->sync_started_at)?->toISOString(),
                'sync_completed_at' => optional($state->sync_completed_at)?->toISOString(),
                'latest_sync_log' => $state->syncLogs()->latest()->first(),
            ],
        ]);
    }
}
