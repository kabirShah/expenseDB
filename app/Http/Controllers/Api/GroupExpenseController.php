<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupExpense;
use App\Models\GroupExpenseSplit;
use App\Models\ExpenseGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupExpenseController extends Controller
{
    public function store(Request $request, ExpenseGroup $expenseGroup)
    {
        $request->validate([
            'title'        => 'required|string|max:200',
            'amount'       => 'required|numeric|min:0.01',
            'paid_by'      => 'required|exists:group_members,id',
            'split_type'   => 'required|in:equal,exact,percentage,shares',
            'expense_date' => 'required|date',
            'splits'       => 'required|array|min:1',
            'splits.*.member_id'  => 'required|exists:group_members,id',
            'splits.*.amount'     => 'nullable|numeric|min:0',
            'splits.*.percentage' => 'nullable|numeric|between:0,100',
            'splits.*.shares'     => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $expenseGroup) {
            $expense = GroupExpense::create([
                'group_id'     => $expenseGroup->id,
                'paid_by'      => $request->paid_by,
                'category_id'  => $request->category_id,
                'title'        => $request->title,
                'amount'       => $request->amount,
                'split_type'   => $request->split_type,
                'notes'        => $request->notes,
                'expense_date' => $request->expense_date,
                'created_by'   => $request->user()->id,
            ]);

            $splits = $this->calculateSplits(
                $request->amount,
                $request->split_type,
                $request->splits
            );

            foreach ($splits as $split) {
                GroupExpenseSplit::create([
                    'group_expense_id' => $expense->id,
                    'member_id'        => $split['member_id'],
                    'owed_amount'      => $split['amount'],
                    'percentage'       => $split['percentage'] ?? null,
                    'shares'           => $split['shares'] ?? null,
                ]);
            }

            // Log activity
            DB::table('group_activity')->insert([
                'group_id'   => $expenseGroup->id,
                'user_id'    => $request->user()->id,
                'type'       => 'expense_added',
                'entity_id'  => $expense->id,
                'message'    => $request->user()->name . " added \"{$expense->title}\" — ₹{$expense->amount}",
                'created_at' => now(),
            ]);

            return response()->json($expense->load(['splits.member', 'paidByMember']), 201);
        });
    }

    public function settle(Request $request, ExpenseGroup $expenseGroup)
    {
        $request->validate([
            'payer_id' => 'required|exists:group_members,id',
            'payee_id' => 'required|exists:group_members,id',
            'amount'   => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($request, $expenseGroup) {
            // Record the settlement
            DB::table('group_settlements')->insert([
                'group_id'    => $expenseGroup->id,
                'payer_id'    => $request->payer_id,
                'payee_id'    => $request->payee_id,
                'amount'      => $request->amount,
                'notes'       => $request->notes,
                'recorded_by' => $request->user()->id,
                'settled_at'  => now(),
            ]);

            // Mark splits as settled (up to the settlement amount)
            GroupExpenseSplit::where('member_id', $request->payer_id)
                ->whereHas('groupExpense', function ($q) use ($expenseGroup, $request) {
                    $q->where('group_id', $expenseGroup->id)
                      ->where('paid_by', $request->payee_id);
                })
                ->where('is_settled', 0)
                ->orderBy('owed_amount', 'asc')
                ->get()
                ->each(function ($split) {
                    $split->update(['is_settled' => 1, 'settled_at' => now()]);
                });

            DB::table('group_activity')->insert([
                'group_id'   => $expenseGroup->id,
                'user_id'    => $request->user()->id,
                'type'       => 'settlement',
                'entity_id'  => null,
                'message'    => $request->user()->name . " recorded a payment of ₹{$request->amount}",
                'created_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Settlement recorded']);
    }

    private function calculateSplits(float $total, string $type, array $splits): array
    {
        return match($type) {
            'equal' => collect($splits)->map(fn($s) => [
                'member_id' => $s['member_id'],
                'amount'    => round($total / count($splits), 2),
            ])->toArray(),

            'exact' => collect($splits)->map(fn($s) => [
                'member_id' => $s['member_id'],
                'amount'    => $s['amount'],
            ])->toArray(),

            'percentage' => collect($splits)->map(fn($s) => [
                'member_id'  => $s['member_id'],
                'amount'     => round($total * ($s['percentage'] / 100), 2),
                'percentage' => $s['percentage'],
            ])->toArray(),

            'shares' => (function() use ($total, $splits) {
                $totalShares = array_sum(array_column($splits, 'shares'));
                return collect($splits)->map(fn($s) => [
                    'member_id' => $s['member_id'],
                    'amount'    => round($total * ($s['shares'] / $totalShares), 2),
                    'shares'    => $s['shares'],
                ])->toArray();
            })(),
        };
    }
}