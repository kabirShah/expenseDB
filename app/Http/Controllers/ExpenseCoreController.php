<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\ExpenseService;
use App\Services\AutoSplitterService;
use App\Services\ConfidenceScoreService;
use App\Services\CurrencyService;
use Illuminate\Support\Str;

class ExpenseCoreController extends Controller
{
    protected $expenseService;
    protected $autoSplitterService;
    protected $confidenceScoreService;
    protected $currencyService;

    public function __construct(
        ExpenseService $expenseService,
        AutoSplitterService $autoSplitterService,
        ConfidenceScoreService $confidenceScoreService,
        CurrencyService $currencyService
    ) {
        $this->middleware('auth:sanctum');
        $this->expenseService = $expenseService;
        $this->autoSplitterService = $autoSplitterService;
        $this->confidenceScoreService = $confidenceScoreService;
        $this->currencyService = $currencyService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $groupId = $request->query('group_id');
        $perPage = $request->query('per_page', 15);

        if ($groupId) {
            $expenses = $this->expenseService->getExpensesByGroup($groupId, $perPage);
        } else {
            $expenses = $this->expenseService->getExpensesByPayer($request->user()->id, $perPage);
        }

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'expense_date' => 'required|date',
            'group_id' => 'required|exists:groups,id',
            'split_type' => 'required|in:equal,exact,percentage,ratio,income-based',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.amount' => 'required_if:split_type,exact|numeric|min:0',
            'participants.*.percentage' => 'required_if:split_type,percentage|numeric|min:0|max:100',
            'participants.*.ratio' => 'required_if:split_type,ratio|integer|min:1',
            'participants.*.share_details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['payer_id'] = $request->user()->id;
            $data['expense_id'] = Str::uuid();

            // Convert currency if needed
            $data = $this->currencyService->convertExpenseToBaseCurrency($data);

            $expense = $this->expenseService->createExpense($data);

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => $expense
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $expense = $this->expenseService->expenseRepository->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        // Check if user has access to this expense
        if ($expense->payer_id !== $request->user()->id &&
            !$expense->group->isMember($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Convert to user's preferred currency if specified
        $userCurrency = $request->query('currency');
        if ($userCurrency) {
            $expenseData = $expense->toArray();
            $expenseData = $this->currencyService->convertExpenseToUserCurrency($expenseData, $userCurrency);
            $expense = (object) $expenseData;
        }

        return response()->json([
            'success' => true,
            'data' => $expense->load('expenseShares.user', 'group', 'payer')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|size:3',
            'expense_date' => 'sometimes|required|date',
            'split_type' => 'sometimes|required|in:equal,exact,percentage,ratio,income-based',
            'participants' => 'sometimes|required|array|min:1',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.amount' => 'required_if:split_type,exact|numeric|min:0',
            'participants.*.percentage' => 'required_if:split_type,percentage|numeric|min:0|max:100',
            'participants.*.ratio' => 'required_if:split_type,ratio|integer|min:1',
            'participants.*.share_details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Convert currency if needed
            if (isset($data['currency'])) {
                $data = $this->currencyService->convertExpenseToBaseCurrency($data);
            }

            $expense = $this->expenseService->updateExpense($id, $data);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => $expense
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $expense = $this->expenseService->expenseRepository->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        // Check if user is the payer or group admin
        if ($expense->payer_id !== $request->user()->id &&
            !$expense->group->isAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $result = $this->expenseService->deleteExpense($id);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete expense'
        ], 500);
    }

    /**
     * Get auto-split suggestions for a group
     */
    public function getAutoSplitSuggestions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $suggestions = $this->autoSplitterService->getSuggestions(
            $request->group_id,
            $request->description
        );

        return response()->json([
            'success' => true,
            'data' => $suggestions
        ]);
    }

    /**
     * Check for duplicate expenses
     */
    public function checkDuplicates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $duplicates = $this->confidenceScoreService->findDuplicates(
            $request->group_id,
            $request->amount,
            $request->description,
            $request->date
        );

        return response()->json([
            'success' => true,
            'data' => $duplicates,
            'has_duplicates' => $duplicates->isNotEmpty()
        ]);
    }

    /**
     * Get expense analytics
     */
    public function analytics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|exists:groups,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $analytics = $this->expenseService->getExpenseAnalytics(
            $request->group_id,
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Settle an expense
     */
    public function settle(Request $request, string $id)
    {
        $expense = $this->expenseService->expenseRepository->find($id);

        if (!$expense) {
            return response()->json([
                'success' => false,
                'message' => 'Expense not found'
            ], 404);
        }

        // Check if user is the payer or group admin
        if ($expense->payer_id !== $request->user()->id &&
            !$expense->group->isAdmin($request->user()->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $result = $this->expenseService->settleExpense($id);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Expense settled successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to settle expense'
        ], 500);
    }
}
