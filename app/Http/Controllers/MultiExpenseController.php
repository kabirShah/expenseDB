<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MultiExpense;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MultiExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all multi-expenses for logged-in user
     */
    public function index(Request $request)
    {
        $multiExpenses = MultiExpense::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $multiExpenses]);
    }

    /**
     * Create a new multi-expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Calculate total amount by parsing the lines like "200₹ Groceries"
        $totalAmount = $this->calculateTotal($request->description);

        $multiExpense = MultiExpense::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'total_amount' => $totalAmount,
            'description' => $request->description,
            'category' => $request->category ?? 'Miscellaneous',
            'multi_expense_id' => Str::uuid(),
        ]);

        return response()->json(['success' => true, 'message' => 'Multi-expense created successfully', 'data' => $multiExpense], 201);
    }

    /**
     * Get single expense by ID
     */
    public function show(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $multiExpense]);
    }

    /**
     * Update an existing expense
     */
    public function update(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'nullable|string|max:255',
        ]);

        // Recalculate total again based on updated lines
        $totalAmount = $this->calculateTotal($validated['description']);

        $multiExpense->update(array_merge($validated, ['total_amount' => $totalAmount]));

        return response()->json(['success' => true, 'message' => 'Multi-expense updated successfully', 'data' => $multiExpense]);
    }

    /**
     * Delete an expense
     */
    public function destroy(Request $request, $id)
    {
        $multiExpense = MultiExpense::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$multiExpense) {
            return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
        }

        $multiExpense->delete();
        return response()->json(['success' => true, 'message' => 'Multi-expense deleted successfully']);
    }

    /**
     * Helper to calculate total from text like:
     * 200₹ Groceries
     * 500₹ Transport
     */
    private function calculateTotal(string $description): float
    {
        $total = 0;
        $lines = array_filter(array_map('trim', explode("\n", $description)));

        foreach ($lines as $line) {
            // Match numbers before ₹ or at start of line
            if (preg_match('/(\d+)/', $line, $match)) {
                $total += (float) $match[1];
            }
        }

        return $total;
    }
}
