<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()
            ->with(['user:id,name,email,phone', 'group:id,name,type', 'expense:id,description,amount,expense_date'])
            ->latest();

        if ($request->filled('group_id')) {
            $groupId = $request->integer('group_id');
            abort_unless(
                GroupMember::query()->where('group_id', $groupId)->where('user_id', $request->user()->id)->exists(),
                403,
                'Unauthorized'
            );
            $query->where('group_id', $groupId);
        } else {
            $groupIds = GroupMember::query()
                ->where('user_id', $request->user()->id)
                ->pluck('group_id');

            $query->where(function ($query) use ($request, $groupIds) {
                $query->whereIn('group_id', $groupIds)
                    ->orWhere('user_id', $request->user()->id);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->integer('per_page', 30)),
        ]);
    }
}
