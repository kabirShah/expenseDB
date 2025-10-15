<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use App\Models\Group;
use App\Models\ExpenseSplit;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettlementController extends Controller
{
    /**
     * Display a listing of settlements for a group
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

        $settlements = $group->settlements()
            ->with(['payer:id,name,email', 'payee:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $settlements->getCollection()->transform(function ($settlement) {
            return [
                'id' => $settlement->id,
                'settlement_id' => $settlement->settlement_id,
                'amount' => $settlement->amount,
                'description' => $settlement->description,
                'status' => $settlement->status,
                'paid_by' => $settlement->payer->name,
                'paid_to' => $settlement->payee->name,
                'settled_at' => $settlement->settled_at,
                'related_expenses' => $settlement->getRelatedExpenseIds(),
                'created_at' => $settlement->created_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $settlements
        ]);
    }

    /**
     * Store a newly created settlement
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
            'paid_to' => 'required|exists:users,id|different:paid_by',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'related_expenses' => 'nullable|array',
            'related_expenses.*' => 'exists:expense_splits,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate that paid_to user is a member of the group
        if (!$group->isMember($request->paid_to)) {
            return response()->json([
                'success' => false,
                'message' => 'The recipient is not a member of this group'
            ], 422);
        }

        // Validate related expenses belong to this group
        if ($request->related_expenses) {
            $invalidExpenses = ExpenseSplit::whereIn('id', $request->related_expenses)
                ->where('group_id', '!=', $group->id)
                ->exists();

            if ($invalidExpenses) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some related expenses do not belong to this group'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $settlement = Settlement::create([
                'group_id' => $group->id,
                'paid_by' => $userId,
                'paid_to' => $request->paid_to,
                'amount' => $request->amount,
                'description' => $request->description ?? 'Settlement payment',
                'status' => 'completed',
                'settled_at' => now(),
                'related_expenses' => $request->related_expenses ?? []
            ]);

            // Log settlement
            AuditLog::log($userId, 'settle', 'settlement', $settlement->id, "Created settlement of {$request->amount} in group '{$group->name}'");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settlement recorded successfully',
                'data' => $settlement->load(['payer:id,name,email', 'payee:id,name,email'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record settlement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified settlement
     */
    public function show(Group $group, Settlement $settlement): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $settlement->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to settlement'
            ], 403);
        }

        $settlement->load(['payer:id,name,email', 'payee:id,name,email', 'group']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $settlement->id,
                'settlement_id' => $settlement->settlement_id,
                'amount' => $settlement->amount,
                'description' => $settlement->description,
                'status' => $settlement->status,
                'paid_by' => $settlement->payer->name,
                'paid_to' => $settlement->payee->name,
                'settled_at' => $settlement->settled_at,
                'related_expenses' => $settlement->getRelatedExpenses()->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'title' => $expense->title,
                        'total_amount' => $expense->total_amount,
                        'expense_date' => $expense->expense_date
                    ];
                }),
                'group_name' => $group->name,
                'created_at' => $settlement->created_at,
                'updated_at' => $settlement->updated_at
            ]
        ]);
    }

    /**
     * Update the specified settlement
     */
    public function update(Request $request, Group $group, Settlement $settlement): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $settlement->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to settlement'
            ], 403);
        }

        if ($settlement->paid_by !== $userId && !$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the settlement creator or group admin can update this settlement'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'description' => 'sometimes|required|string|max:500',
            'related_expenses' => 'nullable|array',
            'related_expenses.*' => 'exists:expense_splits,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate related expenses belong to this group
        if ($request->related_expenses) {
            $invalidExpenses = ExpenseSplit::whereIn('id', $request->related_expenses)
                ->where('group_id', '!=', $group->id)
                ->exists();

            if ($invalidExpenses) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some related expenses do not belong to this group'
                ], 422);
            }
        }

        $oldValues = $settlement->only(['description', 'related_expenses']);

        $settlement->update($request->only(['description', 'related_expenses']));

        // Log update
        AuditLog::log($userId, 'update', 'settlement', $settlement->id, "Updated settlement in group '{$group->name}'", $oldValues, $settlement->only(['description', 'related_expenses']));

        return response()->json([
            'success' => true,
            'message' => 'Settlement updated successfully',
            'data' => $settlement
        ]);
    }

    /**
     * Remove the specified settlement
     */
    public function destroy(Group $group, Settlement $settlement): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId) || $settlement->group_id !== $group->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to settlement'
            ], 403);
        }

        if ($settlement->paid_by !== $userId && !$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the settlement creator or group admin can delete this settlement'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Log deletion
            AuditLog::log($userId, 'delete', 'settlement', $settlement->id, "Deleted settlement of {$settlement->amount} from group '{$group->name}'");

            $settlement->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settlement deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete settlement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settlement suggestions for the group
     */
    public function getSuggestions(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        try {
            $reportService = app(\App\Services\ReportService::class);
            $suggestions = $reportService->getSettlementSuggestions($group->id);

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate settlement suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
