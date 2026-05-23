<?php

namespace App\Services;

use App\Models\Category;
use App\Models\RoutineExpense;
use App\Models\Wallet;
use Illuminate\Support\Facades\Validator;

class RoutineExpenseService
{
    public function list(int $userId)
    {
        return RoutineExpense::query()
            ->where('user_id', $userId)
            ->with(['category', 'wallet'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (RoutineExpense $routine) => $this->appendNextDueDate($routine));
    }

    public function create(int $userId, array $payload): RoutineExpense
    {
        $data = $this->validate($userId, $payload);
        $data['user_id'] = $userId;
        $data['status'] = $data['status'] ?? 'active';

        return $this->appendNextDueDate(RoutineExpense::create($data)->load(['category', 'wallet']));
    }

    public function update(int $userId, RoutineExpense $routine, array $payload): RoutineExpense
    {
        $this->authorize($userId, $routine);

        $data = $this->validate($userId, $payload, true);
        $routine->update($data);

        return $this->appendNextDueDate($routine->fresh(['category', 'wallet']));
    }

    public function delete(int $userId, RoutineExpense $routine): void
    {
        $this->authorize($userId, $routine);
        $routine->delete();
    }

    public function toggle(int $userId, RoutineExpense $routine): RoutineExpense
    {
        $this->authorize($userId, $routine);
        $routine->update(['status' => $routine->status === 'active' ? 'inactive' : 'active']);

        return $this->appendNextDueDate($routine->fresh(['category', 'wallet']));
    }

    private function validate(int $userId, array $payload, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $rules = [
            'title' => [$required, 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'category_id' => [$required, 'integer'],
            'wallet_id' => [$required, 'integer'],
            'frequency' => [$required, 'in:daily,weekly,monthly'],
            'start_date' => [$required, 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:active,inactive'],
            'reminder' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];

        $validator = Validator::make($payload, $rules);
        $validator->after(function ($validator) use ($userId, $payload, $partial) {
            if ((!$partial || array_key_exists('wallet_id', $payload)) && !$this->userWallet($userId, $payload['wallet_id'] ?? null)) {
                $validator->errors()->add('wallet_id', 'Wallet not found.');
            }

            if ((!$partial || array_key_exists('category_id', $payload)) && !$this->accessibleCategory($userId, $payload['category_id'] ?? null)) {
                $validator->errors()->add('category_id', 'Category not found.');
            }
        });

        return $validator->validate();
    }

    private function authorize(int $userId, RoutineExpense $routine): void
    {
        if ((int) $routine->user_id !== $userId) {
            abort(response()->json(['message' => 'Unauthorized'], 403));
        }
    }

    private function userWallet(int $userId, mixed $walletId): ?Wallet
    {
        if (!$walletId) {
            return null;
        }

        return Wallet::query()->where('user_id', $userId)->whereKey($walletId)->first();
    }

    private function accessibleCategory(int $userId, mixed $categoryId): ?Category
    {
        if (!$categoryId) {
            return null;
        }

        return Category::query()
            ->whereKey($categoryId)
            ->where(function ($query) use ($userId) {
                $query->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->first();
    }

    private function appendNextDueDate(RoutineExpense $routine): RoutineExpense
    {
        $routine->setAttribute('next_due_date', app(RoutineSchedulerService::class)->nextDueDate($routine)?->toDateString());

        return $routine;
    }
}
