<?php

namespace App\Http\Controllers;

use App\Services\GroupExpenseService;
use App\Services\SharedSplitCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedSplitController extends Controller
{
    public function __construct(
        private readonly SharedSplitCalculationService $splitCalculator,
        private readonly GroupExpenseService $balanceService
    ) {
        $this->middleware('auth:sanctum');
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'split_type' => 'required|in:equal,exact,percentage,shares,share,custom,item,itemized,item_based',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|integer|exists:users,id',
            'participants.*.amount' => 'nullable|numeric|min:0',
            'participants.*.percentage' => 'nullable|numeric|min:0|max:100',
            'participants.*.shares' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.amount' => 'required_with:items|numeric|min:0.01',
            'items.*.user_ids' => 'required_with:items|array|min:1',
            'items.*.user_ids.*' => 'integer|exists:users,id',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'amount' => round((float) $data['amount'], 2),
                'split_type' => $data['split_type'],
                'splits' => $this->splitCalculator->calculate(
                    (float) $data['amount'],
                    $data['split_type'],
                    $data['participants'],
                    $data['items'] ?? []
                ),
            ],
        ]);
    }

    public function simplify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'balances' => 'required|array',
            'balances.*' => 'numeric',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->balanceService->simplifyDebts($data['balances']),
        ]);
    }
}
