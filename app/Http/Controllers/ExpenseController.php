<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Expense;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum'); // Protect all routes
    }

    /**
     * 🔹 Get all expenses for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $expenses = Expense::where('user_id', $user->id)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * 🔹 Create or update (sync) an expense
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expense_id' => 'nullable|uuid',
            'category' => 'required|string|max:255',
            'transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'paid_by' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'receipt_url' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:50',
            'is_recurring' => 'boolean',
            'recurrence_pattern' => 'nullable|string|max:50',
            'next_recurrence_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        // ✅ Hybrid Sync Logic — prevent duplicates when syncing offline data
        $existing = Expense::where('expense_id', $data['expense_id'] ?? null)->first();

        if ($existing) {
            $existing->update($data);
            $expense = $existing;
            $message = 'Expense synced (updated successfully)';
        } else {
            $data['user_id'] = $user->id;
            $data['expense_id'] = $data['expense_id'] ?? (string) Str::uuid();
            $expense = Expense::create($data);
            $message = 'Expense created successfully';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $expense
        ], 201);
    }

    /**
     * 🔹 Show a single expense (owned by the logged-in user)
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $expense = Expense::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $expense
        ]);
    }

    /**
     * 🔹 Update an expense
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $expense = Expense::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $validated = $request->validate([
            'category' => 'required|string|max:255',
            'transaction_type' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'paid_by' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'receipt_url' => 'nullable|string|max:500',
        ]);

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense
        ]);
    }

    /**
     * 🔹 Delete an expense (soft delete enabled)
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $expense = Expense::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * 🔹 Bulk store for offline sync (insert or update)
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expenses' => 'required|array',
            'expenses.*.expense_id' => 'nullable|uuid',
            'expenses.*.category' => 'required|string|max:255',
            'expenses.*.transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'expenses.*.description' => 'nullable|string|max:500',
            'expenses.*.amount' => 'required|numeric|min:0',
            'expenses.*.date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $expensesData = $validator->validated()['expenses'];
        $createdOrUpdated = [];

        foreach ($expensesData as $expenseData) {
            $existing = Expense::where('expense_id', $expenseData['expense_id'] ?? null)->first();

            if ($existing) {
                $existing->update($expenseData);
                $createdOrUpdated[] = $existing;
            } else {
                $expenseData['user_id'] = $user->id;
                $expenseData['expense_id'] = $expenseData['expense_id'] ?? (string) Str::uuid();
                $createdOrUpdated[] = Expense::create($expenseData);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Expenses synced successfully',
            'data' => $createdOrUpdated
        ], 201);
    }
}
