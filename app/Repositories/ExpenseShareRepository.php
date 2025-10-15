<?php

namespace App\Repositories;

use App\Models\ExpenseShare;
use Illuminate\Database\Eloquent\Collection;

class ExpenseShareRepository
{
    protected $model;

    public function __construct(ExpenseShare $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find($id): ?ExpenseShare
    {
        return $this->model->find($id);
    }

    public function findByShareId($shareId): ?ExpenseShare
    {
        return $this->model->where('share_id', $shareId)->first();
    }

    public function create(array $data): ExpenseShare
    {
        return $this->model->create($data);
    }

    public function update($id, array $data): bool
    {
        $share = $this->find($id);
        return $share ? $share->update($data) : false;
    }

    public function delete($id): bool
    {
        $share = $this->find($id);
        return $share ? $share->delete() : false;
    }

    public function getByExpense($expenseId): Collection
    {
        return $this->model->where('expense_id', $expenseId)
            ->with('user')
            ->get();
    }

    public function getByUser($userId): Collection
    {
        return $this->model->byUser($userId)
            ->with(['expense.group', 'expense.payer'])
            ->get();
    }

    public function getPendingByUser($userId): Collection
    {
        return $this->model->byUser($userId)
            ->pending()
            ->with(['expense.group', 'expense.payer'])
            ->get();
    }

    public function getSettledByUser($userId): Collection
    {
        return $this->model->byUser($userId)
            ->settled()
            ->with(['expense.group', 'expense.payer'])
            ->get();
    }

    public function getTotalOwedByUser($userId): float
    {
        return $this->model->byUser($userId)->sum('amount_owed');
    }

    public function getTotalPaidByUser($userId): float
    {
        return $this->model->byUser($userId)->sum('amount_paid');
    }

    public function getBalanceByUser($userId): float
    {
        return $this->getTotalPaidByUser($userId) - $this->getTotalOwedByUser($userId);
    }

    public function addPayment($shareId, $amount): bool
    {
        $share = $this->findByShareId($shareId);
        if (!$share) {
            return false;
        }

        $share->addPayment($amount);
        return true;
    }

    public function settleShare($shareId): bool
    {
        $share = $this->findByShareId($shareId);
        if (!$share) {
            return false;
        }

        $share->markAsSettled();
        return true;
    }
}
