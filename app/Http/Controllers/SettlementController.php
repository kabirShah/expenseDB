<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use App\Models\ExpenseShare;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
     public function index(Group $group)
    {
        return response()->json([
            'success' => true,
            'data' => Settlement::where('group_id', $group->id)
                ->with(['fromMember', 'toMember'])
                ->get()
        ]);
    }
    
    public function generateGroupSettlement(Request $request, Group $group)
    {
        $members = $group->members()->get();

        if ($members->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'At least 2 members are required for settlement'
            ]);
        }

        // Compute totals
        $balances = [];

        foreach ($members as $m) {
            $paid = DB::table('expense_contributions')
                ->join('group_expenses', 'expense_contributions.expense_id', '=', 'group_expenses.id')
                ->where('group_expenses.group_id', $group->id)
                ->where('expense_contributions.member_id', $m->id)
                ->sum('amount_paid');

            $owed = DB::table('expense_shares')
                ->join('group_expenses', 'expense_shares.expense_id', '=', 'group_expenses.id')
                ->where('group_expenses.group_id', $group->id)
                ->where('expense_shares.member_id', $m->id)
                ->sum('share_amount');

            $balances[] = [
                'member_id' => $m->id,
                'name' => $m->name,
                'net' => round($paid - $owed, 2)
            ];
        }

        // Split into payers and receivers
        $payers = [];
        $receivers = [];

        foreach ($balances as $b) {
            if ($b['net'] < 0) {
                $payers[] = ['member_id' => $b['member_id'], 'amount' => abs($b['net'])];
            } else if ($b['net'] > 0) {
                $receivers[] = ['member_id' => $b['member_id'], 'amount' => $b['net']];
            }
        }

        // Greedy Settlement Matching
        $settlements = [];

        $i = 0; $j = 0;

        while ($i < count($payers) && $j < count($receivers)) {
            $payAmount = $payers[$i]['amount'];
            $receiveAmount = $receivers[$j]['amount'];

            $settleAmount = min($payAmount, $receiveAmount);

            $settlements[] = [
                'from_member_id' => $payers[$i]['member_id'],
                'to_member_id' => $receivers[$j]['member_id'],
                'amount' => $settleAmount,
                'group_id' => $group->id,
                'status' => 'pending'
            ];

            // Adjust balances
            $payers[$i]['amount'] -= $settleAmount;
            $receivers[$j]['amount'] -= $settleAmount;

            if ($payers[$i]['amount'] == 0) $i++;
            if ($receivers[$j]['amount'] == 0) $j++;
        }

        // Store settlements
        foreach ($settlements as $s) {
            Settlement::create($s);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement generated successfully',
            'settlements' => $settlements
        ]);
    }

    // ----------------------------------------
    // Mark settlement as paid
    // ----------------------------------------
    public function markPaid(Request $request)
    {
        $request->validate([
            'settlement_id' => 'required|exists:settlements,id'
        ]);

        $settlement = Settlement::find($request->settlement_id);

        $settlement->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Settlement marked as paid',
            'data' => $settlement
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_id' => 'nullable|exists:group_expenses,id',
            'from_member_id' => 'required|exists:group_members,id',
            'to_member_id' => 'required|exists:group_members,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string',
        ]);

        $settlement = Settlement::create($request->all());

        return response()->json(['success' => true, 'data' => $settlement]);
    }
}
