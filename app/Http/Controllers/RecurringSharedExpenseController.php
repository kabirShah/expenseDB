<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Models\RecurringSharedExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringSharedExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $query = RecurringSharedExpense::query()
            ->with('group:id,name,type,currency')
            ->where('created_by', $request->user()->id);

        if ($request->filled('group_id')) {
            $this->authorizeGroup($request, (int) $request->input('group_id'));
            $query->orWhere('group_id', $request->integer('group_id'));
        }

        return response()->json(['success' => true, 'data' => $query->latest()->paginate($request->integer('per_page', 30))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'nullable|integer|exists:expense_groups,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'wallet_id' => 'nullable|integer|exists:wallets,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'frequency' => 'required|in:daily,weekly,monthly,yearly',
            'split_type' => 'required|in:equal,exact,percentage,shares,share,custom,item,itemized,item_based',
            'payers' => 'required|array|min:1',
            'participants' => 'required|array|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'auto_generate' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        if (!empty($data['group_id'])) {
            $this->authorizeGroup($request, (int) $data['group_id']);
        }

        $recurring = RecurringSharedExpense::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'next_run_at' => $data['start_date'],
            'status' => 'active',
        ]));

        return response()->json(['success' => true, 'data' => $recurring->load('group:id,name,type,currency')], 201);
    }

    public function update(Request $request, RecurringSharedExpense $recurringSharedExpense): JsonResponse
    {
        abort_unless((int) $recurringSharedExpense->created_by === (int) $request->user()->id, 403, 'Unauthorized');

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0.01',
            'frequency' => 'sometimes|in:daily,weekly,monthly,yearly',
            'split_type' => 'sometimes|in:equal,exact,percentage,shares,share,custom,item,itemized,item_based',
            'payers' => 'sometimes|array|min:1',
            'participants' => 'sometimes|array|min:1',
            'end_date' => 'nullable|date',
            'status' => 'sometimes|in:active,paused,archived',
            'auto_generate' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        $recurringSharedExpense->update($data);

        return response()->json(['success' => true, 'data' => $recurringSharedExpense->fresh('group:id,name,type,currency')]);
    }

    public function destroy(Request $request, RecurringSharedExpense $recurringSharedExpense): JsonResponse
    {
        abort_unless((int) $recurringSharedExpense->created_by === (int) $request->user()->id, 403, 'Unauthorized');
        $recurringSharedExpense->delete();

        return response()->json(['success' => true, 'message' => 'Recurring shared expense deleted']);
    }

    private function authorizeGroup(Request $request, int $groupId): ExpenseGroup
    {
        $group = ExpenseGroup::query()->findOrFail($groupId);
        abort_unless(
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->user()->id)->exists(),
            403,
            'Unauthorized'
        );

        return $group;
    }
}
