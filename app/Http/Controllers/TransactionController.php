<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BalanceHistory;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Budget\BudgetInsightService;
use App\Services\TransactionService;
use App\Services\TransactionParserService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionParserService $transactionParserService,
        private readonly BudgetInsightService $budgetInsightService,
        private readonly TransactionService $transactionService
    )
    {
        $this->middleware('auth:sanctum');
    }

    // Get all transactions for logged-in user
    public function index(Request $request)
    {
        if ($this->isPhase1TransactionRequest($request) || $this->hasColumn('transactions', 'wallet_id')) {
            $query = Transaction::query()
                ->where('user_id', $request->user()->id)
                ->with(['category', 'wallet']);

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            if ($request->filled('category_id') && $this->hasColumn('transactions', 'category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }
            if ($request->filled('wallet_id') && $this->hasColumn('transactions', 'wallet_id')) {
                $query->where('wallet_id', $request->integer('wallet_id'));
            }
            if ($request->filled('payment_method') && $this->hasColumn('transactions', 'payment_method')) {
                $query->where('payment_method', $request->input('payment_method'));
            }
            if ($request->filled('source_app') && $this->hasColumn('transactions', 'source_app')) {
                $query->where('source_app', $request->input('source_app'));
            }
            if ($request->filled('date_from')) {
                $query->whereDate('transaction_date', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('transaction_date', '<=', $request->input('date_to'));
            }
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($builder) use ($search) {
                    $builder->where('note', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%");
                });
            }

            return $query
                ->orderByDesc('transaction_date')
                ->orderByDesc('created_at')
                ->paginate((int) $request->input('per_page', 20));
        }

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Store new transaction
    public function store(Request $request)
    {
        if ($this->isPhase1TransactionRequest($request)) {
            return $this->storePhase1($request);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:credit,debit,transfer,refund',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:pending,completed,failed,refunded',
            'transaction_date' => 'required|date',
            'payment_provider_id' => 'nullable|exists:payment_providers,id',
            'credit_card_id' => 'nullable|exists:credit_cards,id',
            'debit_card_id' => 'nullable|exists:debit_cards,id',
            'expense_id' => 'nullable|exists:expenses,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'reference_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['transaction_id'] = Str::uuid();

        $transaction = Transaction::create($data);

        // Load relationships for response
        $transaction->load(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice']);

        return response()->json(['success' => true, 'message' => 'Transaction created', 'data' => $transaction], 201);
    }

    // Show single transaction
    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $transaction]);
    }

    // Update transaction
    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $validated = $request->validate([
            'type' => 'required|in:credit,debit,transfer,refund',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:pending,completed,failed,refunded',
            'transaction_date' => 'required|date',
            'payment_provider_id' => 'nullable|exists:payment_providers,id',
            'credit_card_id' => 'nullable|exists:credit_cards,id',
            'debit_card_id' => 'nullable|exists:debit_cards,id',
            'expense_id' => 'nullable|exists:expenses,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'reference_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $transaction->update($validated);

        // Load relationships for response
        $transaction->load(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice']);

        return response()->json(['success' => true, 'message' => 'Transaction updated', 'data' => $transaction]);
    }

    // Delete transaction
    public function destroy(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $transaction->delete();
        return response()->json(['success' => true, 'message' => 'Transaction deleted']);
    }

    // Get transactions by type
    public function byType(Request $request, $type)
    {
        $validTypes = ['credit', 'debit', 'transfer', 'refund'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction type'], 400);
        }

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('type', $type)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Get transactions by status
    public function byStatus(Request $request, $status)
    {
        $validStatuses = ['pending', 'completed', 'failed', 'refunded'];
        
        if (!in_array($status, $validStatuses)) {
            return response()->json(['success' => false, 'message' => 'Invalid transaction status'], 400);
        }

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('status', $status)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Get transactions by date range
    public function byDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dates = $validator->validated();

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->whereBetween('transaction_date', [$dates['start_date'], $dates['end_date']])
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Get transactions summary
    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        $totalCredits = Transaction::where('user_id', $userId)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDebits = Transaction::where('user_id', $userId)
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');

        $pendingCount = Transaction::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        $failedCount = Transaction::where('user_id', $userId)
            ->where('status', 'failed')
            ->count();

        $balance = $totalCredits - $totalDebits;

        return response()->json([
            'success' => true,
            'data' => [
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'balance' => $balance,
                'pending_transactions' => $pendingCount,
                'failed_transactions' => $failedCount,
                'currency' => 'INR'
            ]
        ]);
    }

    // Get transactions by category
    public function byCategory(Request $request, $category)
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('category', $category)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Update transaction status
    public function updateStatus(Request $request, $id)
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,completed,failed,refunded',
        ]);

        $transaction->update($validated);

        return response()->json(['success' => true, 'message' => 'Transaction status updated', 'data' => $transaction]);
    }

    public function autoDetect(Request $request)
    {
        $result = $this->transactionParserService->process($request->user()->id, $request->all());

        if (($result['status'] ?? null) === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'features' => $this->featureFlags(),
            ], 403);
        }

        if (($result['status'] ?? null) === 'ignored') {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'features' => $this->featureFlags(),
            ], 202);
        }

        $budgetStatus = $this->budgetInsightService->dashboardStatusForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'status' => $result['status'],
            'data' => $result['data'] ?? null,
            'budget_status' => $budgetStatus,
            'features' => $this->featureFlags(),
        ], ($result['status'] ?? null) === 'duplicate' ? 200 : 201);
    }

    public function parseDetection(Request $request)
    {
        $data = $request->validate([
            'raw_text' => 'required|string|max:5000',
            'package_name' => 'nullable|string|max:150',
            'received_at' => 'nullable|date',
        ]);

        $result = $this->transactionService->parseDetection($request->user()->id, $data);

        if ($result['duplicate'] ?? false) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'data' => $result['transaction'],
            ]);
        }

        if ($result['ignored'] ?? false) {
            return response()->json([
                'success' => true,
                'duplicate' => false,
                'ignored' => true,
            ], 202);
        }

        return response()->json([
            'success' => true,
            'duplicate' => false,
            'data' => $result['transaction'],
        ], 201);
    }

    public function transactionSummary(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->transactionService->summary($request->user()->id),
        ]);
    }

    // Phase 1 alias: POST /api/transactions/multi
    public function multi(Request $request)
    {
        if ($this->isPhase1MultiRequest($request)) {
            return $this->storePhase1Multi($request);
        }

        $validator = Validator::make($request->all(), [
            'transactions' => 'required|array|min:1',
            'transactions.*.type' => 'required|in:credit,debit,transfer,refund',
            'transactions.*.category' => 'required|string|max:255',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric|min:0',
            'transactions.*.currency' => 'nullable|string|size:3',
            'transactions.*.status' => 'nullable|in:pending,completed,failed,refunded',
            'transactions.*.transaction_date' => 'nullable|date',
            'batch_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $batchId = $request->input('batch_id', (string) Str::uuid());
        $created = [];

        foreach ($request->input('transactions', []) as $entry) {
            $created[] = Transaction::create([
                'transaction_id' => Str::uuid(),
                'user_id' => $request->user()->id,
                'type' => $entry['type'],
                'category' => $entry['category'],
                'description' => $entry['description'],
                'amount' => $entry['amount'],
                'currency' => strtoupper($entry['currency'] ?? 'INR'),
                'status' => $entry['status'] ?? 'completed',
                'reference_id' => $batchId,
                'metadata' => ['batch_id' => $batchId],
                'transaction_date' => $entry['transaction_date'] ?? now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk transactions created',
            'batch_id' => $batchId,
            'data' => $created,
        ], 201);
    }

    // Phase 1 alias: POST /api/transactions/scan
    public function scan(Request $request)
    {
        if ($request->hasFile('receipt')) {
            $request->validate(['receipt' => 'required|image|max:10240']);

            $path = $request->file('receipt')->store('receipts', 'public');

            return response()->json([
                'receipt_path' => $path,
                'parsed_data' => null,
                'message' => 'Receipt uploaded. OCR processing in Phase 2.',
            ]);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|string|max:255',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
            'wallet_id' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:100',
            'source_app' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date',
        ]);

        $category = $this->resolveCategorySelection(
            $request->user()->id,
            $data['category_id'] ?? null,
            $data['category_name'] ?? ($data['category'] ?? null),
            $data['custom_category_name'] ?? null
        );

        if (!$category['name']) {
            return response()->json([
                'success' => false,
                'message' => 'Category is required',
                'errors' => ['category' => ['Category is required']],
            ], 422);
        }

        $wallet = null;
        if ($this->hasTable('wallets')) {
            $wallet = !empty($data['wallet_id'])
                ? $this->findUserWallet($request->user()->id, (int) $data['wallet_id'])
                : Wallet::where('user_id', $request->user()->id)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->first();
        }

        $transaction = Transaction::create([
            'transaction_id' => Str::uuid(),
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet?->id,
            'category_id' => $category['id'],
            'type' => 'debit',
            'category' => $category['name'],
            'description' => $data['description'] ?? 'Scanned receipt transaction',
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? 'INR'),
            'status' => 'completed',
            'entry_type' => 'scan',
            'payment_method' => $data['payment_method'] ?? 'scan',
            'source_app' => $data['source_app'] ?? 'scan',
            'metadata' => [
                'payment_method' => $data['payment_method'] ?? null,
                'source_app' => $data['source_app'] ?? null,
                'source' => 'scan',
            ],
            'transaction_date' => $data['transaction_date'] ?? now(),
        ]);

        if ($wallet) {
            $this->updateWalletBalance($transaction, $wallet);
        }

        return response()->json([
            'success' => true,
            'message' => 'Scanned transaction saved',
            'data' => $transaction->load(['wallet']),
        ], 201);
    }

    // Phase 1 alias: GET /api/transactions/by-batch/{batchId}
    public function byBatch(Request $request, string $batchId)
    {
        if ($this->hasColumn('transactions', 'batch_id')) {
            $transactions = Transaction::where('user_id', $request->user()->id)
                ->where('batch_id', $batchId)
                ->with(['category', 'wallet'])
                ->orderByDesc('transaction_date')
                ->get();

            return response()->json([
                'success' => true,
                'batch_id' => $batchId,
                'transactions' => $transactions,
                'data' => $transactions,
            ]);
        }

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('reference_id', $batchId)
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'data' => $transactions,
        ]);
    }

    private function storePhase1(Request $request)
    {
        $request->validate([
            'wallet_id' => 'nullable|integer',
            'type' => 'required|in:expense,income,transfer',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'category_id' => 'nullable|integer',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:100',
            'source_app' => 'nullable|string|max:100',
        ]);

        return DB::transaction(function () use ($request) {
            $walletId = $request->input('wallet_id');
            $wallet = $walletId ? $this->findUserWallet($request->user()->id, (int) $walletId) : null;
            if ($walletId && !$wallet) {
                return response()->json(['message' => 'Wallet not found'], 404);
            }

            $category = $this->resolveCategorySelection(
                $request->user()->id,
                $request->input('category_id'),
                $request->input('category_name'),
                $request->input('custom_category_name')
            );

            $transaction = Transaction::create($this->filterTransactionColumns([
                'transaction_id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet?->id,
                'category_id' => $category['id'] ?? $this->findAccessibleCategoryId($request->user()->id, $request->input('category_id')),
                'category' => $category['name'],
                'type' => $request->input('type') === 'income' ? 'credit' : 'debit',
                'amount' => $request->input('amount'),
                'note' => $request->input('note'),
                'description' => $request->input('description'),
                'transaction_date' => $request->input('transaction_date'),
                'payment_method' => $request->input('payment_method'),
                'reference_no' => $request->input('reference_no'),
                'source_app' => $request->input('source_app'),
                'receipt_image' => $request->input('receipt_image'),
                'entry_type' => 'single',
                'source_type' => 'single',
                'currency' => strtoupper($request->user()->currency ?? 'INR'),
                'status' => 'completed',
            ]));

            if ($wallet) {
                $this->updateWalletBalance($transaction, $wallet);
            }

            return response()->json($transaction->load(['category', 'wallet']), 201);
        });
    }

    private function storePhase1Multi(Request $request)
    {
        $request->validate([
            'transactions' => 'required|array|min:1',
            'transactions.*.wallet_id' => 'nullable|integer',
            'transactions.*.type' => 'required|in:expense,income,transfer',
            'transactions.*.amount' => 'required|numeric|min:0.01',
            'transactions.*.transaction_date' => 'required|date',
            'transactions.*.payment_method' => 'required|string|max:50',
            'transactions.*.category_id' => 'nullable|integer',
            'transactions.*.category_name' => 'nullable|string|max:255',
            'transactions.*.custom_category_name' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request) {
            $batchId = (string) Str::uuid();
            $created = [];

            foreach ($request->input('transactions', []) as $entry) {
                $walletId = $entry['wallet_id'] ?? null;
                $wallet = $walletId ? $this->findUserWallet($request->user()->id, (int) $walletId) : null;
                if ($walletId && !$wallet) {
                    abort(response()->json(['message' => 'Wallet not found'], 404));
                }

                $category = $this->resolveCategorySelection(
                    $request->user()->id,
                    $entry['category_id'] ?? null,
                    $entry['category_name'] ?? null,
                    $entry['custom_category_name'] ?? null
                );

                $transaction = Transaction::create($this->filterTransactionColumns([
                    'transaction_id' => (string) Str::uuid(),
                    'user_id' => $request->user()->id,
                    'wallet_id' => $wallet?->id,
                    'category_id' => $category['id'] ?? $this->findAccessibleCategoryId($request->user()->id, $entry['category_id'] ?? null),
                    'category' => $category['name'],
                    'type' => ($entry['type'] ?? null) === 'income' ? 'credit' : 'debit',
                    'amount' => $entry['amount'],
                    'note' => $entry['note'] ?? null,
                    'description' => $entry['description'] ?? null,
                    'transaction_date' => $entry['transaction_date'],
                    'payment_method' => $entry['payment_method'],
                    'reference_no' => $entry['reference_no'] ?? null,
                    'source_app' => $entry['source_app'] ?? null,
                    'receipt_image' => $entry['receipt_image'] ?? null,
                    'entry_type' => 'multi',
                    'source_type' => 'multi',
                    'batch_id' => $batchId,
                    'currency' => strtoupper($request->user()->currency ?? 'INR'),
                    'status' => 'completed',
                ]));

                if ($wallet) {
                    $this->updateWalletBalance($transaction, $wallet);
                }
                $created[] = $transaction->load(['category', 'wallet']);
            }

            return response()->json([
                'batch_id' => $batchId,
                'transactions' => $created,
                'count' => count($created),
            ], 201);
        });
    }

    private function updateWalletBalance(Transaction $transaction, ?Wallet $wallet = null): void
    {
        if (!$this->hasTable('wallets') || !$this->hasColumn('transactions', 'wallet_id')) {
            return;
        }

        $wallet = $wallet ?: Wallet::find($transaction->wallet_id);
        if (!$wallet) {
            return;
        }

        $previous = (float) $wallet->balance;
        $type = $transaction->type;

        if ($type === 'expense' || $type === self::legacyDebitType()) {
            $wallet->balance = $previous - (float) $transaction->amount;
            $changeType = 'debit';
        } else {
            $wallet->balance = $previous + (float) $transaction->amount;
            $changeType = 'credit';
        }

        $wallet->save();

        if ($this->hasTable('balance_histories')) {
            BalanceHistory::create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'transaction_id' => $transaction->id,
                'previous_balance' => $previous,
                'new_balance' => (float) $wallet->balance,
                'change_amount' => (float) $transaction->amount,
                'change_type' => $changeType,
            ]);
        }
    }

    private function findUserWallet(int $userId, int $walletId): ?Wallet
    {
        if (!$this->hasTable('wallets')) {
            return null;
        }

        return Wallet::where('user_id', $userId)->find($walletId);
    }

    private function findAccessibleCategoryId(int $userId, mixed $categoryId): mixed
    {
        if (!$categoryId || !$this->hasColumn('transactions', 'category_id')) {
            return null;
        }

        if (!$this->hasTable('categories')) {
            return null;
        }

        $query = Category::query()->whereKey($categoryId);

        if ($this->hasColumn('categories', 'user_id')) {
            $query->where(function ($builder) use ($userId) {
                $builder->whereNull('user_id')->orWhere('user_id', $userId);
            });
        }

        return $query->exists() ? $categoryId : null;
    }

    private function isPhase1TransactionRequest(Request $request): bool
    {
        return $request->has('wallet_id')
            || in_array($request->input('type'), ['expense', 'income', 'transfer'], true)
            || $request->has('payment_method')
            || $request->has('category_id');
    }

    private function isPhase1MultiRequest(Request $request): bool
    {
        $first = $request->input('transactions.0', []);

        return is_array($first) && (
            array_key_exists('wallet_id', $first)
            || in_array($first['type'] ?? null, ['expense', 'income', 'transfer'], true)
            || array_key_exists('payment_method', $first)
            || array_key_exists('category_id', $first)
        );
    }

    private function filterTransactionColumns(array $attributes): array
    {
        $columns = Schema::hasTable('transactions') ? Schema::getColumnListing('transactions') : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private static function legacyDebitType(): string
    {
        return defined(Transaction::class . '::TYPE_DEBIT') ? Transaction::TYPE_DEBIT : 'debit';
    }

    private function featureFlags(): array
    {
        return [
            'enable_payment_source_detection' => (bool) config('features.enable_payment_source_detection', true),
            'enable_auto_tracking' => (bool) config('features.enable_auto_tracking', true),
        ];
    }
}
