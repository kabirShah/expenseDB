<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Models\Settlement;
use App\Services\GroupExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupExpenseController extends Controller
{
    public function __construct(private readonly GroupExpenseService $groupExpenseService)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request, ?ExpenseGroup $expenseGroup = null)
    {
        $groupId = $expenseGroup?->id ?? $request->integer('group_id');
        $group = $expenseGroup ?: ExpenseGroup::query()->findOrFail($groupId);

        abort_unless(
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->user()->id)->exists(),
            403,
            'Unauthorized'
        );

        $expenses = Expense::query()
            ->where('group_id', $group->id)
            ->with(['splits.user', 'group'])
            ->latest('expense_date')
            ->get();

        return response()->json(['success' => true, 'data' => $expenses]);
    }

    public function store(Request $request, ?ExpenseGroup $expenseGroup = null)
    {
        $group = $expenseGroup ?: ExpenseGroup::query()->findOrFail($request->input('group_id'));

        abort_unless(
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->user()->id)->exists(),
            403,
            'Unauthorized'
        );

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'expense_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'category_name' => 'nullable|string|max:255',
            'merchant_name' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'split_type' => 'required|in:equal,exact,percentage,shares,share,custom,item,itemized,item_based',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|exists:users,id',
            'participants.*.amount' => 'nullable|numeric|min:0',
            'participants.*.percentage' => 'nullable|numeric|min:0|max:100',
            'participants.*.shares' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.amount' => 'required_with:items|numeric|min:0.01',
            'items.*.user_ids' => 'required_with:items|array|min:1',
            'items.*.user_ids.*' => 'integer|exists:users,id',
            'payers' => 'required|array|min:1',
            'payers.*.user_id' => 'required|exists:users,id',
            'payers.*.amount_paid' => 'required|numeric|min:0',
            'linked_transaction_id' => 'nullable|integer|exists:transactions,id',
            'transaction_reference' => 'nullable|string|max:191',
            'bank_reference' => 'nullable|string|max:191',
        ]);

        $expense = $this->groupExpenseService->createGroupExpense($group, $request->user()->id, $data);

        return response()->json(['success' => true, 'data' => $expense], 201);
    }

    public function update(Request $request, ExpenseGroup $expenseGroup, Expense $expense)
    {
        abort(501, 'Group expense update is not implemented yet.');
    }

    public function destroy(Request $request, ExpenseGroup $expenseGroup, Expense $expense)
    {
        abort(501, 'Group expense delete is not implemented yet.');
    }

    public function settle(Request $request, ExpenseGroup $expenseGroup)
    {
        abort_unless(
            GroupMember::query()->where('group_id', $expenseGroup->id)->where('user_id', $request->user()->id)->exists(),
            403,
            'Unauthorized'
        );

        $data = $request->validate([
            'from_user_id' => 'required|integer|exists:users,id|different:to_user_id',
            'to_user_id' => 'required|integer|exists:users,id',
            'related_expense_id' => 'nullable|exists:expenses,id',
            'amount' => 'required|numeric|min:0.01',
            'settled_amount' => 'nullable|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'reference_id' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
        ]);

        foreach ([$data['from_user_id'], $data['to_user_id'], $request->user()->id] as $userId) {
            abort_unless(
                GroupMember::query()->where('group_id', $expenseGroup->id)->where('user_id', $userId)->exists(),
                422,
                'Settlement users must belong to the group.'
            );
        }

        if (isset($data['related_expense_id'])) {
            $groupExpenseBelongsToGroup = Expense::query()
                ->where('id', $data['related_expense_id'])
                ->where('group_id', $expenseGroup->id)
                ->exists();

            abort_unless($groupExpenseBelongsToGroup, 422, 'Related expense must belong to the selected group.');
        }

        $settlement = DB::transaction(function () use ($request, $expenseGroup, $data) {
            $settledAmount = round((float) ($data['settled_amount'] ?? $data['amount']), 2);
            $amount = round((float) $data['amount'], 2);

            return Settlement::create([
                'group_id' => $expenseGroup->id,
                'from_user_id' => $data['from_user_id'],
                'to_user_id' => $data['to_user_id'],
                'related_expense_id' => $data['related_expense_id'] ?? null,
                'amount' => $amount,
                'settled_amount' => $settledAmount,
                'status' => $settledAmount >= $amount ? 'settled' : 'partial',
                'method' => $data['method'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'future_payment_rails' => ['upi', 'qr', 'bank'],
                ],
                'settled_at' => now(),
                'recorded_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $settlement->load(['group', 'fromUser', 'toUser', 'expense']),
        ], 201);
    }
}
