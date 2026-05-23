<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\ExpenseSplit;
use App\Models\GroupMember;
use App\Models\Settlement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupExpenseService
{
    public function createGroupExpense(ExpenseGroup $group, int $creatorId, array $data): Expense
    {
        $members = GroupMember::query()
            ->where('group_id', $group->id)
            ->pluck('user_id')
            ->all();

        $participants = collect($data['participants'] ?? [])->map(function (array $participant) {
            return [
                'user_id' => (int) $participant['user_id'],
                'amount' => isset($participant['amount']) ? (float) $participant['amount'] : null,
                'percentage' => isset($participant['percentage']) ? (float) $participant['percentage'] : null,
                'shares' => isset($participant['shares']) ? (float) $participant['shares'] : null,
            ];
        });

        $payers = collect($data['payers'] ?? [])->map(function (array $payer) {
            return [
                'user_id' => (int) $payer['user_id'],
                'amount_paid' => (float) $payer['amount_paid'],
            ];
        });

        foreach ($participants as $participant) {
            if (!in_array($participant['user_id'], $members, true)) {
                throw ValidationException::withMessages(['participants' => ['All participants must be group members.']]);
            }
        }

        foreach ($payers as $payer) {
            if (!in_array($payer['user_id'], $members, true)) {
                throw ValidationException::withMessages(['payers' => ['All payers must be group members.']]);
            }
        }

        if ($participants->isEmpty() || $payers->isEmpty()) {
            throw ValidationException::withMessages([
                'participants' => ['Participants are required.'],
                'payers' => ['Payers are required.'],
            ]);
        }

        $totalAmount = round((float) $data['amount'], 2);
        $splitRows = $this->buildSplitRows($totalAmount, $data['split_type'], $participants, $payers, $data['items'] ?? []);
        $duplicateKey = $this->duplicateKey($creatorId, $data);

        if ($duplicateKey && Schema::hasColumn('expenses', 'duplicate_key')) {
            $exists = Expense::query()->where('duplicate_key', $duplicateKey)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'expense' => ['A matching shared expense already exists.'],
                ]);
            }
        }

        return DB::transaction(function () use ($group, $creatorId, $data, $splitRows, $totalAmount, $duplicateKey) {
            $expenseAttributes = [
                'user_id' => $creatorId,
                'group_id' => $group->id,
                'source_type' => 'group',
                'split_type' => $data['split_type'],
                'category_id' => $data['category_id'] ?? null,
                'category_name' => $data['category_name'] ?? null,
                'merchant_name' => $data['merchant_name'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_type' => $data['payment_method'] ?? 'Cash',
                'description' => $data['description'] ?? $data['title'],
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'date' => $data['expense_date'],
                'expense_date' => $data['expense_date'],
                'notes' => $data['notes'] ?? null,
                'status' => Expense::STATUS_ACTIVE,
                'metadata' => [
                    'title' => $data['title'],
                    'payers' => $data['payers'],
                    'participants' => $data['participants'],
                    'items' => $data['items'] ?? [],
                    'budget_scope' => 'group',
                ],
            ];

            if ($duplicateKey && Schema::hasColumn('expenses', 'duplicate_key')) {
                $expenseAttributes['duplicate_key'] = $duplicateKey;
            }
            if (isset($data['linked_transaction_id']) && Schema::hasColumn('expenses', 'linked_transaction_id')) {
                $expenseAttributes['linked_transaction_id'] = $data['linked_transaction_id'];
            }
            if (Schema::hasColumn('expenses', 'shared_metadata')) {
                $expenseAttributes['shared_metadata'] = [
                    'split_type' => $data['split_type'],
                    'payer_count' => count($data['payers']),
                    'participant_count' => count($data['participants']),
                ];
            }

            $expense = Expense::create($expenseAttributes);

            $splitDetails = collect($splitRows)
                ->map(fn (array $row) => [
                    'user_id' => $row['user_id'],
                    'payer_user_id' => $row['payer_user_id'],
                    'amount_owed' => $row['amount_owed'],
                    'amount_paid' => $row['amount_paid'],
                    'shares' => $row['shares'],
                    'percentage' => $row['percentage'],
                    'status' => $row['amount_paid'] >= $row['amount_owed'] ? 'settled' : 'pending',
                ])
                ->values()
                ->all();

            foreach ($splitRows as $row) {
                $splitAttributes = [
                    'expense_id' => $expense->id,
                    'group_id' => $group->id,
                    'paid_by' => $row['payer_user_id'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'total_amount' => $totalAmount,
                    'split_details' => $splitDetails,
                    'expense_date' => $data['expense_date'],
                    'category' => $data['category_name'] ?? null,
                    'receipt_images' => null,
                    'user_id' => $row['user_id'],
                    'payer_user_id' => $row['payer_user_id'],
                    'amount_owed' => $row['amount_owed'],
                    'amount_paid' => $row['amount_paid'],
                    'shares' => $row['shares'],
                    'percentage' => $row['percentage'],
                    'split_type' => $data['split_type'],
                    'status' => $row['amount_paid'] >= $row['amount_owed'] ? 'settled' : 'pending',
                    'is_settled' => $row['amount_paid'] >= $row['amount_owed'],
                ];

                if (Schema::hasColumn('expense_splits', 'split_basis')) {
                    $splitAttributes['split_basis'] = $row['split_basis'] ?? null;
                }
                if (Schema::hasColumn('expense_splits', 'itemized_details')) {
                    $splitAttributes['itemized_details'] = $row['itemized_details'] ?? null;
                }

                ExpenseSplit::create($splitAttributes);
            }

            return $expense->load(['splits.user', 'group']);
        });
    }

    public function balancesForGroup(ExpenseGroup $group): array
    {
        if (!$this->hasExpenseSplitBalanceColumns()) {
            return [
                'balances' => [],
                'simplified' => [],
            ];
        }

        $memberIds = GroupMember::query()
            ->where('group_id', $group->id)
            ->pluck('user_id');

        $balances = [];
        foreach ($memberIds as $memberId) {
            $owed = (float) ExpenseSplit::query()
                ->where('group_id', $group->id)
                ->where('user_id', $memberId)
                ->sum('amount_owed');

            $paid = (float) ExpenseSplit::query()
                ->where('group_id', $group->id)
                ->where('payer_user_id', $memberId)
                ->sum('amount_paid');

            $settledOut = (float) Settlement::query()
                ->where('group_id', $group->id)
                ->where('from_user_id', $memberId)
                ->sum('settled_amount');

            $settledIn = (float) Settlement::query()
                ->where('group_id', $group->id)
                ->where('to_user_id', $memberId)
                ->sum('settled_amount');

            $balances[$memberId] = round(($paid + $settledIn) - ($owed + $settledOut), 2);
        }

        return [
            'balances' => $balances,
            'simplified' => $this->simplifyDebts($balances),
        ];
    }

    public function simplifyDebts(array $balances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $userId => $balance) {
            if ($balance > 0.009) {
                $creditors[] = ['user_id' => $userId, 'amount' => round($balance, 2)];
            } elseif ($balance < -0.009) {
                $debtors[] = ['user_id' => $userId, 'amount' => round(abs($balance), 2)];
            }
        }

        $settlements = [];
        $i = 0;
        $j = 0;

        while (isset($debtors[$i], $creditors[$j])) {
            $amount = min($debtors[$i]['amount'], $creditors[$j]['amount']);

            $settlements[] = [
                'from_user_id' => $debtors[$i]['user_id'],
                'to_user_id' => $creditors[$j]['user_id'],
                'amount' => round($amount, 2),
            ];

            $debtors[$i]['amount'] = round($debtors[$i]['amount'] - $amount, 2);
            $creditors[$j]['amount'] = round($creditors[$j]['amount'] - $amount, 2);

            if ($debtors[$i]['amount'] <= 0.009) {
                $i++;
            }
            if ($creditors[$j]['amount'] <= 0.009) {
                $j++;
            }
        }

        return $settlements;
    }

    private function buildSplitRows(float $totalAmount, string $splitType, Collection $participants, Collection $payers, array $items = []): array
    {
        $calculatedSplits = app(SharedSplitCalculationService::class)
            ->calculate($totalAmount, $splitType, $participants->all(), $items);
        $owedMap = collect($calculatedSplits)->mapWithKeys(fn ($split) => [$split['user_id'] => $split['amount_owed']])->all();

        $paidMap = [];
        foreach ($payers as $payer) {
            $paidMap[$payer['user_id']] = ($paidMap[$payer['user_id']] ?? 0) + round((float) $payer['amount_paid'], 2);
        }

        if (round(array_sum($owedMap), 2) !== round($totalAmount, 2)) {
            throw ValidationException::withMessages(['participants' => ['Split total does not match expense amount.']]);
        }

        if (round(array_sum($paidMap), 2) !== round($totalAmount, 2)) {
            throw ValidationException::withMessages(['payers' => ['Paid total does not match expense amount.']]);
        }

        $primaryPayerId = array_key_first($paidMap);
        $rows = [];
        foreach ($owedMap as $userId => $amountOwed) {
            $rows[] = [
                'user_id' => $userId,
                'payer_user_id' => $primaryPayerId,
                'amount_owed' => round($amountOwed, 2),
                'amount_paid' => round($paidMap[$userId] ?? 0, 2),
                'shares' => $participants->firstWhere('user_id', $userId)['shares'] ?? null,
                'percentage' => $participants->firstWhere('user_id', $userId)['percentage'] ?? null,
                'split_basis' => $participants->firstWhere('user_id', $userId),
                'itemized_details' => $splitType === 'item_based' || $splitType === 'itemized' || $splitType === 'item' ? $items : null,
            ];
        }

        return $rows;
    }

    private function duplicateKey(int $creatorId, array $data): ?string
    {
        $reference = $data['transaction_reference'] ?? $data['bank_reference'] ?? $data['linked_transaction_id'] ?? null;
        $parts = [
            $creatorId,
            round((float) ($data['amount'] ?? 0), 2),
            strtolower(trim((string) ($data['merchant_name'] ?? $data['title'] ?? ''))),
            substr((string) ($data['expense_date'] ?? now()->toDateString()), 0, 10),
            strtolower(trim((string) ($data['payment_method'] ?? ''))),
            $reference,
        ];

        return Str::of(implode('|', $parts))->isNotEmpty()
            ? hash('sha256', implode('|', $parts))
            : null;
    }

    private function hasExpenseSplitBalanceColumns(): bool
    {
        return Schema::hasTable('expense_splits')
            && Schema::hasColumn('expense_splits', 'group_id')
            && Schema::hasColumn('expense_splits', 'user_id')
            && Schema::hasColumn('expense_splits', 'payer_user_id')
            && Schema::hasColumn('expense_splits', 'amount_owed')
            && Schema::hasColumn('expense_splits', 'amount_paid');
    }
}
