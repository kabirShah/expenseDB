<?php

namespace App\Jobs;

use App\Models\OnboardingState;
use App\Models\SyncLog;
use App\Services\FinancialSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFinancialSyncJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $onboardingStateId,
        private readonly int $syncLogId,
        private readonly array $messages
    ) {
    }

    public function handle(FinancialSyncService $financialSyncService): void
    {
        $state = OnboardingState::findOrFail($this->onboardingStateId);
        $log = SyncLog::findOrFail($this->syncLogId);

        $log->forceFill([
            'status' => 'processing',
            'started_at' => now(),
        ])->save();

        $financialSyncService->process($state, $log, $this->messages);
    }

    public function failed(?\Throwable $exception): void
    {
        if (!$exception) {
            return;
        }

        $state = OnboardingState::find($this->onboardingStateId);
        $log = SyncLog::find($this->syncLogId);

        if ($state && $log) {
            app(FinancialSyncService::class)->markFailed($state, $log, $exception);
        }
    }
}
