<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MultiExpense;
use App\Models\MultiExpenseMember;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MultiExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Get all multi-expenses for logged-in user
    public function index(Request $request)
    {
        $multiExpenses = MultiExpense::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $multiExpenses]);
    }

    // Store new multi-expense
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Parse description to calculate total_amount
        $lines = array_filter(array_map('trim', explode("\n", $request->description)));
        $totalAmount = 0;
        $parsedDescriptions = [];
        foreach ($lines as $line) {
            $parsed = $this->parseExpenseString($line);
            if ($parsed) {
                $totalAmount += $parsed['amount'];
                $parsedDescriptions[] = $parsed['description'];
            }
        }

        $data = [
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'total_amount' => $totalAmount,
            'description' => implode(', ', $parsedDescriptions),
            'category' => $request->category ?? 'Shared',
            'split_type' => 'equal',
            'multi_expense_id' => Str::uuid(),
        ];

        $multiExpense = MultiExpense::create($data);

        return response()->json(['success' => true, 'message' => 'Multi-expense created', 'data' => $multiExpense], 201);
    }

    // Show single multi-expense
    public function show(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('multiExpenseMembers.user')
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Multi-expense not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $multiExpense]);
    }

    // Update multi-expense
    public function update(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Multi-expense not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'split_type' => 'required|in:equal',
        ]);

        $multiExpense->update($validated);

        return response()->json(['success' => true, 'message' => 'Multi-expense updated', 'data' => $multiExpense]);
    }

    // Delete multi-expense
    public function destroy(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Multi-expense not found'], 404);
        }

        $multiExpense->delete();
        return response()->json(['success' => true, 'message' => 'Multi-expense deleted']);
    }

    // Settle a member's share
    public function settleMember(Request $request, $id, $memberId)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Multi-expense not found'], 404);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
        ]);

        $member = MultiExpenseMember::where('multi_expense_id', $id)
            ->where('user_id', $memberId)
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }

        $member->amount_paid += $validated['amount_paid'];
        $member->status = $member->amount_paid >= $member->amount_owed ? 'settled' : 'pending';
        $member->save();

        return response()->json(['success' => true, 'message' => 'Member settled', 'data' => $multiExpense->load('multiExpenseMembers.user')]);
    }

    private function parseExpenseString($string)
    {
        if (preg_match('/^(\d+)\₹\s*(.+)$/', $string, $matches)) {
            return [
                'amount' => (float) $matches[1],
                'description' => trim($matches[2])
            ];
        }
        return null;
    }
}
