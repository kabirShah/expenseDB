<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MultiExpenseMember;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MultiExpenseMemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Get all multi-expense members for a specific multi-expense
    public function index(Request $request, $multiExpenseId)
    {
        $members = MultiExpenseMember::where('multi_expense_id', $multiExpenseId)
            ->whereHas('multiExpense', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with('user')
            ->get();

        return response()->json(['success' => true, 'data' => $members]);
    }

    // Add a member to multi-expense
    public function store(Request $request, $multiExpenseId)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'amount_owed' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,settled,partially_paid',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Verify the multi-expense belongs to the user
        $multiExpense = \App\Models\MultiExpense::where('id', $multiExpenseId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Multi-expense not found'], 404);
        }

        $data = $validator->validated();
        $data['multi_expense_id'] = $multiExpenseId;
        $data['multi_expense_member_id'] = Str::uuid();

        $member = MultiExpenseMember::create($data);

        return response()->json(['success' => true, 'message' => 'Member added to multi-expense', 'data' => $member], 201);
    }

    // Update a member's details
    public function update(Request $request, $multiExpenseId, $memberId)
    {
        $member = MultiExpenseMember::where('id', $memberId)
            ->where('multi_expense_id', $multiExpenseId)
            ->whereHas('multiExpense', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }

        $validated = $request->validate([
            'amount_owed' => 'required|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:pending,settled,partially_paid',
        ]);

        $member->update($validated);

        return response()->json(['success' => true, 'message' => 'Member updated', 'data' => $member]);
    }

    // Remove a member from multi-expense
    public function destroy(Request $request, $multiExpenseId, $memberId)
    {
        $member = MultiExpenseMember::where('id', $memberId)
            ->where('multi_expense_id', $multiExpenseId)
            ->whereHas('multiExpense', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }

        $member->delete();
        return response()->json(['success' => true, 'message' => 'Member removed from multi-expense']);
    }

    // Settle a member's payment
    public function settle(Request $request, $multiExpenseId, $memberId)
    {
        $member = MultiExpenseMember::where('id', $memberId)
            ->where('multi_expense_id', $multiExpenseId)
            ->whereHas('multiExpense', function($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
        ]);

        $member->amount_paid += $validated['amount_paid'];
        
        if ($member->amount_paid >= $member->amount_owed) {
            $member->status = 'settled';
        } elseif ($member->amount_paid > 0) {
            $member->status = 'partially_paid';
        }

        $member->save();

        return response()->json(['success' => true, 'message' => 'Payment settled', 'data' => $member]);
    }
}
