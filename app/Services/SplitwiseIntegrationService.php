<?php

namespace App\Services;

use App\Models\User;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\ExpenseSplit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SplitwiseIntegrationService
{
    protected $baseUrl = 'https://secure.splitwise.com/api/v3.0';
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.splitwise.client_id');
        $this->clientSecret = config('services.splitwise.client_secret');
        $this->redirectUri = config('services.splitwise.redirect_uri');
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl()
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri,
            'scope' => '',
        ];

        return 'https://secure.splitwise.com/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code)
    {
        try {
            $response = Http::asForm()->post('https://secure.splitwise.com/oauth/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Splitwise token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Splitwise token exchange error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = Http::asForm()->post('https://secure.splitwise.com/oauth/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Splitwise token refresh error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user profile from Splitwise
     */
    public function getUserProfile($accessToken)
    {
        try {
            $response = Http::withToken($accessToken)->get("{$this->baseUrl}/get_current_user");

            if ($response->successful()) {
                return $response->json()['user'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Splitwise get user profile error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get user's groups from Splitwise
     */
    public function getUserGroups($accessToken)
    {
        try {
            $response = Http::withToken($accessToken)->get("{$this->baseUrl}/get_groups");

            if ($response->successful()) {
                return $response->json()['groups'];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Splitwise get groups error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get group expenses from Splitwise
     */
    public function getGroupExpenses($accessToken, $groupId, $limit = 20, $offset = 0)
    {
        try {
            $response = Http::withToken($accessToken)->get("{$this->baseUrl}/get_expenses", [
                'group_id' => $groupId,
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($response->successful()) {
                return $response->json()['expenses'];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Splitwise get group expenses error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Import Splitwise group to pocket-money
     */
    public function importGroup(User $user, $splitwiseGroup)
    {
        try {
            // Create group in pocket-money
            $group = Group::create([
                'group_id' => 'sw_' . $splitwiseGroup['id'],
                'name' => $splitwiseGroup['name'],
                'description' => $splitwiseGroup['description'] ?? null,
                'currency' => $splitwiseGroup['currency_code'] ?? 'USD',
                'status' => 'active',
                'member_count' => count($splitwiseGroup['members']),
                'created_by' => $user->id,
            ]);

            // Add members
            foreach ($splitwiseGroup['members'] as $member) {
                $memberUser = User::where('email', $member['email'])->first();

                if (!$memberUser) {
                    // Create user if doesn't exist
                    $memberUser = User::create([
                        'name' => $member['first_name'] . ' ' . $member['last_name'],
                        'email' => $member['email'],
                        'password' => bcrypt(str_random(16)), // Random password
                        'email_verified_at' => now(),
                    ]);
                }

                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $memberUser->id,
                    'role' => $member['id'] == $splitwiseGroup['creator']['id'] ? 'admin' : 'member',
                    'status' => 'active',
                ]);
            }

            return $group;
        } catch (\Exception $e) {
            Log::error('Splitwise group import error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Import Splitwise expenses to pocket-money group
     */
    public function importExpenses(Group $group, $splitwiseExpenses)
    {
        $imported = 0;

        try {
            foreach ($splitwiseExpenses as $expense) {
                // Skip if expense already exists
                $existing = ExpenseSplit::where('expense_split_id', 'sw_' . $expense['id'])->first();
                if ($existing) continue;

                // Find the user who paid
                $paidByUser = null;
                foreach ($group->members as $member) {
                    if ($member->user->email == $expense['created_by']['email']) {
                        $paidByUser = $member->user;
                        break;
                    }
                }

                if (!$paidByUser) continue;

                // Create expense split
                $expenseSplit = ExpenseSplit::create([
                    'expense_split_id' => 'sw_' . $expense['id'],
                    'title' => $expense['description'],
                    'description' => $expense['details'] ?? null,
                    'total_amount' => $expense['cost'],
                    'split_type' => 'equal', // Default to equal split
                    'expense_date' => Carbon::parse($expense['date'])->toDateString(),
                    'category' => $expense['category']['name'] ?? null,
                    'paid_by' => $paidByUser->name,
                    'group_id' => $group->id,
                    'created_by' => $paidByUser->id,
                ]);

                $imported++;
            }

            return $imported;
        } catch (\Exception $e) {
            Log::error('Splitwise expenses import error', ['error' => $e->getMessage()]);
            return $imported;
        }
    }

    /**
     * Export pocket-money group to Splitwise
     */
    public function exportGroupToSplitwise(User $user, Group $group, $accessToken)
    {
        try {
            // Create group in Splitwise
            $members = [];
            foreach ($group->members as $member) {
                $members[] = [
                    'email' => $member->user->email,
                    'first_name' => explode(' ', $member->user->name)[0],
                    'last_name' => explode(' ', $member->user->name)[1] ?? '',
                ];
            }

            $response = Http::withToken($accessToken)->post("{$this->baseUrl}/create_group", [
                'name' => $group->name,
                'members' => $members,
                'currency_code' => $group->currency,
            ]);

            if ($response->successful()) {
                return $response->json()['group'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Splitwise group export error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if user has valid Splitwise connection
     */
    public function hasValidConnection(User $user)
    {
        if (!$user->splitwise_access_token) {
            return false;
        }

        // Check if token is expired
        if ($user->splitwise_token_expires_at && now()->gte($user->splitwise_token_expires_at)) {
            // Try to refresh token
            $newTokens = $this->refreshAccessToken($user->splitwise_refresh_token);
            if ($newTokens) {
                $user->update([
                    'splitwise_access_token' => $newTokens['access_token'],
                    'splitwise_refresh_token' => $newTokens['refresh_token'] ?? $user->splitwise_refresh_token,
                    'splitwise_token_expires_at' => now()->addSeconds($newTokens['expires_in'] ?? 3600),
                ]);
                return true;
            }
            return false;
        }

        return true;
    }
}
