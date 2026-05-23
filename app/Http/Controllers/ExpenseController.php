<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\AutoExpenseService;
use App\Services\UnifiedTransactionService;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $period = $request->query('period', 'month');
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));

        $query = Expense::active()
            ->where('user_id', $user->id)
            ->with(['category:id,name', 'wallet:id,name,balance']);

        switch ($period) {
            case 'today':
                $query->whereDate('date', Carbon::today());
                break;

            case 'week':
                $query->whereBetween('date', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
                break;

            case '6months':
                $query->whereBetween('date', [
                    Carbon::now()->subMonths(6),
                    Carbon::now(),
                ]);
                break;

            case 'month':
            default:
                $query->whereMonth('date', Carbon::now()->month)
                    ->whereYear('date', Carbon::now()->year);
                break;
        }

        $expenses = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $expenses,
            'features' => $this->featureFlags(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_id' => 'nullable|uuid',
            'wallet_id' => 'nullable|integer',
            'source_type' => 'nullable|in:manual,sms,notification,voice,scan,split,group',
            'source_ref_id' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
            'merchant_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'expense_date' => 'nullable|date',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'paid_by' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'receipt_url' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string|max:50',
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'nullable|string|max:50',
            'next_recurrence_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request, $validator) {
            $data = $validator->validated();
            $user = $request->user();

            $category = $this->resolveCategorySelection(
                $user->id,
                $data['category_id'] ?? null,
                $data['category_name'] ?? null,
                $data['custom_category_name'] ?? null
            );

            $wallet = $this->resolveRequestedWallet($user->id, $data['wallet_id'] ?? null);

            $data['category_id'] = $category['id'];
            $data['category_name'] = $category['name'];
            $data['wallet_id'] = $wallet?->id;
            $data['source_type'] = $data['source_type'] ?? 'manual';
            $data['payment_method'] = $data['payment_method'] ?? $data['transaction_type'];
            $data['currency'] = strtoupper($data['currency'] ?? 'INR');
            $data['expense_date'] = $data['expense_date'] ?? $data['date'];
            $data['status'] = $data['status'] ?? Expense::STATUS_ACTIVE;

            $existing = Expense::query()
                ->where('expense_id', $data['expense_id'] ?? null)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $previousWallet = $existing->wallet_id ? Wallet::find($existing->wallet_id) : null;
                $previousAmount = (float) $existing->amount;
                $previousStatus = (string) $existing->status;

                $existing->update($this->filterExpenseColumns($data));
                $expense = $existing->fresh(['wallet', 'category']);
                app(UnifiedTransactionService::class)->syncExpense($expense, $data['source_type']);

                $this->syncWalletBalanceAfterExpenseChange($expense, $previousWallet, $previousAmount, $previousStatus);

                return response()->json([
                    'success' => true,
                    'message' => 'Expense synced (updated successfully)',
                    'data' => $expense,
                    'features' => $this->featureFlags(),
                ], 201);
            }

            $data['user_id'] = $user->id;
            $data['expense_id'] = $data['expense_id'] ?? (string) Str::uuid();

            $expense = Expense::create($this->filterExpenseColumns($data));
            $expense->load(['wallet', 'category']);
            app(UnifiedTransactionService::class)->syncExpense($expense, $data['source_type']);
            $this->applyWalletBalanceForExpense($expense);

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => $expense->fresh(['wallet', 'category']),
                'features' => $this->featureFlags(),
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->with(['wallet', 'category'])
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense,
            'features' => $this->featureFlags(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        $validated = $request->validate([
            'wallet_id' => 'nullable|integer',
            'source_type' => 'sometimes|in:manual,sms,notification,voice,scan,split,group',
            'source_ref_id' => 'sometimes|nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'category_name' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
            'merchant_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'transaction_type' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'currency' => 'sometimes|nullable|string|size:3',
            'expense_date' => 'sometimes|nullable|date',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'paid_by' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'receipt_url' => 'nullable|string|max:500',
            'metadata' => 'sometimes|nullable|array',
            'status' => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($user, $expense, $validated) {
            $category = $this->resolveCategorySelection(
                $user->id,
                $validated['category_id'] ?? null,
                $validated['category_name'] ?? null,
                $validated['custom_category_name'] ?? null
            );

            $wallet = array_key_exists('wallet_id', $validated)
                ? $this->resolveRequestedWallet($user->id, $validated['wallet_id'])
                : ($expense->wallet_id ? Wallet::find($expense->wallet_id) : null);

            $validated['category_id'] = $category['id'];
            $validated['category_name'] = $category['name'];
            $validated['wallet_id'] = $wallet?->id;
            $validated['source_type'] = $validated['source_type'] ?? $expense->source_type ?? 'manual';
            $validated['payment_method'] = $validated['payment_method'] ?? $validated['transaction_type'];
            $validated['currency'] = strtoupper($validated['currency'] ?? $expense->currency ?? 'INR');
            $validated['expense_date'] = $validated['expense_date'] ?? $validated['date'];
            $validated['status'] = $validated['status'] ?? $expense->status;

            $previousWallet = $expense->wallet_id ? Wallet::find($expense->wallet_id) : null;
            $previousAmount = (float) $expense->amount;
            $previousStatus = (string) $expense->status;

            $expense->update($this->filterExpenseColumns($validated));
            $expense = $expense->fresh(['wallet', 'category']);
            app(UnifiedTransactionService::class)->syncExpense($expense, $validated['source_type']);

            $this->syncWalletBalanceAfterExpenseChange($expense, $previousWallet, $previousAmount, $previousStatus);

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => $expense,
                'features' => $this->featureFlags(),
            ]);
        });
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found',
            ], 404);
        }

        DB::transaction(function () use ($expense) {
            $this->revertExpenseWalletBalance($expense);
            app(UnifiedTransactionService::class)->deleteExpenseTransaction($expense);
            $expense->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }

    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expenses' => 'required|array',
            'expenses.*.expense_id' => 'nullable|uuid',
            'expenses.*.wallet_id' => 'nullable|integer',
            'expenses.*.source_type' => 'nullable|in:manual,sms,notification,voice,scan,split,group',
            'expenses.*.source_ref_id' => 'nullable|integer',
            'expenses.*.category_id' => 'nullable|exists:categories,id',
            'expenses.*.category_name' => 'nullable|string|max:255',
            'expenses.*.custom_category_name' => 'nullable|string|max:255',
            'expenses.*.merchant_name' => 'nullable|string|max:255',
            'expenses.*.payment_method' => 'nullable|string|max:50',
            'expenses.*.transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'expenses.*.description' => 'nullable|string|max:500',
            'expenses.*.amount' => 'required|numeric|min:0',
            'expenses.*.currency' => 'nullable|string|size:3',
            'expenses.*.expense_date' => 'nullable|date',
            'expenses.*.date' => 'required|date',
            'expenses.*.metadata' => 'nullable|array',
            'expenses.*.status' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $expensesData = $validator->validated()['expenses'];

        $result = DB::transaction(function () use ($user, $expensesData) {
            $result = [];

            foreach ($expensesData as $expenseData) {
                $category = $this->resolveCategorySelection(
                    $user->id,
                    $expenseData['category_id'] ?? null,
                    $expenseData['category_name'] ?? null,
                    $expenseData['custom_category_name'] ?? null
                );

                $wallet = $this->resolveRequestedWallet($user->id, $expenseData['wallet_id'] ?? null);

                $expenseData['category_id'] = $category['id'];
                $expenseData['category_name'] = $category['name'];
                $expenseData['wallet_id'] = $wallet?->id;
                $expenseData['source_type'] = $expenseData['source_type'] ?? 'manual';
                $expenseData['payment_method'] = $expenseData['payment_method'] ?? $expenseData['transaction_type'];
                $expenseData['currency'] = strtoupper($expenseData['currency'] ?? 'INR');
                $expenseData['expense_date'] = $expenseData['expense_date'] ?? $expenseData['date'];
                $expenseData['status'] = $expenseData['status'] ?? Expense::STATUS_ACTIVE;

                $existing = Expense::query()
                    ->where('expense_id', $expenseData['expense_id'] ?? null)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing) {
                    $previousWallet = $existing->wallet_id ? Wallet::find($existing->wallet_id) : null;
                    $previousAmount = (float) $existing->amount;
                    $previousStatus = (string) $existing->status;

                    $existing->update($this->filterExpenseColumns($expenseData));
                    $existing = $existing->fresh(['wallet', 'category']);
                    app(UnifiedTransactionService::class)->syncExpense($existing, $expenseData['source_type']);
                    $this->syncWalletBalanceAfterExpenseChange($existing, $previousWallet, $previousAmount, $previousStatus);
                    $result[] = $existing;
                    continue;
                }

                $expenseData['user_id'] = $user->id;
                $expenseData['expense_id'] = $expenseData['expense_id'] ?? (string) Str::uuid();

                $expense = Expense::create($this->filterExpenseColumns($expenseData));
                app(UnifiedTransactionService::class)->syncExpense($expense, $expenseData['source_type']);
                $this->applyWalletBalanceForExpense($expense);
                $result[] = $expense->fresh(['wallet', 'category']);
            }

            return $result;
        });

        return response()->json([
            'success' => true,
            'message' => 'Expenses synced successfully',
            'data' => $result,
            'features' => $this->featureFlags(),
        ], 201);
    }

    public function auto(Request $request, AutoExpenseService $autoExpenseService)
    {
        $result = $autoExpenseService->ingestNotification($request->user()->id, $request->all());

        if ($result['ignored'] ?? false) {
            return response()->json([
                'success' => true,
                'ignored' => true,
                'message' => $result['message'],
            ], 202);
        }

        return response()->json([
            'success' => true,
            'duplicate' => (bool) ($result['duplicate'] ?? false),
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'hash' => $result['hash'] ?? null,
            'features' => $this->featureFlags(),
        ], ($result['duplicate'] ?? false) ? 200 : 201);
    }

    private function featureFlags(): array
    {
        return [
            'enable_payment_source_detection' => (bool) config('features.enable_payment_source_detection', true),
            'enable_auto_tracking' => (bool) config('features.enable_auto_tracking', true),
        ];
    }

    private function resolveRequestedWallet(int $userId, ?int $walletId): ?Wallet
    {
        $wallet = $this->resolveUserWallet($userId, $walletId);

        if ($walletId && !$wallet) {
            abort(404, 'Wallet not found');
        }

        return $wallet;
    }

    private function applyWalletBalanceForExpense(Expense $expense): void
    {
        if (!$this->shouldAffectWalletBalance($expense->status) || !$expense->wallet_id) {
            return;
        }

        $wallet = Wallet::find($expense->wallet_id);
        if ($wallet) {
            $this->applyWalletBalanceChange($wallet, (float) $expense->amount, 'debit');
        }
    }

    private function revertExpenseWalletBalance(Expense $expense): void
    {
        if (!$this->shouldAffectWalletBalance($expense->status) || !$expense->wallet_id) {
            return;
        }

        $wallet = Wallet::find($expense->wallet_id);
        if ($wallet) {
            $this->applyWalletBalanceChange($wallet, (float) $expense->amount, 'credit');
        }
    }

    private function syncWalletBalanceAfterExpenseChange(
        Expense $expense,
        ?Wallet $previousWallet,
        float $previousAmount,
        string $previousStatus
    ): void {
        if ($previousWallet && $this->shouldAffectWalletBalance($previousStatus)) {
            $this->applyWalletBalanceChange($previousWallet, $previousAmount, 'credit');
        }

        $this->applyWalletBalanceForExpense($expense);
    }

    private function shouldAffectWalletBalance(?string $status): bool
    {
        return ($status ?? Expense::STATUS_ACTIVE) === Expense::STATUS_ACTIVE;
    }

    private function filterExpenseColumns(array $attributes): array
    {
        $columns = Schema::hasTable('expenses') ? Schema::getColumnListing('expenses') : [];

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
