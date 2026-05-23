<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        // Get groups where user is a member
        $groups = ExpenseGroup::whereHas('members', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->with(['members' => function ($q) {
            $q->limit(4); // Preview avatars
        }])->withCount('expenses')->get();

        return response()->json($groups);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'type'     => 'nullable|in:trip,home,couple,office,other',
            'currency' => 'nullable|string|max:10',
        ]);

        $group = ExpenseGroup::create([
            'name'       => $request->name,
            'type'       => $request->type ?? 'other',
            'currency'   => $request->currency ?? 'INR',
            'description'=> $request->description,
            'created_by' => $request->user()->id,
        ]);

        // Add creator as admin member
        $group->members()->create([
            'user_id' => $request->user()->id,
            'role'    => 'admin',
        ]);

        return response()->json($group->load('members'), 201);
    }

    public function show(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        return response()->json([
            'group'    => $expenseGroup->load(['members.user', 'expenses' => function($q) {
                $q->orderBy('expense_date', 'desc')->limit(20);
            }]),
            'balances' => $this->calculateBalances($expenseGroup->id),
            'debts'    => $this->calculateDebts($expenseGroup->id),
        ]);
    }

    public function addMember(Request $request, ExpenseGroup $expenseGroup)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'name'    => 'required_without:user_id|string',
            'email'   => 'nullable|email',
            'phone'   => 'nullable|string',
        ]);

        $member = $expenseGroup->members()->create($request->only(['user_id', 'name', 'email', 'phone']));

        $this->logActivity($expenseGroup->id, $request->user()->id, 'member_added',
            $member->id, "Added " . ($member->user?->name ?? $member->name) . " to the group");

        return response()->json($member, 201);
    }

    public function removeMember(Request $request, ExpenseGroup $expenseGroup, GroupMember $member)
    {
        $this->authorizeGroupAccess($request->user()->id, $expenseGroup);

        // Check if member has unsettled balance
        $balance = $this->getMemberBalance($expenseGroup->id, $member->id);
        if (abs($balance) > 0) {
            return response()->json([
                'message' => 'Member has unsettled balance of ₹' . abs($balance) . '. Settle first.',
            ], 422);
        }

        $member->delete();
        return response()->json(['message' => 'Member removed']);
    }

    private function authorizeGroupAccess(int $userId, ExpenseGroup $group): void
    {
        $isMember = $group->members()->where('user_id', $userId)->exists();
        if (!$isMember) abort(403, 'Not a group member');
    }

    private function calculateBalances(int $groupId): array
    {
        return \DB::select("
            SELECT
                gm.id AS member_id,
                COALESCE(u.name, gm.name) AS member_name,
                COALESCE(SUM(CASE WHEN ge.paid_by = gm.id THEN ge.amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(ges.owed_amount), 0) AS total_owed,
                COALESCE(SUM(CASE WHEN gs_r.payee_id = gm.id THEN gs_r.amount ELSE 0 END), 0) AS total_received,
                COALESCE(SUM(CASE WHEN gs_s.payer_id = gm.id THEN gs_s.amount ELSE 0 END), 0) AS total_sent,
                (
                    COALESCE(SUM(CASE WHEN ge.paid_by = gm.id THEN ge.amount ELSE 0 END), 0)
                    - COALESCE(SUM(ges.owed_amount), 0)
                    + COALESCE(SUM(CASE WHEN gs_r.payee_id = gm.id THEN gs_r.amount ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN gs_s.payer_id = gm.id THEN gs_s.amount ELSE 0 END), 0)
                ) AS net_balance
            FROM group_members gm
            LEFT JOIN users u ON u.id = gm.user_id
            LEFT JOIN group_expenses ge ON ge.group_id = gm.group_id
            LEFT JOIN group_expense_splits ges ON ges.group_expense_id = ge.id AND ges.member_id = gm.id
            LEFT JOIN group_settlements gs_r ON gs_r.group_id = gm.group_id AND gs_r.payee_id = gm.id
            LEFT JOIN group_settlements gs_s ON gs_s.group_id = gm.group_id AND gs_s.payer_id = gm.id
            WHERE gm.group_id = ?
            GROUP BY gm.id, member_name
        ", [$groupId]);
    }

    private function calculateDebts(int $groupId): array
    {
        return \DB::select("
            SELECT
                payer.id AS from_member_id,
                COALESCE(pu.name, payer.name) AS from_name,
                payee.id AS to_member_id,
                COALESCE(pau.name, payee.name) AS to_name,
                SUM(ges.owed_amount) AS amount_owed
            FROM group_expense_splits ges
            JOIN group_expenses ge ON ge.id = ges.group_expense_id
            JOIN group_members payer ON payer.id = ges.member_id
            JOIN group_members payee ON payee.id = ge.paid_by
            LEFT JOIN users pu ON pu.id = payer.user_id
            LEFT JOIN users pau ON pau.id = payee.user_id
            WHERE ge.group_id = ? AND ges.is_settled = 0 AND payer.id != payee.id
            GROUP BY payer.id, payee.id, from_name, to_name
            HAVING SUM(ges.owed_amount) > 0
        ", [$groupId]);
    }

    private function getMemberBalance(int $groupId, int $memberId): float
    {
        $balances = $this->calculateBalances($groupId);
        foreach ($balances as $b) {
            if ($b->member_id === $memberId) return (float) $b->net_balance;
        }
        return 0;
    }

    private function logActivity(int $groupId, int $userId, string $type, ?int $entityId, string $message): void
    {
        \DB::table('group_activity')->insert([
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'type'       => $type,
            'entity_id'  => $entityId,
            'message'    => $message,
            'created_at' => now(),
        ]);
    }
}