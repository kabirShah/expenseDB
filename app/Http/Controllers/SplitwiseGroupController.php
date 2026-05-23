<?php

namespace App\Http\Controllers;

use App\Models\SplitwiseGroup;
use App\Models\SplitwiseGroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SplitwiseGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $groups = SplitwiseGroup::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withCount('members')
            ->with(['members' => fn ($query) => $query->select('id', 'splitwise_group_id', 'user_id', 'name', 'role')])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
            'member_user_ids' => 'nullable|array',
            'member_user_ids.*' => 'integer|exists:users,id',
            'members' => 'nullable|array',
            'members.*.user_id' => 'nullable|integer|exists:users,id',
            'members.*.name' => 'required_without:members.*.user_id|string|max:120',
            'members.*.email' => 'nullable|email|max:190',
        ]);

        $group = DB::transaction(function () use ($request, $data) {
            $group = SplitwiseGroup::create([
                'created_by' => $request->user()->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            SplitwiseGroupMember::create([
                'splitwise_group_id' => $group->id,
                'user_id' => $request->user()->id,
                'name' => $request->user()->name ?? trim(($request->user()->first_name ?? '') . ' ' . ($request->user()->last_name ?? '')),
                'email' => $request->user()->email,
                'role' => 'admin',
            ]);

            foreach (array_unique($data['member_user_ids'] ?? []) as $userId) {
                if ((int) $userId === (int) $request->user()->id) {
                    continue;
                }

                $user = User::query()->find($userId);
                if (!$user) {
                    continue;
                }

                SplitwiseGroupMember::firstOrCreate(
                    [
                        'splitwise_group_id' => $group->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                        'email' => $user->email,
                        'role' => 'member',
                    ]
                );
            }

            foreach ($data['members'] ?? [] as $member) {
                SplitwiseGroupMember::firstOrCreate(
                    [
                        'splitwise_group_id' => $group->id,
                        'user_id' => $member['user_id'] ?? null,
                        'email' => $member['email'] ?? null,
                    ],
                    [
                        'name' => $member['name'] ?? 'Member',
                        'role' => 'member',
                    ]
                );
            }

            return $group;
        });

        return response()->json([
            'success' => true,
            'data' => $group->load(['members.user']),
        ], 201);
    }

    public function show(Request $request, int $groupId): JsonResponse
    {
        $group = $this->findAccessibleGroup($request->user()->id, $groupId);

        return response()->json([
            'success' => true,
            'data' => $group->load([
                'members.user',
                'expenses.splits.member',
                'settlements.payer',
                'settlements.payee',
            ]),
        ]);
    }

    private function findAccessibleGroup(int $userId, int $groupId): SplitwiseGroup
    {
        return SplitwiseGroup::query()
            ->whereKey($groupId)
            ->whereHas('members', fn ($query) => $query->where('user_id', $userId))
            ->firstOrFail();
    }
}
