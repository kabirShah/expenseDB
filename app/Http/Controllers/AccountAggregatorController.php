<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAADataJob;
use App\Models\Account;
use App\Models\Consent;
use App\Models\RawAaData;
use App\Models\Transaction;
use App\Services\SetuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountAggregatorController extends Controller
{
    public function __construct(private readonly SetuService $setuService)
    {
        $this->middleware('auth:sanctum')->except('webhook');
    }

    public function createConsent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'redirect_url' => 'required|url',
            'account_types' => 'nullable|array',
            'account_types.*' => 'string|max:50',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        $response = $this->setuService->createConsent([
            'consentHandle' => (string) Str::uuid(),
            'redirectUrl' => $data['redirect_url'],
            'accountTypes' => $data['account_types'] ?? ['savings'],
            'dataRange' => [
                'from' => $data['from_date'] ?? now()->subMonths(3)->toDateString(),
                'to' => $data['to_date'] ?? now()->toDateString(),
            ],
        ]);

        $consent = Consent::create([
            'user_id' => $request->user()->id,
            'consent_id' => (string) ($response['consentId'] ?? $response['id'] ?? ''),
            'consent_handle' => (string) ($response['consentHandle'] ?? $response['handle'] ?? ''),
            'status' => Consent::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'consent_id' => $consent->consent_id,
            'consent_handle' => $consent->consent_handle,
            'consent_url' => $response['consentUrl'] ?? $response['url'] ?? null,
        ], 201);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $this->setuService->handleWebhook(
            $request->all(),
            $request->header('x-setu-signature')
        );

        $consentId = (string) (
            data_get($payload, 'data.consentId')
            ?? data_get($payload, 'consentId')
            ?? ''
        );

        $consentHandle = (string) (
            data_get($payload, 'data.consentHandle')
            ?? data_get($payload, 'consentHandle')
            ?? ''
        );

        $consent = Consent::query()
            ->when($consentId !== '', fn ($query) => $query->where('consent_id', $consentId))
            ->when($consentId === '' && $consentHandle !== '', fn ($query) => $query->where('consent_handle', $consentHandle))
            ->first();

        if (!$consent) {
            return response()->json(['success' => true, 'message' => 'Webhook accepted'], 202);
        }

        DB::transaction(function () use ($consent, $payload): void {
            $consent->forceFill([
                'status' => $this->setuService->normalizeConsentStatus($payload),
                'consent_id' => $consent->consent_id ?: (data_get($payload, 'data.consentId') ?? $consent->consent_id),
                'consent_handle' => $consent->consent_handle ?: (data_get($payload, 'data.consentHandle') ?? $consent->consent_handle),
            ])->save();

            $linkedAccounts = data_get($payload, 'data.accounts', []);

            foreach (is_array($linkedAccounts) ? $linkedAccounts : [] as $accountData) {
                if (!is_array($accountData)) {
                    continue;
                }

                Account::updateOrCreate(
                    [
                        'user_id' => $consent->user_id,
                        'provider' => 'setu',
                        'account_ref' => (string) ($accountData['accountRef'] ?? $accountData['maskedAccNumber'] ?? ''),
                    ],
                    [
                        'masked_account_number' => $accountData['maskedAccNumber'] ?? $accountData['maskedAccountNumber'] ?? null,
                        'bank_name' => $accountData['bankName'] ?? $accountData['fipName'] ?? 'Unknown Bank',
                        'type' => in_array(($accountData['type'] ?? 'savings'), ['savings', 'current'], true)
                            ? $accountData['type']
                            : 'savings',
                    ]
                );
            }
        });

        return response()->json(['success' => true]);
    }

    public function fetchTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'consent_id' => 'required|string',
        ]);

        $consent = Consent::where('user_id', $request->user()->id)
            ->where('consent_id', $data['consent_id'])
            ->firstOrFail();

        $payload = $this->setuService->fetchData($consent->consent_id);

        $raw = RawAaData::create([
            'user_id' => $request->user()->id,
            'consent_id' => $consent->id,
            'payload' => $payload,
            'processed' => false,
        ]);

        ProcessAADataJob::dispatchSync($raw->id);

        $accounts = Account::where('user_id', $request->user()->id)
            ->where('provider', 'setu')
            ->get();

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('source_app', 'setu')
            ->when(
                $accounts->isNotEmpty(),
                fn ($query) => $query->whereIn('account_id', $accounts->pluck('id'))
            )
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json([
            'accounts' => $accounts,
            'transactions' => $transactions,
        ]);
    }
}
