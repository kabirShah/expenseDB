<?php

namespace App\Http\Controllers;

use App\Models\DeviceContact;
use App\Models\Friend;
use App\Services\ContactSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedContactController extends Controller
{
    public function __construct(private readonly ContactSyncService $contacts)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $friends = Friend::query()
            ->where('user_id', $request->user()->id)
            ->with('friendUser:id,name,email,phone')
            ->orderByDesc('is_favorite')
            ->orderByDesc('last_used_at')
            ->orderByDesc('usage_count')
            ->paginate($request->integer('per_page', 30));

        return response()->json(['success' => true, 'data' => $friends]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contacts' => 'required|array|max:1000',
            'contacts.*.device_contact_id' => 'nullable|string|max:191',
            'contacts.*.name' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:30',
            'contacts.*.email' => 'nullable|email|max:255',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->contacts->sync($request->user()->id, $data['contacts']),
        ]);
    }

    public function deviceContacts(Request $request): JsonResponse
    {
        $contacts = DeviceContact::query()
            ->where('user_id', $request->user()->id)
            ->with('matchedUser:id,name,email,phone')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $contacts]);
    }

    public function invite(Request $request, DeviceContact $contact): JsonResponse
    {
        abort_unless((int) $contact->user_id === (int) $request->user()->id, 403, 'Unauthorized');

        return response()->json([
            'success' => true,
            'message' => $contact->matched_user_id ? 'Friend request sent' : 'Invite marked for contact',
            'data' => $this->contacts->invite($request->user()->id, $contact->id)->load('friendUser:id,name,email,phone'),
        ], 201);
    }

    public function favorite(Request $request, Friend $friend): JsonResponse
    {
        abort_unless((int) $friend->user_id === (int) $request->user()->id, 403, 'Unauthorized');
        $friend->forceFill(['is_favorite' => !$friend->is_favorite])->save();

        return response()->json(['success' => true, 'data' => $friend->fresh('friendUser:id,name,email,phone')]);
    }

    public function respond(Request $request, Friend $friend): JsonResponse
    {
        abort_unless((int) $friend->friend_user_id === (int) $request->user()->id, 403, 'Unauthorized');

        $data = $request->validate([
            'status' => 'required|in:accepted,rejected,blocked',
        ]);

        $friend->forceFill([
            'status' => $data['status'],
            'accepted_at' => $data['status'] === 'accepted' ? now() : $friend->accepted_at,
            'blocked_at' => $data['status'] === 'blocked' ? now() : null,
        ])->save();

        return response()->json(['success' => true, 'data' => $friend->fresh('friendUser:id,name,email,phone')]);
    }
}
