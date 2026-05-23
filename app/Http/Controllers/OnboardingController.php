<?php

namespace App\Http\Controllers;

use App\Models\OnboardingState;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $state = $this->resolveState($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $this->payload($state),
        ]);
    }

    public function saveStep(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_step' => 'required|string|max:50',
            'step_payload' => 'nullable|array',
            'permissions_granted' => 'nullable|boolean',
            'sync_consent_granted' => 'nullable|boolean',
        ]);

        $state = DB::transaction(function () use ($request, $data) {
            $state = $this->resolveState($request->user()->id);
            $existingPayload = $state->step_payload ?? [];

            $state->fill([
                'current_step' => $data['current_step'],
                'permissions_granted' => $data['permissions_granted'] ?? $state->permissions_granted,
                'sync_consent_granted' => $data['sync_consent_granted'] ?? $state->sync_consent_granted,
                'step_payload' => array_replace_recursive($existingPayload, $data['step_payload'] ?? []),
            ]);
            $state->save();

            return $state->refresh();
        });

        return response()->json([
            'success' => true,
            'data' => $this->payload($state),
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $state = $this->resolveState($request->user()->id);

        if ($state->sync_consent_granted && $state->sync_status !== OnboardingState::SYNC_DONE) {
            return response()->json([
                'success' => false,
                'message' => 'Sync must complete before onboarding can finish.',
            ], 422);
        }

        $state->forceFill([
            'is_completed' => true,
            'current_step' => 'COMPLETE',
        ])->save();

        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $this->payload($state->refresh()),
        ]);
    }

    private function resolveState(int $userId): OnboardingState
    {
        return OnboardingState::firstOrCreate(
            ['user_id' => $userId],
            [
                'current_step' => 'START',
                'sync_status' => OnboardingState::SYNC_PENDING,
                'is_completed' => false,
            ]
        );
    }

    private function payload(OnboardingState $state): array
    {
        return [
            'user_id' => $state->user_id,
            'current_step' => $state->current_step,
            'sync_status' => $state->sync_status,
            'is_completed' => $state->is_completed,
            'permissions_granted' => $state->permissions_granted,
            'sync_consent_granted' => $state->sync_consent_granted,
            'step_payload' => $state->step_payload ?? [],
            'sync_started_at' => optional($state->sync_started_at)?->toISOString(),
            'sync_completed_at' => optional($state->sync_completed_at)?->toISOString(),
            'latest_sync_log' => $state->syncLogs()->latest()->first(),
        ];
    }
}
