<?php

namespace App\Services;

use App\Models\DeviceContact;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContactSyncService
{
    public function sync(int $userId, array $contacts): Collection
    {
        return DB::transaction(function () use ($userId, $contacts) {
            return collect($contacts)->map(function (array $contact) use ($userId) {
                $phone = $this->normalizePhone($contact['phone'] ?? null);
                $email = isset($contact['email']) ? strtolower(trim((string) $contact['email'])) : null;
                $matchedUser = $this->matchUser($phone, $email, $userId);

                return DeviceContact::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'device_contact_id' => $contact['device_contact_id'] ?? sha1(($phone ?: '') . '|' . ($email ?: '') . '|' . ($contact['name'] ?? '')),
                    ],
                    [
                        'name' => $contact['name'] ?? null,
                        'phone' => $phone,
                        'email' => $email,
                        'matched_user_id' => $matchedUser?->id,
                        'is_registered' => (bool) $matchedUser,
                        'last_synced_at' => now(),
                        'metadata' => [
                            'source' => 'device',
                        ],
                    ]
                )->load('matchedUser:id,name,email,phone');
            });
        });
    }

    public function invite(int $userId, int $contactId): Friend
    {
        $contact = DeviceContact::query()
            ->where('user_id', $userId)
            ->findOrFail($contactId);

        $contact->forceFill(['is_invited' => true])->save();

        $lookup = $contact->matched_user_id
            ? [
                'user_id' => $userId,
                'friend_user_id' => $contact->matched_user_id,
            ]
            : [
                'user_id' => $userId,
                'phone' => $contact->phone,
                'email' => $contact->email,
            ];

        return Friend::updateOrCreate(
            $lookup,
            [
                'display_name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'status' => $contact->matched_user_id ? 'requested' : 'invited',
                'metadata' => [
                    'device_contact_id' => $contact->id,
                ],
            ]
        );
    }

    private function matchUser(?string $phone, ?string $email, int $currentUserId): ?User
    {
        return User::query()
            ->whereKeyNot($currentUserId)
            ->where(function ($query) use ($phone, $email) {
                if ($phone) {
                    $query->orWhere('phone', $phone);
                }
                if ($email) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
