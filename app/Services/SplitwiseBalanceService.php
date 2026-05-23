<?php

namespace App\Services;

use App\Models\SplitwiseGroup;
use App\Models\SplitwiseGroupMember;
use App\Models\SplitwiseSettlement;

class SplitwiseBalanceService
{
    public function balancesForGroup(SplitwiseGroup $group): array
    {
        $members = $group->members()->orderBy('name')->get();
        $net = [];

        foreach ($members as $member) {
            $net[$member->id] = 0.0;
        }

        $expenses = $group->expenses()->with('splits')->get();

        foreach ($expenses as $expense) {
            $payerId = $expense->paid_by_member_id;
            $net[$payerId] = ($net[$payerId] ?? 0.0) + (float) $expense->amount;

            foreach ($expense->splits as $split) {
                $memberId = $split->member_id;
                $net[$memberId] = ($net[$memberId] ?? 0.0) - (float) $split->amount_owed;
            }
        }

        $settlements = $group->settlements()->get();

        foreach ($settlements as $settlement) {
            $net[$settlement->payer_member_id] = ($net[$settlement->payer_member_id] ?? 0.0) - (float) $settlement->amount;
            $net[$settlement->payee_member_id] = ($net[$settlement->payee_member_id] ?? 0.0) + (float) $settlement->amount;
        }

        $memberBalances = $members->map(function (SplitwiseGroupMember $member) use ($net) {
            $balance = round((float) ($net[$member->id] ?? 0.0), 2);

            return [
                'member_id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->name,
                'email' => $member->email,
                'balance' => $balance,
                'you_are_owed' => $balance > 0 ? $balance : 0.0,
                'you_owe' => $balance < 0 ? abs($balance) : 0.0,
            ];
        })->values();

        return [
            'group_id' => $group->id,
            'group_name' => $group->name,
            'members' => $memberBalances,
            'simplified' => $this->simplifyDebts($memberBalances->all()),
        ];
    }

    private function simplifyDebts(array $memberBalances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($memberBalances as $member) {
            $balance = round((float) ($member['balance'] ?? 0), 2);

            if ($balance > 0) {
                $creditors[] = ['member' => $member, 'amount' => $balance];
            } elseif ($balance < 0) {
                $debtors[] = ['member' => $member, 'amount' => abs($balance)];
            }
        }

        $settlements = [];
        $creditorIndex = 0;
        $debtorIndex = 0;

        while (isset($debtors[$debtorIndex], $creditors[$creditorIndex])) {
            $payment = min($debtors[$debtorIndex]['amount'], $creditors[$creditorIndex]['amount']);

            $settlements[] = [
                'from_member_id' => $debtors[$debtorIndex]['member']['member_id'],
                'from_name' => $debtors[$debtorIndex]['member']['name'],
                'to_member_id' => $creditors[$creditorIndex]['member']['member_id'],
                'to_name' => $creditors[$creditorIndex]['member']['name'],
                'amount' => round($payment, 2),
            ];

            $debtors[$debtorIndex]['amount'] = round($debtors[$debtorIndex]['amount'] - $payment, 2);
            $creditors[$creditorIndex]['amount'] = round($creditors[$creditorIndex]['amount'] - $payment, 2);

            if ($debtors[$debtorIndex]['amount'] <= 0) {
                $debtorIndex++;
            }

            if ($creditors[$creditorIndex]['amount'] <= 0) {
                $creditorIndex++;
            }
        }

        return $settlements;
    }
}
