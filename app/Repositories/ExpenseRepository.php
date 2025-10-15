<?php

namespace App\Repositories;

use App\Models\ExpenseCore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseRepository
{
    protected $model;

    public function __construct(ExpenseCore $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find($id): ?ExpenseCore
    {
        return $this->model->find($id);
    }

    public function findByExpenseId($expenseId): ?ExpenseCore
    {
        return $this->model->where('expense_id', $expenseId)->first();
    }

    public function create(array $data): ExpenseCore
    {
        return $this->model->create($data);
    }

    public function update($id, array $data): bool
    {
        $expense = $this->find($id);
        return $expense ? $expense->update($data) : false;
    }

    public function delete($id): bool
    {
        $expense = $this->find($id);
        return $expense ? $expense->delete() : false;
    }

    public function getByGroup($groupId, $perPage = 15): LengthAwarePaginator
    {
        return $this->model->byGroup($groupId)
            ->with(['payer', 'expenseShares.user'])
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getByPayer($payerId, $perPage = 15): LengthAwarePaginator
    {
        return $this->model->byPayer($payerId)
            ->with(['group', 'expenseShares.user'])
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getActiveByGroup($groupId): Collection
    {
        return $this->model->byGroup($groupId)->active()->get();
    }

    public function getSettledByGroup($groupId): Collection
    {
        return $this->model->byGroup($groupId)->settled()->get();
    }

    public function getByDateRange($startDate, $endDate, $groupId = null)
    {
        $query = $this->model->whereBetween('expense_date', [$startDate, $endDate]);

        if ($groupId) {
            $query->byGroup($groupId);
        }

        return $query->get();
    }

    public function getTotalAmountByGroup($groupId): float
    {
        return $this->model->byGroup($groupId)->sum('amount');
    }

    public function getUnsettledExpensesByGroup($groupId): Collection
    {
        return $this->model->byGroup($groupId)
            ->where('status', '!=', 'settled')
            ->get()
            ->filter(function ($expense) {
                return !$expense->isSettled();
            });
    }

    public function search($query, $groupId = null)
    {
        $searchQuery = $this->model->where(function ($q) use ($query) {
            $q->where('title', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%");
        });

        if ($groupId) {
            $searchQuery->byGroup($groupId);
        }

        return $searchQuery->get();
    }
}
