<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use Illuminate\Http\Request;

class RecurringController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $rows = RecurringTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'wallet_id' => 'nullable|integer',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:expense,income',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
            'payment_method' => 'required|in:upi,credit_card,debit_card,cash,net_banking,other',
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'next_run_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($data['wallet_id'])) {
            $wallet = $this->resolveUserWallet($request->user()->id, (int) $data['wallet_id']);
            if (!$wallet) {
                return response()->json(['message' => 'Wallet not found'], 404);
            }

            $data['wallet_id'] = $wallet->id;
        }

        $recurring = RecurringTransaction::create([
            ...$data,
            'user_id' => $request->user()->id,
            'next_run_date' => $data['next_run_date'] ?? $data['start_date'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $recurring], 201);
    }

    public function show(Request $request, RecurringTransaction $recurring)
    {
        if ($recurring->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $recurring]);
    }

    public function update(Request $request, RecurringTransaction $recurring)
    {
        if ($recurring->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'type' => 'sometimes|in:expense,income',
            'amount' => 'sometimes|numeric|min:0.01',
            'note' => 'sometimes|nullable|string',
            'payment_method' => 'sometimes|in:upi,credit_card,debit_card,cash,net_banking,other',
            'frequency' => 'sometimes|in:daily,weekly,monthly,yearly',
            'end_date' => 'sometimes|nullable|date',
            'next_run_date' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $recurring->update($data);
        return response()->json(['success' => true, 'data' => $recurring->fresh()]);
    }

    public function destroy(Request $request, RecurringTransaction $recurring)
    {
        if ($recurring->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $recurring->delete();
        return response()->json(['success' => true, 'message' => 'Recurring transaction deleted']);
    }
}
