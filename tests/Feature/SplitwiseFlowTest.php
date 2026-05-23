<?php

namespace Tests\Feature;

use App\Models\ExpenseSplit;
use App\Models\FriendRelationship;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SplitwiseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_expense_and_settlement_flow_is_exposed_in_dashboard(): void
    {
        $owner = $this->createUser('owner');
        $friendOne = $this->createUser('friend-one');
        $friendTwo = $this->createUser('friend-two');

        Sanctum::actingAs($owner);

        $this->postJson('/api/friends', [
            'friend_user_id' => $friendOne->id,
        ])->assertCreated();

        $this->assertDatabaseHas('friend_relationships', [
            'user_id' => $owner->id,
            'friend_user_id' => $friendOne->id,
        ]);
        $this->assertDatabaseHas('friend_relationships', [
            'user_id' => $friendOne->id,
            'friend_user_id' => $owner->id,
        ]);

        $groupResponse = $this->postJson('/api/groups', [
            'name' => 'Goa Trip',
            'description' => 'Weekend expenses',
            'member_user_ids' => [$friendOne->id, $friendTwo->id],
        ])->assertCreated();

        $groupId = $groupResponse->json('data.id');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $groupId,
            'user_id' => $owner->id,
            'role' => 'admin',
        ]);
        $this->assertSame(3, GroupMember::query()->where('group_id', $groupId)->count());

        $expenseResponse = $this->postJson("/api/groups/{$groupId}/expenses", [
            'title' => 'Dinner',
            'description' => 'Beachside dinner',
            'amount' => 900,
            'currency' => 'INR',
            'expense_date' => now()->toISOString(),
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => $owner->id],
                ['user_id' => $friendOne->id],
                ['user_id' => $friendTwo->id],
            ],
            'payers' => [
                ['user_id' => $owner->id, 'amount_paid' => 900],
            ],
        ])->assertCreated();

        $expenseId = $expenseResponse->json('data.id');

        $this->assertDatabaseHas('expenses', [
            'id' => $expenseId,
            'group_id' => $groupId,
            'source_type' => 'group',
            'split_type' => 'equal',
        ]);
        $this->assertSame(3, ExpenseSplit::query()->where('expense_id', $expenseId)->count());

        $this->getJson("/api/groups/{$groupId}/balances")
            ->assertOk()
            ->assertJsonPath('data.balances.' . $owner->id, 600)
            ->assertJsonPath('data.balances.' . $friendOne->id, -300)
            ->assertJsonPath('data.balances.' . $friendTwo->id, -300);

        $this->postJson("/api/groups/{$groupId}/settle", [
            'from_user_id' => $friendOne->id,
            'to_user_id' => $owner->id,
            'related_expense_id' => $expenseId,
            'amount' => 300,
            'settled_amount' => 150,
            'method' => 'UPI',
            'notes' => 'Partial settle',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'partial');

        $this->assertDatabaseHas('settlements', [
            'group_id' => $groupId,
            'from_user_id' => $friendOne->id,
            'to_user_id' => $owner->id,
            'amount' => 300,
            'settled_amount' => 150,
            'status' => 'partial',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('groups.count', 1)
            ->assertJsonPath('friends.count', 1)
            ->assertJsonPath('totals.today_group_expense', 900);
    }

    public function test_user_cannot_add_self_as_friend(): void
    {
        $user = $this->createUser('self-user');

        Sanctum::actingAs($user);

        $this->postJson('/api/friends', [
            'friend_user_id' => $user->id,
        ])->assertStatus(422);

        $this->assertSame(0, FriendRelationship::query()->count());
    }

    private function createUser(string $prefix): User
    {
        return User::query()->create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'Tester',
            'email' => $prefix . '@example.com',
            'phone' => '99999' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
            'dob' => '1995-01-01',
            'gender' => 'Other',
            'password' => Hash::make('password'),
        ]);
    }
}
