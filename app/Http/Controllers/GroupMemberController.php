<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupMemberController extends Controller
{
    public function store(Request $request)
    {
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
