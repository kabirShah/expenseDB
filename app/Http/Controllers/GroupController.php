<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $groups = Group::where('created_by', $request->user()->id)
            ->withCount('members')
            ->get();

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required'
        ]);

        $group = Group::create([
            'group_uuid' => Str::uuid(),
            'name' => $request->name,
            'created_by' => $request->user()->id
        ]);

        // Add creator as member
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $request->user()->id,
            'name' => $request->user()->name,
            'is_app_user' => true
        ]);

        return response()->json(['success' => true, 'data' => $group]);
    }

    public function show($id)
    {
        $group = Group::with('members')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $group]);
    }

    public function delete($id)
    {
        Group::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Group deleted']);
    }
}
