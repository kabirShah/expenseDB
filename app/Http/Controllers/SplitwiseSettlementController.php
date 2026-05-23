<?php

namespace App\Http\Controllers;

use App\Models\SplitwiseGroup;
use App\Models\SplitwiseSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SplitwiseSettlementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $groupId = $request->query('group_id');

        $query = SplitwiseSettlement::query()
            ->with(['group', 'payer', 'payee'])
            ->whereHas('group.members', fn ($builder) => $builder->where('user_id', $request->user()->id))
            ->orderByDesc('settled_at')
            ->orderByDesc('id');

        if ($groupId) {
            $query->where('splitwise_group_id', (int) $groupId);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_id' => 'required|integer|exists:splitwise_groups,id',
            'payer_member_id' => 'required|integer|exists:splitwise_group_members,id',
            'payee_member_id' => 'required|integer|exists:splitwise_group_members,id',
            'amount' => 'required|numeric|min:0.01',
            'settled_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $group = SplitwiseGroup::query()
            ->whereKey((int) $data['group_id'])
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        $memberIds = $group->members()->pluck('id')->all();
        abort_unless(in_array((int) $data['payer_member_id'], $memberIds, true), 422, 'Payer must belong to the group.');
        abort_unless(in_array((int) $data['payee_member_id'], $memberIds, true), 422, 'Payee must belong to the group.');

        $settlement = DB::transaction(function () use ($request, $group, $data) {
            return SplitwiseSettlement::create([
                'splitwise_group_id' => $group->id,
                'payer_member_id' => $data['payer_member_id'],
                'payee_member_id' => $data['payee_member_id'],
                'created_by' => $request->user()->id,
                'amount' => $data['amount'],
                'settled_at' => $data['settled_at'],
                'note' => $data['note'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $settlement->load(['group', 'payer', 'payee']),
        ], 201);
    }
}
