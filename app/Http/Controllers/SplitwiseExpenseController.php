<?php

namespace App\Http\Controllers;

use App\Models\SplitwiseExpense;
use App\Models\SplitwiseGroup;
use App\Models\SplitwiseGroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SplitwiseExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'required|integer|exists:splitwise_groups,id',
        ]);

        $group = $this->findAccessibleGroup($request->user()->id, (int) $data['group_id']);

        $expenses = $group->expenses()
            ->with(['paidByMember', 'splits.member'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'required|integer|exists:splitwise_groups,id',
            'paid_by_member_id' => 'required|integer|exists:splitwise_group_members,id',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'expense_date' => 'required|date',
            'splits' => 'required|array|min:1',
            'splits.*.member_id' => 'required|integer|exists:splitwise_group_members,id',
            'splits.*.amount_owed' => 'required|numeric|min:0',
        ]);

        $group = $this->findAccessibleGroup($request->user()->id, (int) $data['group_id']);
        $memberIds = $group->members()->pluck('id')->all();

        abort_unless(in_array((int) $data['paid_by_member_id'], $memberIds, true), 422, 'Paid by member must belong to the group.');

        foreach ($data['splits'] as $split) {
            abort_unless(in_array((int) $split['member_id'], $memberIds, true), 422, 'Split member must belong to the group.');
        }

        $splitTotal = round(collect($data['splits'])->sum(fn ($split) => (float) $split['amount_owed']), 2);
        abort_unless(abs($splitTotal - round((float) $data['amount'], 2)) < 0.01, 422, 'Split total must equal expense amount.');

        $expense = DB::transaction(function () use ($request, $group, $data) {
            $expense = SplitwiseExpense::create([
                'splitwise_group_id' => $group->id,
                'paid_by_member_id' => $data['paid_by_member_id'],
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'expense_date' => $data['expense_date'],
            ]);

            foreach ($data['splits'] as $split) {
                $expense->splits()->create([
                    'member_id' => $split['member_id'],
                    'amount_owed' => $split['amount_owed'],
                    'is_settled' => false,
                ]);
            }

            return $expense;
        });

        return response()->json([
            'success' => true,
            'data' => $expense->load(['paidByMember', 'splits.member']),
        ], 201);
    }

    private function findAccessibleGroup(int $userId, int $groupId): SplitwiseGroup
    {
        return SplitwiseGroup::query()
            ->whereKey($groupId)
            ->whereHas('members', fn ($query) => $query->where('user_id', $userId))
            ->firstOrFail();
    }
}
