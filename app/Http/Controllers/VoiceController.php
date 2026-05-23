<?php

namespace App\Http\Controllers;

use App\Models\BalanceHistory;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\VoiceEntry;
use App\Models\Wallet;
use App\Services\Ingestion\UnifiedExpenseIngestionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class VoiceController extends Controller
{
    private const DEFAULT_PAYLOAD = [
        'type' => 'unknown',
        'amount' => null,
        'currency' => 'INR',
        'merchant' => null,
        'category' => 'other',
        'date' => null,
        'time' => null,
        'notes' => null,
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $entries = VoiceEntry::query()
            ->where('user_id', $request->user()->id)
            ->with(['transaction'])
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    public function parse(Request $request)
    {
        $request->validate([
            'transcript' => 'required|string|max:500',
        ]);

        $parsed = $this->parseWithAI($request->input('transcript'));

        $entry = VoiceEntry::create([
            'user_id' => $request->user()->id,
            'raw_transcript' => $request->input('transcript'),
            'parsed_data' => $parsed,
            'status' => 'pending',
        ]);

        return response()->json([
            'entry_id' => $entry->id,
            'parsed' => $parsed,
            'transcript' => $request->input('transcript'),
        ]);
    }

    public function confirm(Request $request, VoiceEntry $voiceEntry)
    {
        if ($voiceEntry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'wallet_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:expense,income,credit,debit',
            'payment_method' => 'required|string|max:50',
            'category_id' => 'nullable|exists:categories,id',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'note' => 'nullable|string',
            'source_app' => 'nullable|string|max:100',
        ]);

        $wallet = $this->resolveUserWallet($request->user()->id, $data['wallet_id'] ?? null);

        if (!empty($data['wallet_id']) && !$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $category = $this->resolveCategorySelection(
            $request->user()->id,
            $data['category_id'] ?? null,
            $data['category_name'] ?? null,
            $data['custom_category_name'] ?? null
        );

        $type = match ($data['type']) {
            'expense' => 'debit',
            'income' => 'credit',
            default => $data['type'],
        };

        $transaction = Transaction::create([
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet?->id,
            'category_id' => $category['id'],
            'category' => $category['name'],
            'type' => $type,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'method' => $data['payment_method'],
            'transaction_date' => $data['transaction_date'],
            'note' => $data['note'] ?? null,
            'description' => $data['note'] ?? 'Voice entry',
            'source_app' => $data['source_app'] ?? null,
            'source_type' => 'voice',
            'merchant_name' => $voiceEntry->parsed_data['merchant'] ?? null,
            'entry_type' => 'voice',
            'currency' => 'INR',
            'status' => 'completed',
        ]);

        $this->updateWalletBalance($transaction);

        $expense = app(UnifiedExpenseIngestionService::class)->ingest($request->user()->id, 'voice', [
            'source_ref_id' => $voiceEntry->id,
            'wallet_id' => $wallet?->id,
            'merchant_name' => $voiceEntry->parsed_data['merchant'] ?? null,
            'amount' => $data['amount'],
            'currency' => 'INR',
            'payment_method' => $data['payment_method'],
            'transaction_type' => $data['payment_method'],
            'expense_date' => $data['transaction_date'],
            'date' => $data['transaction_date'],
            'category_id' => $category['id'],
            'category_name' => $category['name'],
            'description' => $data['note'] ?? 'Voice entry',
            'notes' => $voiceEntry->raw_transcript,
            'status' => Expense::STATUS_ACTIVE,
            'metadata' => [
                'transaction_id' => $transaction->id,
                'parsed_data' => $voiceEntry->parsed_data,
            ],
        ]);

        $transaction->expense_id = $expense->id;
        $transaction->save();

        $voiceEntry->update([
            'transaction_id' => $transaction->id,
            'status' => 'confirmed',
        ]);

        return response()->json($transaction->load(['categoryRel', 'wallet']), 201);
    }

    private function parseWithAI(string $transcript): array
    {
        try {
            $apiKey = config('services.openai.key');
            if (!$apiKey) {
                return $this->fallbackParse($transcript);
            }

            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->buildSystemPrompt(),
                        ],
                        [
                            'role' => 'user',
                            'content' => "User Input:\n\"\"\"\n{$transcript}\n\"\"\"",
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 200,
                ]);

            $content = $response->json('choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            return is_array($decoded)
                ? $this->normalizeParsedPayload($decoded, $transcript)
                : $this->fallbackParse($transcript);
        } catch (\Throwable $e) {
            return $this->fallbackParse($transcript);
        }
    }

    private function buildSystemPrompt(): string
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        return <<<PROMPT
You are a voice expense parser for a personal finance application.

Your task is to extract structured expense or income data from the given spoken sentence.

Return ONLY valid JSON. Do not include any explanation or extra text.

Output JSON format:
{
  "type": "expense | income | unknown",
  "amount": number | null,
  "currency": "INR",
  "merchant": string | null,
  "category": "food | shopping | travel | bills | transfer | recharge | entertainment | salary | other",
  "date": "YYYY-MM-DD" | null,
  "time": "HH:MM" | null,
  "notes": string | null
}

Rules:
- Detect type:
  - expense -> spent, paid, bought, ordered, gave
  - income -> received, earned, got salary, credited
- Extract amount
- Extract merchant/service name (e.g., Swiggy, Amazon, Uber)
- Extract category:
  - Swiggy/Zomato -> food
  - Uber/Ola -> travel
  - Amazon/Flipkart -> shopping
  - Salary -> salary
- Extract date:
  - today -> {$today}
  - yesterday -> {$yesterday}
  - last night -> previous date evening
- Extract time if mentioned (e.g., “at 8 pm” -> 20:00)
- If no date provided, assume today
- If unclear, set fields to null
- Keep notes as original cleaned sentence

If the sentence is not related to expense or income, return:
{
  "type": "unknown",
  "amount": null,
  "currency": "INR",
  "merchant": null,
  "category": "other",
  "date": null,
  "time": null,
  "notes": null
}

Ensure:
- Output is always valid JSON
- Do NOT guess missing values
PROMPT;
    }

    private function fallbackParse(string $transcript): array
    {
        $cleaned = trim(preg_replace('/\s+/', ' ', $transcript));
        preg_match('/(\d+(?:\.\d{1,2})?)/', $cleaned, $amountMatch);
        $type = $this->detectType($cleaned);

        if ($type === 'unknown') {
            return self::DEFAULT_PAYLOAD;
        }

        return [
            'type' => $type,
            'amount' => isset($amountMatch[1]) ? (float) $amountMatch[1] : null,
            'currency' => 'INR',
            'merchant' => $this->extractMerchant($cleaned),
            'category' => $this->detectCategory($cleaned),
            'date' => now()->toDateString(),
            'time' => $this->extractTime($cleaned),
            'notes' => $cleaned !== '' ? $cleaned : null,
        ];
    }

    private function normalizeParsedPayload(array $payload, string $transcript): array
    {
        $normalized = self::DEFAULT_PAYLOAD;
        $cleanedTranscript = trim(preg_replace('/\s+/', ' ', $transcript));

        $normalized['type'] = in_array($payload['type'] ?? null, ['expense', 'income', 'unknown'], true)
            ? $payload['type']
            : 'unknown';

        $normalized['amount'] = is_numeric($payload['amount'] ?? null)
            ? (float) $payload['amount']
            : null;
        $normalized['currency'] = 'INR';
        $normalized['merchant'] = $this->stringOrNull($payload['merchant'] ?? null);
        $normalized['category'] = in_array(
            $payload['category'] ?? null,
            ['food', 'shopping', 'travel', 'bills', 'transfer', 'recharge', 'entertainment', 'salary', 'other'],
            true
        ) ? $payload['category'] : 'other';

        $normalized['date'] = $this->normalizeDate(
            $payload['date'] ?? ($normalized['type'] !== 'unknown' ? now()->toDateString() : null)
        );
        $normalized['time'] = $this->normalizeTime($payload['time'] ?? null);
        $normalized['notes'] = $this->stringOrNull($payload['notes'] ?? $cleanedTranscript);

        return $normalized;
    }

    private function detectType(string $transcript): string
    {
        if (preg_match('/\b(spent|paid|bought|ordered|gave)\b/i', $transcript)) {
            return 'expense';
        }

        if (preg_match('/\b(received|earned|got salary|credited)\b/i', $transcript)) {
            return 'income';
        }

        return 'unknown';
    }

    private function detectCategory(string $transcript): string
    {
        $haystack = strtoupper($transcript);

        return match (true) {
            str_contains($haystack, 'SWIGGY'), str_contains($haystack, 'ZOMATO') => 'food',
            str_contains($haystack, 'UBER'), str_contains($haystack, 'OLA') => 'travel',
            str_contains($haystack, 'AMAZON'), str_contains($haystack, 'FLIPKART') => 'shopping',
            str_contains($haystack, 'SALARY') => 'salary',
            str_contains($haystack, 'RECHARGE') => 'recharge',
            str_contains($haystack, 'BILL'), str_contains($haystack, 'ELECTRICITY') => 'bills',
            str_contains($haystack, 'TRANSFER') => 'transfer',
            str_contains($haystack, 'MOVIE'), str_contains($haystack, 'NETFLIX'), str_contains($haystack, 'SPOTIFY') => 'entertainment',
            default => 'other',
        };
    }

    private function extractMerchant(string $transcript): ?string
    {
        $known = [
            'Swiggy', 'Zomato', 'Uber', 'Ola', 'Amazon', 'Flipkart',
        ];

        foreach ($known as $merchant) {
            if (stripos($transcript, $merchant) !== false) {
                return $merchant;
            }
        }

        if (preg_match('/\b(?:at|to|from|for)\s+([A-Za-z][A-Za-z0-9 &.-]{1,40})/i', $transcript, $match)) {
            return $this->stringOrNull($match[1]);
        }

        return null;
    }

    private function extractTime(string $transcript): ?string
    {
        if (!preg_match('/\b(?:at\s+)?(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $transcript, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = isset($match[2]) ? (int) $match[2] : 0;
        $suffix = strtolower($match[3]);

        if ($suffix === 'pm' && $hour < 12) {
            $hour += 12;
        }

        if ($suffix === 'am' && $hour === 12) {
            $hour = 0;
        }

        return ($hour > 23 || $minute > 59) ? null : sprintf('%02d:%02d', $hour, $minute);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
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

    private function updateWalletBalance(Transaction $transaction): void
    {
        if (!Schema::hasTable('wallets') || !Schema::hasColumn('transactions', 'wallet_id')) {
            return;
        }

        $wallet = Wallet::find($transaction->wallet_id);
        if (!$wallet) {
            return;
        }

        $previous = (float) $wallet->balance;
        $isDebit = in_array($transaction->type, ['expense', Transaction::TYPE_DEBIT], true);

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
