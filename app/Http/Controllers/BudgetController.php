<?php

namespace App\Http\Controllers;

use App\Models\BudgetPlan;
use App\Services\Budget\BudgetInsightService;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetInsightService $budgetInsightService)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        return response()->json(
            $this->budgetInsightService->summaryForUser($request->user()->id)
        );
    }

    public function summary(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->budgetInsightService->summaryForUser($request->user()->id),
        ]);
    }

    public function alerts(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->budgetInsightService->syncAlertsForUser($request->user()->id),
        ]);
    }

    public function predictions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->budgetInsightService->predictionsForUser($request->user()->id),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'period' => 'required|in:weekly,monthly,yearly,custom',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'category_id' => 'nullable|exists:categories,id',
            'alert_at' => 'nullable|integer|between:1,100',
        ]);

        $budget = BudgetPlan::create([
            ...$data,
            'user_id' => $request->user()->id,
            'alert_at' => $data['alert_at'] ?? 80,
            'is_active' => true,
        ]);

        return response()->json($budget->load('category'), 201);
    }

    public function show(Request $request, BudgetPlan $budget)
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($budget->load('category'));
    }

    public function update(Request $request, BudgetPlan $budget)
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'amount' => 'sometimes|numeric|min:1',
            'period' => 'sometimes|in:weekly,monthly,yearly,custom',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'alert_at' => 'sometimes|integer|between:1,100',
            'is_active' => 'sometimes|boolean',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date',
        ]);

        $budget->update($data);

        return response()->json($budget->fresh('category'));
    }

    public function destroy(Request $request, BudgetPlan $budget)
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget deleted']);
    }
}
