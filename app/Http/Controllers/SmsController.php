<?php

namespace App\Http\Controllers;

use App\Models\SmsEntry;
use App\Models\BalanceHistory;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Ingestion\UnifiedExpenseIngestionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SmsController extends Controller
{
    private const DEFAULT_PAYLOAD = [
        'type' => 'unknown',
        'amount' => null,
        'currency' => 'INR',
        'merchant' => null,
        'account_last4' => null,
        'date' => null,
        'time' => null,
        'reference' => null,
        'category' => 'other',
        'payment_source' => null,
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $query = SmsEntry::query()
            ->where('user_id', $request->user()->id);

        if (!$request->boolean('all')) {
            $query->where('is_financial', true);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query
                ->orderByDesc('received_at')
                ->orderByDesc('created_at')
                ->paginate((int) $request->input('per_page', 20)),
        ]);
    }

    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sms_body' => 'required|string|max:5000',
        ]);

        return response()->json($this->normalizeParsedPayload(
            $this->parseWithAI($data['sms_body'])
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sms_body' => 'required|string|max:5000',
            'sender' => 'nullable|string|max:100',
            'received_at' => 'nullable|date',
            'source_app' => 'nullable|string|max:100',
            'external_id' => 'nullable|string|max:191',
        ]);

        $entry = $this->upsertSmsEntry($request->user()->id, $data);

        return response()->json([
            'success' => true,
            'message' => 'SMS parsed and saved',
            'data' => $entry,
        ], 201);
    }

    public function sync(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.sms_body' => 'required|string|max:5000',
            'messages.*.sender' => 'nullable|string|max:100',
            'messages.*.received_at' => 'nullable|date',
            'messages.*.source_app' => 'nullable|string|max:100',
            'messages.*.external_id' => 'nullable|string|max:191',
        ]);

        $saved = [];

        foreach ($payload['messages'] as $message) {
            $saved[] = $this->upsertSmsEntry($request->user()->id, $message);
        }

        $financialCount = collect($saved)->where('is_financial', true)->count();

        return response()->json([
            'success' => true,
            'message' => 'SMS messages synced',
            'count' => count($saved),
            'financial_count' => $financialCount,
            'data' => $saved,
        ], 201);
    }

    public function show(Request $request, SmsEntry $smsEntry): JsonResponse
    {
        if ($smsEntry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $smsEntry,
        ]);
    }

    public function updateStatus(Request $request, SmsEntry $smsEntry): JsonResponse
    {
        if ($smsEntry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,ignored',
            'wallet_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
        ]);

        $smsEntry->status = $data['status'];

        if ($data['status'] === 'confirmed' && !$smsEntry->transaction_id) {
            $transaction = $this->createTransactionFromSms($request, $smsEntry, $data);

            if ($transaction) {
                $smsEntry->transaction_id = $transaction->id;
            }
        }

        $smsEntry->save();

        return response()->json([
            'success' => true,
            'message' => 'SMS status updated',
            'data' => $smsEntry->fresh(['transaction']),
        ]);
    }

    private function upsertSmsEntry(int $userId, array $attributes): SmsEntry
    {
        $parsed = $this->normalizeParsedPayload($this->parseWithAI($attributes['sms_body']));
        $isFinancial = $parsed['type'] !== 'unknown' || $parsed['amount'] !== null;

        $entry = null;

        if (!empty($attributes['external_id'])) {
            $entry = SmsEntry::firstOrNew([
                'user_id' => $userId,
                'external_id' => $attributes['external_id'],
            ]);
        }

        if (!$entry) {
            $entry = new SmsEntry();
            $entry->user_id = $userId;
        }

        $entry->sender = $attributes['sender'] ?? null;
        $entry->sms_body = $attributes['sms_body'];
        $entry->parsed_data = $parsed;
        $entry->status = $entry->status ?? 'pending';
        $entry->is_financial = $isFinancial;
        $entry->received_at = $attributes['received_at'] ?? null;
        $entry->source_app = $attributes['source_app'] ?? null;
        $entry->external_id = $attributes['external_id'] ?? null;
        $entry->save();

        Log::info('SMS payment source parsed', [
            'sms_entry_id' => $entry->id,
            'user_id' => $userId,
            'sender' => $entry->sender,
            'source_app' => $entry->source_app,
            'amount' => $parsed['amount'],
            'payment_source' => $parsed['payment_source'] ?? null,
        ]);

        return $entry->fresh();
    }

    private function parseWithAI(string $smsBody): array
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            return $this->fallbackParse($smsBody);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->buildSystemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => "SMS:\n\"\"\"\n{$smsBody}\n\"\"\"",
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 300,
                ]);

            $content = $response->json('choices.0.message.content');

            if (!is_string($content) || trim($content) === '') {
                return $this->fallbackParse($smsBody);
            }

            $decoded = json_decode($content, true);

            return is_array($decoded)
                ? $decoded
                : $this->fallbackParse($smsBody);
        } catch (\Throwable $e) {
            return $this->fallbackParse($smsBody);
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a financial SMS parser for an expense tracking application.

Your task is to extract structured transaction data from the SMS provided below.

Return ONLY valid JSON. Do not include any explanation or extra text.

Output JSON format:
{
  "type": "debit | credit | unknown",
  "amount": number | null,
  "currency": "INR",
  "merchant": string | null,
  "account_last4": string | null,
  "date": "YYYY-MM-DD" | null,
  "time": "HH:MM" | null,
  "reference": string | null,
  "category": "food | shopping | travel | bills | transfer | recharge | entertainment | other"
}

Rules:
- Identify transaction type:
  - debit -> debited, spent, paid, purchase, sent
  - credit -> credited, received, deposited
- Extract amount (handle Rs, INR formats)
- Extract last 4 digits of account/card if available (e.g., XX1234 -> 1234)
- Extract merchant name and normalize it:
  - Remove prefixes like UPI-, PAYTM-, NEFT-, IMPS-
  - Convert to clean name (e.g., AMAZON PAY -> Amazon, SWIGGY INSTAMART -> Swiggy)
- Extract date and convert to YYYY-MM-DD format if present
- Extract time and convert to HH:MM (24-hour format) if present
- Extract transaction/reference ID if available
- Categorize based on merchant:
  - Swiggy, Zomato -> food
  - Amazon, Flipkart -> shopping
  - Uber, Ola -> travel
  - Electricity, Recharge -> bills or recharge
- Ignore OTP, promotional, and non-transaction messages

If the SMS is NOT a financial transaction, return:
{
  "type": "unknown",
  "amount": null,
  "currency": "INR",
  "merchant": null,
  "account_last4": null,
  "date": null,
  "time": null,
  "reference": null,
  "category": "other"
}

Constraints:
- Do NOT guess missing values
- If any field is unclear, set it to null
- Ensure output is always valid JSON
PROMPT;
    }

    private function fallbackParse(string $smsBody): array
    {
        $text = trim($smsBody);
        $lower = strtolower($text);

        if ($this->looksNonTransactional($lower)) {
            return self::DEFAULT_PAYLOAD;
        }

        $payload = self::DEFAULT_PAYLOAD;
        $payload['type'] = $this->detectType($lower);
        $payload['amount'] = $this->extractAmount($text);
        $payload['account_last4'] = $this->extractAccountLast4($text);
        $payload['reference'] = $this->extractReference($text);
        $payload['merchant'] = $this->extractMerchant($text);
        $payload['date'] = $this->extractDate($text);
        $payload['time'] = $this->extractTime($text);
        $payload['category'] = $this->detectCategory($payload['merchant'], $text);

        if ($payload['type'] === 'unknown' && $payload['amount'] === null) {
            return self::DEFAULT_PAYLOAD;
        }

        return $payload;
    }

    private function normalizeParsedPayload(array $payload): array
    {
        $normalized = self::DEFAULT_PAYLOAD;

        $normalized['type'] = in_array($payload['type'] ?? null, ['debit', 'credit', 'unknown'], true)
            ? $payload['type']
            : 'unknown';

        $normalized['amount'] = is_numeric($payload['amount'] ?? null)
            ? (float) $payload['amount']
            : null;
        $normalized['currency'] = 'INR';
        $normalized['merchant'] = $this->stringOrNull($payload['merchant'] ?? null);
        $normalized['account_last4'] = $this->normalizeLast4($payload['account_last4'] ?? null);
        $normalized['date'] = $this->normalizeDate($payload['date'] ?? null);
        $normalized['time'] = $this->normalizeTime($payload['time'] ?? null);
        $normalized['reference'] = $this->stringOrNull($payload['reference'] ?? null);

        $normalized['category'] = in_array(
            $payload['category'] ?? null,
            ['food', 'shopping', 'travel', 'bills', 'transfer', 'recharge', 'entertainment', 'other'],
            true
        ) ? $payload['category'] : 'other';
        $normalized['payment_source'] = $this->detectPaymentSource($payload);

        return $normalized;
    }

    private function looksNonTransactional(string $text): bool
    {
        return str_contains($text, 'otp')
            || str_contains($text, 'one time password')
            || str_contains($text, 'promo')
            || str_contains($text, 'offer')
            || str_contains($text, 'sale')
            || str_contains($text, 'discount')
            || str_contains($text, 'win cash');
    }

    private function detectType(string $text): string
    {
        if (preg_match('/\b(debited|spent|paid|purchase|purchased|sent|dr)\b/i', $text)) {
            return 'debit';
        }

        if (preg_match('/\b(credited|received|deposited|cr)\b/i', $text)) {
            return 'credit';
        }

        return 'unknown';
    }

    private function extractAmount(string $text): ?float
    {
        if (preg_match('/(?:rs\.?|inr|mrp)\s*[:.-]?\s*([0-9,]+(?:\.[0-9]{1,2})?)/i', $text, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }

        if (preg_match('/(?:^|[^\d])([0-9,]+(?:\.[0-9]{1,2})?)(?:[^\d]|$)/', $text, $match)) {
            return (float) str_replace(',', '', $match[1]);
        }

        return null;
    }

    private function extractAccountLast4(string $text): ?string
    {
        $patterns = [
            '/(?:a\/c|acct|account|card)\s*(?:xx|x{2,}|\*{2,})?(\d{4})/i',
            '/(?:xx|x{2,}|\*{2,})(\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $match[1];
            }
        }

        return null;
    }

    private function extractReference(string $text): ?string
    {
        if (preg_match('/(?:utr|ref(?:erence)?|txn|transaction id|upi ref(?: no)?)[\s:.-]*([A-Z0-9\-]+)/i', $text, $match)) {
            return $match[1];
        }

        return null;
    }

    private function extractMerchant(string $text): ?string
    {
        $patterns = [
            '/(?:at|to|from)\s+([A-Za-z0-9 .&-]{2,50})/i',
            '/(?:info|merchant)[\s:.-]+([A-Za-z0-9 .&-]{2,50})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $merchant = $this->normalizeMerchant($match[1]);
                if ($merchant !== null) {
                    return $merchant;
                }
            }
        }

        return null;
    }

    private function normalizeMerchant(?string $merchant): ?string
    {
        if (!$merchant) {
            return null;
        }

        $merchant = trim($merchant);
        $merchant = preg_replace('/\b(UPI|PAYTM|NEFT|IMPS)[- ]*/i', '', $merchant);
        $merchant = preg_replace('/\s+/', ' ', $merchant ?? '');
        $merchant = trim($merchant ?? '', " .-\t\n\r\0\x0B");

        if ($merchant === '') {
            return null;
        }

        $upper = strtoupper($merchant);

        return match (true) {
            str_contains($upper, 'AMAZON') => 'Amazon',
            str_contains($upper, 'FLIPKART') => 'Flipkart',
            str_contains($upper, 'SWIGGY') => 'Swiggy',
            str_contains($upper, 'ZOMATO') => 'Zomato',
            str_contains($upper, 'UBER') => 'Uber',
            str_contains($upper, 'OLA') => 'Ola',
            default => ucwords(strtolower($merchant)),
        };
    }

    private function extractDate(string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})\b/', $text, $match)) {
            $day = (int) $match[1];
            $month = (int) $match[2];
            $year = (int) $match[3];

            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    private function extractTime(string $text): ?string
    {
        if (!preg_match('/\b(\d{1,2}):(\d{2})(?:\s*([AP]M))?\b/i', $text, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $suffix = strtoupper($match[3] ?? '');

        if ($suffix === 'PM' && $hour < 12) {
            $hour += 12;
        }

        if ($suffix === 'AM' && $hour === 12) {
            $hour = 0;
        }

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private function detectCategory(?string $merchant, string $text): string
    {
        $haystack = strtoupper(($merchant ?? '') . ' ' . $text);

        return match (true) {
            str_contains($haystack, 'SWIGGY'), str_contains($haystack, 'ZOMATO') => 'food',
            str_contains($haystack, 'AMAZON'), str_contains($haystack, 'FLIPKART') => 'shopping',
            str_contains($haystack, 'UBER'), str_contains($haystack, 'OLA') => 'travel',
            str_contains($haystack, 'RECHARGE') => 'recharge',
            str_contains($haystack, 'ELECTRICITY'), str_contains($haystack, 'BILL') => 'bills',
            str_contains($haystack, 'TRANSFER'), str_contains($haystack, 'NEFT'), str_contains($haystack, 'IMPS') => 'transfer',
            str_contains($haystack, 'MOVIE'), str_contains($haystack, 'NETFLIX'), str_contains($haystack, 'SPOTIFY') => 'entertainment',
            default => 'other',
        };
    }

    private function normalizeLast4(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) ? $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function createTransactionFromSms(Request $request, SmsEntry $smsEntry, array $data): ?Transaction
    {
        $parsed = $this->normalizeParsedPayload($smsEntry->parsed_data ?? []);
        $amount = $parsed['amount'] ?? null;
        $type = $parsed['type'] ?? 'unknown';

        if (!is_numeric($amount) || (float) $amount <= 0 || !in_array($type, ['debit', 'credit'], true)) {
            return null;
        }

        $wallet = $this->resolveWallet($request->user()->id, $data['wallet_id'] ?? null);
        $category = $this->resolveCategorySelection(
            $request->user()->id,
            $data['category_id'] ?? null,
            $data['category_name'] ?? ($parsed['category'] ?? null),
            $data['custom_category_name'] ?? null
        );

        $transaction = Transaction::create([
            'transaction_id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet?->id,
            'category_id' => $category['id'],
            'category' => $category['name'],
            'type' => $type,
            'amount' => (float) $amount,
            'payment_method' => 'SMS',
            'description' => $parsed['merchant']
                ? 'SMS ' . ucfirst($type) . ' - ' . $parsed['merchant']
                : 'SMS ' . ucfirst($type),
            'note' => $smsEntry->sms_body,
            'reference_no' => $parsed['reference'],
            'source_app' => $smsEntry->source_app,
            'entry_type' => 'sms',
            'currency' => $parsed['currency'] ?? 'INR',
            'status' => 'completed',
            'transaction_date' => $this->resolveSmsTransactionDate($smsEntry, $parsed),
        ]);

        if ($wallet) {
            $this->updateWalletBalance($transaction, $wallet);
        }

        $expense = app(UnifiedExpenseIngestionService::class)->ingest($request->user()->id, 'sms', [
            'source_ref_id' => $smsEntry->id,
            'wallet_id' => $wallet?->id,
            'source_app' => $smsEntry->source_app,
            'merchant_name' => $parsed['merchant'] ?? null,
            'amount' => (float) $amount,
            'currency' => $parsed['currency'] ?? 'INR',
            'payment_method' => 'SMS',
            'payment_source' => $parsed['payment_source'] ?? null,
            'transaction_type' => 'SMS',
            'expense_date' => $transaction->transaction_date,
            'date' => $transaction->transaction_date,
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'description' => $transaction->description,
            'notes' => $smsEntry->sms_body,
            'status' => Expense::STATUS_ACTIVE,
            'metadata' => [
                'transaction_id' => $transaction->id,
                'sender' => $smsEntry->sender,
                'reference' => $parsed['reference'] ?? null,
                'account_last4' => $parsed['account_last4'] ?? null,
            ],
        ]);

        $transaction->expense_id = $expense->id;
        $transaction->save();

        return $transaction;
    }

    private function detectPaymentSource(array $payload): ?string
    {
        if (!config('features.enable_payment_source_detection', true)) {
            return null;
        }

        $parts = array_filter([
            $payload['payment_source'] ?? null,
            $payload['merchant'] ?? null,
            $payload['reference'] ?? null,
            $payload['source_app'] ?? null,
        ], static fn ($value) => is_scalar($value) && trim((string) $value) !== '');

        $haystack = strtoupper(implode(' ', array_map(static fn ($value) => (string) $value, $parts)));

        return match (true) {
            $haystack === '' => null,
            str_contains($haystack, 'GOOGLE PAY'),
            str_contains($haystack, 'GPAY') => 'gpay',
            str_contains($haystack, 'PHONEPE') => 'phonepe',
            str_contains($haystack, 'PAYTM') => 'paytm',
            str_contains($haystack, 'UPI') => 'upi',
            str_contains($haystack, 'BANK'),
            str_contains($haystack, 'A/C'),
            str_contains($haystack, 'ACCOUNT'),
            str_contains($haystack, 'NEFT'),
            str_contains($haystack, 'IMPS') => 'bank',
            default => 'unknown',
        };
    }

    private function resolveWallet(int $userId, ?int $walletId): ?Wallet
    {
        if (!Schema::hasTable('wallets')) {
            return null;
        }

        if ($walletId) {
            return Wallet::where('user_id', $userId)->find($walletId);
        }

        return Wallet::where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function resolveSmsTransactionDate(SmsEntry $smsEntry, array $parsed): string
    {
        if (!empty($parsed['date'])) {
            $date = $parsed['date'];
            $time = $parsed['time'] ?? '00:00';

            return Carbon::parse($date . ' ' . $time)->toDateTimeString();
        }

        return ($smsEntry->received_at ?? now())->toDateTimeString();
    }

    private function updateWalletBalance(Transaction $transaction, Wallet $wallet): void
    {
        $previous = (float) $wallet->balance;
        $isDebit = $transaction->type === Transaction::TYPE_DEBIT;

        $wallet->balance = $isDebit
            ? $previous - (float) $transaction->amount
            : $previous + (float) $transaction->amount;
        $wallet->save();

        if (Schema::hasTable('balance_histories')) {
            BalanceHistory::create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'previous_balance' => $previous,
                'new_balance' => (float) $wallet->balance,
                'change_amount' => (float) $transaction->amount,
                'change_type' => $isDebit ? 'debit' : 'credit',
            ]);
        }
    }
}
