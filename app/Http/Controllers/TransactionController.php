<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Get all transactions for logged-in user
    public function index(Request $request)
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->with(['paymentProvider', 'creditCard', 'debitCard', 'expense', 'invoice'])
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    // Store new transaction
    public function store(Request $request)
    {
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
}
