<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Models\Settlement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettlementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Settlement::query()
            ->with(['group', 'fromUser', 'toUser', 'expense'])
            ->where(function ($builder) use ($request) {
                $builder
                    ->where('from_user_id', $request->user()->id)
                    ->orWhere('to_user_id', $request->user()->id);
            });

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->integer('group_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required|exists:expense_groups,id',
            'from_user_id' => 'required|exists:users,id|different:to_user_id',
            'to_user_id' => 'required|exists:users,id',
            'related_expense_id' => 'nullable|exists:expenses,id',
            'amount' => 'required|numeric|min:0.01',
            'settled_amount' => 'nullable|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $group = ExpenseGroup::query()->findOrFail($data['group_id']);

        foreach ([$data['from_user_id'], $data['to_user_id'], $request->user()->id] as $userId) {
            abort_unless(
                GroupMember::query()->where('group_id', $group->id)->where('user_id', $userId)->exists(),
                422,
                'Settlement users must belong to the group.'
            );
        }

        $settlement = DB::transaction(function () use ($request, $data) {
            $settledAmount = round((float) ($data['settled_amount'] ?? $data['amount']), 2);
            $amount = round((float) $data['amount'], 2);

            return Settlement::create([
                'group_id' => $data['group_id'],
                'from_user_id' => $data['from_user_id'],
                'to_user_id' => $data['to_user_id'],
                'related_expense_id' => $data['related_expense_id'] ?? null,
                'amount' => $amount,
                'settled_amount' => $settledAmount,
                'status' => $settledAmount >= $amount ? 'settled' : 'partial',
                'method' => $data['method'] ?? null,
                'notes' => $data['notes'] ?? null,
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
