<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Consent;
use App\Models\RawAaData;
use App\Models\Transaction;
use App\Services\TransactionMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessAADataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $rawAaDataId)
    {
    }

    public function handle(TransactionMapper $transactionMapper): void
    {
        $raw = RawAaData::with('consent')->findOrFail($this->rawAaDataId);

        if ($raw->processed) {
            return;
        }

        $mapped = $transactionMapper->map($raw->payload ?? [], $raw->user_id, $raw->consent_id);

        DB::transaction(function () use ($raw, $mapped): void {
            foreach ($mapped['accounts'] as $accountData) {
                if (empty($accountData['account_ref'])) {
                    continue;
                }

                Account::updateOrCreate(
                    [
                        'user_id' => $raw->user_id,
                        'provider' => 'setu',
                        'account_ref' => $accountData['account_ref'],
                    ],
                    [
                        'masked_account_number' => $accountData['masked_account_number'],
                        'bank_name' => $accountData['bank_name'],
                        'type' => $accountData['type'],
                    ]
                );
            }

            foreach ($mapped['transactions'] as $transactionData) {
                $account = Account::where('user_id', $raw->user_id)
                    ->where('provider', 'setu')
                    ->where('account_ref', $transactionData['account_ref'])
                    ->first();

                if (!$account) {
                    continue;
                }

                $duplicateExists = Transaction::query()
                    ->where('user_id', $raw->user_id)
                    ->where('account_id', $account->id)
                    ->where('metadata->aa_hash', $transactionData['hash'])
                    ->exists();

                if ($duplicateExists) {
                    continue;
                }

                Transaction::create([
                    'transaction_id' => (string) Str::uuid(),
                    'user_id' => $raw->user_id,
                    'account_id' => $account->id,
                    'type' => $transactionData['type'] === 'income' ? 'credit' : 'debit',
                    'amount' => $transactionData['amount'],
                    'merchant' => $transactionData['merchant'],
                    'reference_id' => $transactionData['reference_id'],
                    'raw_data' => $transactionData['raw_data'],
                    'transaction_date' => $transactionData['transaction_date'],
                    'currency' => 'INR',
                    'status' => 'completed',
                    'payment_method' => 'AA',
                    'source_app' => 'setu',
                    'entry_type' => 'aa',
                    'description' => $transactionData['merchant'] ?? 'AA synced transaction',
                    'metadata' => [
                        'aa_hash' => $transactionData['hash'],
                        'consent_id' => $raw->consent_id,
                        'provider' => 'setu',
                    ],
                ]);
            }

            $raw->forceFill(['processed' => true])->save();

            if ($raw->consent && $raw->consent->status !== Consent::STATUS_REVOKED) {
                $raw->consent->forceFill(['status' => Consent::STATUS_ACTIVE])->save();
            }
        });
    }
}
