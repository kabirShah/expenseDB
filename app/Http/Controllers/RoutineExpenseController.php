<?php

namespace App\Http\Controllers;

use App\Models\RoutineExpense;
use App\Services\RoutineExpenseService;
use Illuminate\Http\Request;

class RoutineExpenseController extends Controller
{
    public function __construct(private readonly RoutineExpenseService $service)
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->list($request->user()->id),
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Routine expense created',
            'data' => $this->service->create($request->user()->id, $request->all()),
        ], 201);
    }

    public function update(Request $request, RoutineExpense $routineExpense)
    {
        return response()->json([
            'success' => true,
            'message' => 'Routine expense updated',
            'data' => $this->service->update($request->user()->id, $routineExpense, $request->all()),
        ]);
    }

    public function destroy(Request $request, RoutineExpense $routineExpense)
    {
        $this->service->delete($request->user()->id, $routineExpense);

        return response()->json([
            'success' => true,
            'message' => 'Routine expense deleted',
        ]);
    }

    public function toggle(Request $request, RoutineExpense $routineExpense)
    {
        return response()->json([
            'success' => true,
            'message' => 'Routine expense status updated',
            'data' => $this->service->toggle($request->user()->id, $routineExpense),
        ]);
    }
}
