<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAADataJob;
use App\Models\Consent;
use App\Models\RawAaData;
use App\Services\SetuService;
use Illuminate\Console\Command;

class SyncAAData extends Command
{
    protected $signature = 'aa:sync-transactions {--consent_id=}';
    protected $description = 'Fetch and queue AA transactions for active consents';

    public function handle(SetuService $setuService): int
    {
        $query = Consent::query()->where('status', Consent::STATUS_ACTIVE);

        if ($this->option('consent_id')) {
            $query->where('consent_id', $this->option('consent_id'));
        }

        $consents = $query->get();

        foreach ($consents as $consent) {
            $payload = $setuService->fetchData($consent->consent_id);

            $raw = RawAaData::create([
                'user_id' => $consent->user_id,
                'consent_id' => $consent->id,
                'payload' => $payload,
                'processed' => false,
            ]);

            ProcessAADataJob::dispatch($raw->id)->onQueue('aa-sync');
        }

        $this->info('Queued AA sync for ' . $consents->count() . ' consent(s).');

        return self::SUCCESS;
    }
}
