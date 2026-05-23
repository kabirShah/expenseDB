<?php

namespace App\Http\Controllers;

use App\Models\FriendRelationship;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FriendController extends Controller
{
    public function index(Request $request)
    {
        $friends = FriendRelationship::query()
            ->where('user_id', $request->user()->id)
            ->with('friend:id,name,email')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $friends,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'friend_user_id' => 'required|integer|exists:users,id',
        ]);

        if ((int) $data['friend_user_id'] === (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot add yourself as a friend.',
            ], 422);
        }

        $friendship = DB::transaction(function () use ($request, $data) {
            $friendship = FriendRelationship::firstOrCreate([
                'user_id' => $request->user()->id,
                'friend_user_id' => $data['friend_user_id'],
            ]);

            FriendRelationship::firstOrCreate([
                'user_id' => $data['friend_user_id'],
                'friend_user_id' => $request->user()->id,
            ]);

            return $friendship->load('friend:id,name,email');
        });

        return response()->json([
            'success' => true,
            'message' => 'Friend added',
            'data' => $friendship,
        ], 201);
    }

    public function destroy(Request $request, int $friendUserId)
    {
        DB::transaction(function () use ($request, $friendUserId) {
            FriendRelationship::query()
                ->where('user_id', $request->user()->id)
                ->where('friend_user_id', $friendUserId)
                ->delete();

            FriendRelationship::query()
                ->where('user_id', $friendUserId)
                ->where('friend_user_id', $request->user()->id)
                ->delete();
        });

        return response()->json(['success' => true, 'message' => 'Friend removed']);
    }
}
