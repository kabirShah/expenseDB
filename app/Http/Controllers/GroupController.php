<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Services\GroupExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function __construct(private readonly GroupExpenseService $groupExpenseService)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $groups = ExpenseGroup::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->withCount('members')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'member_user_ids' => 'nullable|array',
            'member_user_ids.*' => 'integer|exists:users,id',
        ]);

        $group = DB::transaction(function () use ($request, $data) {
            $group = ExpenseGroup::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $request->user()->id,
                'currency' => strtoupper($request->user()->currency ?? 'INR'),
                'is_active' => true,
            ]);

            $memberIds = collect($data['member_user_ids'] ?? [])
                ->push($request->user()->id)
                ->unique()
                ->values();

            foreach ($memberIds as $memberUserId) {
                $user = $memberUserId === $request->user()->id
                    ? $request->user()
                    : \App\Models\User::query()->findOrFail($memberUserId);

                GroupMember::updateOrCreate(
                    ['group_id' => $group->id, 'user_id' => $memberUserId],
                    [
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? null,
                        'role' => $memberUserId === $request->user()->id ? 'admin' : 'member',
                        'joined_at' => now(),
                    ]
                );
            }

            return $group->load('members.user');
        });

        return response()->json(['success' => true, 'data' => $group], 201);
    }

    public function show(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        return response()->json([
            'success' => true,
            'data' => $expenseGroup->load('members.user'),
        ]);
    }

    public function update(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeAdmin($request->user()->id, $expenseGroup);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
        ]);

        $expenseGroup->update($data);

        return response()->json(['success' => true, 'data' => $expenseGroup->fresh()]);
    }

    public function destroy(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeAdmin($request->user()->id, $expenseGroup);
        $expenseGroup->delete();

        return response()->json(['success' => true, 'message' => 'Group deleted']);
    }

    public function addMember(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeAdmin($request->user()->id, $expenseGroup);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|in:admin,member',
        ]);

        $user = \App\Models\User::query()->findOrFail($data['user_id']);

        $member = GroupMember::updateOrCreate(
            ['group_id' => $expenseGroup->id, 'user_id' => $user->id],
            [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'role' => $data['role'] ?? 'member',
                'joined_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'data' => $member->load('user')], 201);
    }

    public function removeMember(Request $request, ExpenseGroup $expenseGroup, GroupMember $member)
    {
        $this->authorizeAdmin($request->user()->id, $expenseGroup);

        if ($member->group_id !== $expenseGroup->id) {
            return response()->json(['success' => false, 'message' => 'Member not found in group'], 404);
        }

        $member->delete();

        return response()->json(['success' => true, 'message' => 'Member removed']);
    }

    public function balances(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        $balances = $this->groupExpenseService->balancesForGroup($expenseGroup);

        return response()->json([
            'success' => true,
            'group_id' => $expenseGroup->id,
            'data' => $balances,
        ]);
    }

    public function activity(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        return response()->json([
            'success' => true,
            'data' => $expenseGroup->activity()->latest()->get(),
        ]);
    }

    public function debts(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        return response()->json([
            'success' => true,
            'data' => $this->groupExpenseService->balancesForGroup($expenseGroup)['simplified'],
        ]);
    }

    private function authorizeGroupAccess(int $userId, ExpenseGroup $group): void
    {
        abort_unless(
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $userId)->exists(),
            403,
            'Unauthorized'
        );
    }

    private function authorizeAdmin(int $userId, ExpenseGroup $group): void
    {
        abort_unless(
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $userId)->where('role', 'admin')->exists(),
            403,
            'Only group admins can perform this action.'
        );
    }
}
