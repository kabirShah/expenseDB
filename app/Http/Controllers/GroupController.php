<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    /**
     * Display a listing of user's groups
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $groups = Group::whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId)->where('status', 'active');
        })->with(['members.user:id,name,email', 'creator:id,name,email'])
          ->orderBy('created_at', 'desc')
          ->get()
          ->map(function ($group) use ($userId) {
              $member = $group->members->firstWhere('user_id', $userId);
              return [
                  'id' => $group->id,
                  'group_id' => $group->group_id,
                  'name' => $group->name,
                  'description' => $group->description,
                  'currency' => $group->currency,
                  'status' => $group->status,
                  'member_count' => $group->members->where('status', 'active')->count(),
                  'role' => $member ? $member->role : null,
                  'created_at' => $group->created_at,
                  'created_by' => $group->creator->name,
              ];
          });

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * Store a newly created group
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'currency' => 'required|string|size:3',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();

        DB::beginTransaction();
        try {
            // Create group
            $group = Group::create([
                'created_by' => $userId,
                'name' => $request->name,
                'description' => $request->description,
                'currency' => $request->currency,
                'status' => 'active',
                'settings' => $request->settings ?? []
            ]);

            // Add creator as admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $userId,
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => now()
            ]);

            // Add other members
            foreach ($request->member_ids as $memberId) {
                if ($memberId != $userId) {
                    GroupMember::create([
                        'group_id' => $group->id,
                        'user_id' => $memberId,
                        'role' => 'member',
                        'status' => 'active',
                        'joined_at' => now()
                    ]);
                }
            }

            // Log creation
            AuditLog::log($userId, 'create', 'group', $group->id, "Created group '{$group->name}'");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'data' => $group->load(['members.user:id,name,email', 'creator:id,name,email'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified group
     */
    public function show(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isMember($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to group'
            ], 403);
        }

        $group->load([
            'members.user:id,name,email',
            'creator:id,name,email',
            'expenseSplits' => function ($query) {
                $query->active()->latest()->limit(5);
            }
        ]);

        $member = $group->members->firstWhere('user_id', $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $group->id,
                'group_id' => $group->group_id,
                'name' => $group->name,
                'description' => $group->description,
                'currency' => $group->currency,
                'status' => $group->status,
                'settings' => $group->settings,
                'member_count' => $group->members->where('status', 'active')->count(),
                'total_expenses' => $group->getTotalExpenses(),
                'role' => $member ? $member->role : null,
                'members' => $group->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'user_id' => $member->user_id,
                        'name' => $member->user->name,
                        'email' => $member->user->email,
                        'role' => $member->role,
                        'status' => $member->status,
                        'joined_at' => $member->joined_at
                    ];
                }),
                'recent_expenses' => $group->expenseSplits->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'title' => $expense->title,
                        'total_amount' => $expense->total_amount,
                        'expense_date' => $expense->expense_date,
                        'paid_by' => $expense->payer->name
                    ];
                }),
                'created_at' => $group->created_at,
                'created_by' => $group->creator->name
            ]
        ]);
    }

    /**
     * Update the specified group
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only group admins can update group details'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'currency' => 'sometimes|required|string|size:3',
            'settings' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $group->only(['name', 'description', 'currency', 'settings']);

        $group->update($request->only(['name', 'description', 'currency', 'settings']));

        // Log update
        AuditLog::log($userId, 'update', 'group', $group->id, "Updated group '{$group->name}'", $oldValues, $group->only(['name', 'description', 'currency', 'settings']));

        return response()->json([
            'success' => true,
            'message' => 'Group updated successfully',
            'data' => $group
        ]);
    }

    /**
     * Remove the specified group
     */
    public function destroy(Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only group admins can delete the group'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Log deletion
            AuditLog::log($userId, 'delete', 'group', $group->id, "Deleted group '{$group->name}'");

            // Soft delete or cascade delete based on requirements
            $group->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Group deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add member to group
     */
    public function addMember(Request $request, Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only group admins can add members'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,member'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($group->isMember($request->user_id)) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a member of this group'
            ], 422);
        }

        $member = GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $request->user_id,
            'role' => $request->role,
            'status' => 'active',
            'joined_at' => now()
        ]);

        // Log member addition
        AuditLog::log($userId, 'add_member', 'group_member', $member->id, "Added member to group '{$group->name}'");

        return response()->json([
            'success' => true,
            'message' => 'Member added successfully',
            'data' => $member->load('user:id,name,email')
        ]);
    }

    /**
     * Remove member from group
     */
    public function removeMember(Request $request, Group $group): JsonResponse
    {
        $userId = Auth::id();

        if (!$group->isAdmin($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Only group admins can remove members'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $member = $group->members()->where('user_id', $request->user_id)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a member of this group'
            ], 404);
        }

        if ($member->user_id == $userId) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove yourself from the group'
            ], 422);
        }

        // Log member removal
        AuditLog::log($userId, 'remove_member', 'group_member', $member->id, "Removed member from group '{$group->name}'");

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member removed successfully'
        ]);
    }
}
