<?php

namespace App\Http\Controllers;

use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\AuditLog;
use App\Services\SplitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpenseSplitController extends Controller
{
    protected $splitService;

    public function __construct(SplitService $splitService)
    {
        $this->splitService = $splitService;
    }

    /**
     * Display a listing of expense splits for a group
     */
    public function index(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $expenseSplits = $group->expenseSplits()
            ->active()
            ->with(['payer:id,name,email'])
            ->orderBy('expense_date', 'desc')
            ->paginate(20);

        $expenseSplits->getCollection()->transform(function ($expense) use ($userId) {
            $userShare = $expense->getUserShare($userId);
            return [
                'id' => $expense->id,
                'expense_split_id' => $expense->expense_split_id,
                'title' => $expense->title,
                'description' => $expense->description,
                'total_amount' => $expense->total_amount,
                'split_type' => $expense->split_type,
                'expense_date' => $expense->expense_date,
                'category' => $expense->category,
                'paid_by' => $expense->payer->name,
                'user_share' => $userShare ? $userShare['amount_owed'] : 0,
                'user_paid' => $userShare ? $userShare['amount_paid'] : 0,
                'user_balance' => $userShare ? $expense->getBalanceForUser($userId) : 0,
                'status' => $userShare ? $userShare['status'] : 'not_involved',
                'is_settled' => $expense->isSettled(),
                'created_at' => $expense->created_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $expenseSplits
        ]);
    }

    /**
     * Store a newly created expense split
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0.01',
            'split_type' => 'required|in:equal,exact,percentage',
            'expense_date' => 'nullable|date',
            'category' => 'nullable|string|max:100',
            'receipt_images' => 'nullable|array',
            'exact_shares' => 'required_if:split_type,exact|array',
            'exact_shares.*.user_id' => 'required|exists:users,id',
            'exact_shares.*.amount' => 'required|numeric|min:0',
            'percentage_shares' => 'required_if:split_type,percentage|array',
            'percentage_shares.*.user_id' => 'required|exists:users,id',
            'percentage_shares.*.percentage' => 'required|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate split data
        $validationErrors = $this->splitService->validateSplitData($request->all());
        if (!empty($validationErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Split validation failed',
                'errors' => $validationErrors
            ], 422);
        }

        DB::beginTransaction();
        try {
            $expenseData = array_merge($request->all(), [
                'group_id' => $group->id,
                'paid_by' => $userId
            ]);

            $expenseSplit = $this->splitService->createExpenseSplit($expenseData);

            // Log creation
            AuditLog::log($userId, 'create', 'expense_split', $expenseSplit->id, "Created expense split '{$expenseSplit->title}' in group '{$group->name}'");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense split created successfully',
                'data' => $expenseSplit->load('payer:id,name,email')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create expense split',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified expense split
     */
    public function show(Group $group, ExpenseSplit $expenseSplit): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $expenseSplit->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to expense split'
            ], 403);
        }

        $expenseSplit->load(['payer:id,name,email', 'group.members.user:id,name,email']);

        $userShare = $expenseSplit->getUserShare($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $expenseSplit->id,
                'expense_split_id' => $expenseSplit->expense_split_id,
                'title' => $expenseSplit->title,
                'description' => $expenseSplit->description,
                'total_amount' => $expenseSplit->total_amount,
                'split_type' => $expenseSplit->split_type,
                'split_details' => $expenseSplit->split_details,
                'expense_date' => $expenseSplit->expense_date,
                'category' => $expenseSplit->category,
                'receipt_images' => $expenseSplit->receipt_images,
                'paid_by' => $expenseSplit->payer->name,
                'group_name' => $group->name,
                'user_share' => $userShare,
                'user_balance' => $userShare ? $expenseSplit->getBalanceForUser($userId) : 0,
                'is_settled' => $expenseSplit->isSettled(),
                'unsettled_amount' => $expenseSplit->getUnsettledAmount(),
                'created_at' => $expenseSplit->created_at,
                'updated_at' => $expenseSplit->updated_at
            ]
        ]);
    }

    /**
     * Update the specified expense split
     */
    public function update(Request $request, Group $group, ExpenseSplit $expenseSplit): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $expenseSplit->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to expense split'
            ], 403);
        }

        if ($expenseSplit->paid_by !== $userId && !$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the expense creator or group admin can update this expense'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expense_date' => 'nullable|date',
            'category' => 'nullable|string|max:100',
            'receipt_images' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $expenseSplit->only(['title', 'description', 'expense_date', 'category', 'receipt_images']);

        $expenseSplit->update($request->only(['title', 'description', 'expense_date', 'category', 'receipt_images']));

        // Log update
        AuditLog::log($userId, 'update', 'expense_split', $expenseSplit->id, "Updated expense split '{$expenseSplit->title}'", $oldValues, $expenseSplit->only(['title', 'description', 'expense_date', 'category', 'receipt_images']));

        return response()->json([
            'success' => true,
            'message' => 'Expense split updated successfully',
            'data' => $expenseSplit
        ]);
    }

    /**
     * Remove the specified expense split
     */
    public function destroy(Group $group, ExpenseSplit $expenseSplit): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $expenseSplit->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to expense split'
            ], 403);
        }

        if ($expenseSplit->paid_by !== $userId && !$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the expense creator or group admin can delete this expense'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Log deletion
            AuditLog::log($userId, 'delete', 'expense_split', $expenseSplit->id, "Deleted expense split '{$expenseSplit->title}' from group '{$group->name}'");

            $expenseSplit->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Expense split deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete expense split',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user payment for an expense split
     */
    public function updatePayment(Request $request, Group $group, ExpenseSplit $expenseSplit): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $expenseSplit->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to expense split'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userShare = $expenseSplit->getUserShare($userId);
        if (!$userShare) {
            return response()->json([
                'success' => false,
                'message' => 'User is not involved in this expense split'
            ], 404);
        }

        if ($request->amount > $userShare['amount_owed']) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount cannot exceed owed amount'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldAmount = $userShare['amount_paid'];
            $this->splitService->updateUserPayment($expenseSplit, $userId, $request->amount);

            // Log payment update
            AuditLog::log($userId, 'update', 'expense_split', $expenseSplit->id, "Updated payment for expense '{$expenseSplit->title}' from {$oldAmount} to {$request->amount}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'data' => [
                    'expense_id' => $expenseSplit->id,
                    'user_id' => $userId,
                    'amount_paid' => $request->amount,
                    'amount_owed' => $userShare['amount_owed'],
                    'balance' => $request->amount - $userShare['amount_owed']
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
