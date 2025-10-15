<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Expense;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum'); // Protect all routes
    }

    // Get all expenses for logged-in user
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

    // Store a new expense
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'description' => 'required|string|min:3',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $data = $validator->validated();
        $data['user_id'] = $user->id;
        $data['expense_id'] = Str::uuid();

        $expense = Expense::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense
        ], 201);
    }
    
// Show a single expense
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

    public function update(Request $request, $id)
    {
        $user =$request->user();

        $expense = Expense::where('id',$id)
        ->where('user_id',$user->id)
        ->first();

         if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }
       $validated = $request->validate([
            'category' => 'required|string|max:255',
            'transaction_type' => 'required|string',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense
        ]);
    }

    public function destroy(Request $request, $id)
    {
         $user = $request->user();
        // Find the expense by ID
        $expense = Expense::where('id', $id)
        ->where('user_id',$user->id)
        ->first();

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }
        // Delete the expense
        $expense->delete();
        return response()->json(['success' => true,'message' => 'Expense deleted successfully']);
    }

    // Bulk store expenses
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'expenses' => 'required|array',
            'expenses.*.category' => 'required|string',
            'expenses.*.transaction_type' => 'required|in:Cash,Credit Card,Debit Card,UPI,Bank Transfer,Mobile Wallet',
            'expenses.*.description' => 'required|string|min:3',
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

        $createdExpenses = [];
        foreach ($expensesData as $expenseData) {
            $expenseData['user_id'] = $user->id;
            $expenseData['expense_id'] = Str::uuid();
            $createdExpenses[] = Expense::create($expenseData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Expenses created successfully',
            'data' => $createdExpenses
        ], 201);
    }

}
