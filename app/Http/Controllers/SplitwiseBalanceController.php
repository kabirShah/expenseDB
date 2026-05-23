<?php

namespace App\Http\Controllers;

use App\Models\SplitwiseGroup;
use App\Services\SplitwiseBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SplitwiseBalanceController extends Controller
{
    public function __construct(private readonly SplitwiseBalanceService $balanceService)
    {
        $this->middleware('auth:sanctum');
    }

    public function show(Request $request, int $groupId): JsonResponse
    {
        $group = SplitwiseGroup::query()
            ->whereKey($groupId)
            ->whereHas('members', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->balanceService->balancesForGroup($group),
        ]);
    }
}
