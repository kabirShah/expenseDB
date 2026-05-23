<?php

namespace App\Http\Controllers;

use App\Models\MultiExpense;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\UnifiedTransactionService;

class MultiExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 10), 100));
        $baseQuery = MultiExpense::query()
            ->where('user_id', $request->user()->id);

        $total = (float) (clone $baseQuery)->sum('total_amount');
        $multiExpenses = $baseQuery
            ->with(['wallet:id,name,balance'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'total' => $total,
            'data' => $multiExpenses,
        ]);
    }

    public function total(Request $request)
    {
        $total = (float) MultiExpense::query()
            ->where('user_id', $request->user()->id)
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'total_expense' => $total,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'wallet_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $validator) {
            $wallet = $this->resolveRequestedWallet($request->user()->id, $validator->validated()['wallet_id'] ?? null);
            $totalAmount = $this->calculateTotal($request->description);

            $multiExpense = MultiExpense::create([
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet?->id,
                'title' => $request->title,
                'total_amount' => $totalAmount,
                'description' => $request->description,
                'category' => $request->input('custom_category_name')
                    ?: $request->category
                    ?: 'Miscellaneous',
                'multi_expense_id' => Str::uuid(),
            ]);

            if ($wallet) {
                $this->applyWalletBalanceChange($wallet, $totalAmount, 'debit');
            }
            app(UnifiedTransactionService::class)->syncMultiExpense($multiExpense);

            return response()->json([
                'success' => true,
                'message' => 'Multi-expense created successfully',
                'data' => $multiExpense->fresh(['wallet']),
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $multiExpense = MultiExpense::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['wallet'])
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $multiExpense]);
    }

    public function update(Request $request, $id)
    {
        $multiExpense = MultiExpense::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        $validated = $request->validate([
            'wallet_id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
            'custom_category_name' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $multiExpense, $validated) {
            $previousWallet = $multiExpense->wallet_id ? Wallet::find($multiExpense->wallet_id) : null;
            $previousAmount = (float) $multiExpense->total_amount;

            $wallet = array_key_exists('wallet_id', $validated)
                ? $this->resolveRequestedWallet($request->user()->id, $validated['wallet_id'])
                : $previousWallet;

            $totalAmount = $this->calculateTotal($validated['description']);

            if ($previousWallet) {
                $this->applyWalletBalanceChange($previousWallet, $previousAmount, 'credit');
            }

            $multiExpense->update([
                'wallet_id' => $wallet?->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['custom_category_name'] ?? $validated['category'] ?? $multiExpense->category,
                'total_amount' => $totalAmount,
            ]);

            if ($wallet) {
                $this->applyWalletBalanceChange($wallet, $totalAmount, 'debit');
            }
            app(UnifiedTransactionService::class)->syncMultiExpense($multiExpense->fresh());

            return response()->json([
                'success' => true,
                'message' => 'Multi-expense updated successfully',
                'data' => $multiExpense->fresh(['wallet']),
            ]);
        });
    }

    public function destroy(Request $request, $id)
    {
        $multiExpense = MultiExpense::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        DB::transaction(function () use ($multiExpense) {
            if ($multiExpense->wallet_id) {
                $wallet = Wallet::find($multiExpense->wallet_id);
                if ($wallet) {
                    $this->applyWalletBalanceChange($wallet, (float) $multiExpense->total_amount, 'credit');
                }
            }

            app(UnifiedTransactionService::class)->deleteMultiExpenseTransactions($multiExpense);
            $multiExpense->delete();
        });

        return response()->json(['success' => true, 'message' => 'Multi-expense deleted successfully']);
    }

    private function resolveRequestedWallet(int $userId, ?int $walletId): ?Wallet
    {
        $wallet = $this->resolveUserWallet($userId, $walletId);

        if ($walletId && !$wallet) {
            abort(404, 'Wallet not found');
        }

        return $wallet;
    }

    private function calculateTotal(string $description): float
    {
        $total = 0.0;

        if (preg_match_all('/(?:₹|rs\.?|inr)\s*([0-9,]+(?:\.[0-9]{1,2})?)/i', $description, $matches)) {
            foreach ($matches[1] as $amount) {
                $total += (float) str_replace(',', '', $amount);
            }

            return round($total, 2);
        }

        if (preg_match_all('/\b([0-9,]+(?:\.[0-9]{1,2})?)\b/', $description, $matches)) {
            foreach ($matches[1] as $amount) {
                $total += (float) str_replace(',', '', $amount);
            }
        }

        return round($total, 2);
    }
}
