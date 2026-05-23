<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Balance;
use App\Models\Expense;
use Illuminate\Support\Facades\Validator;

class BalanceController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /*
    |--------------------------------------------------------------------------
    | List Balances
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $balances = Balance::where('user_id', $request->user()->id)
            ->orderBy('date_added', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $balances,
            'financial_container' => financialContainer($balances->sum('amount')),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Balance
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'source' => 'required|string|max:255',
            'date_added' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        $balance = Balance::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Balance added successfully',
            'data' => $balance,
            'financial_container' => financialContainer($balance->amount),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Balance
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json([
                'success' => false,
                'message' => 'Balance not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $balance,
            'financial_container' => financialContainer($balance->amount),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Balance
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json([
                'success' => false,
                'message' => 'Balance not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'source' => 'required|string|max:255',
            'date_added' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $balance->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Balance updated successfully',
            'data' => $balance,
            'financial_container' => financialContainer($balance->amount),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Balance
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json([
                'success' => false,
                'message' => 'Balance not found'
            ], 404);
        }

        $balance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Balance deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Balance Summary
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $userId = $request->user()->id;

        $totalBalance = Balance::where('user_id', $userId)->sum('amount');

        $totalExpenses = Expense::where('user_id', $userId)
            ->where('status', 'active')
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_added_balance' => $totalBalance,
                'total_expenses' => $totalExpenses,
                'available_balance' => $totalBalance - $totalExpenses,
                'financial_container' => financialContainer($totalBalance - $totalExpenses),
            ],
            'financial_container' => financialContainer($totalBalance - $totalExpenses),
        ]);
    }
}
