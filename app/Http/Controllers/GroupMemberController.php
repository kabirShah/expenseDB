<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    public function store(Request $request, $groupId = null)
    {
        if ($groupId && !$request->has('group_id')) {
            $request->merge(['group_id' => $groupId]);
        }

        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'name' => 'required',
            'phone' => 'nullable',
            'email' => 'nullable|email',
        ]);

        $member = GroupMember::create($request->all());

        return response()->json(['success' => true, 'data' => $member]);
    }

    public function delete($id)
    {
        GroupMember::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Member removed']);
    }
}
