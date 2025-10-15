<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Balance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    // Get all balances for logged-in user
    public function index(Request $request)
    {
        $balances = Balance::where('user_id', $request->user()->id)
            ->orderBy('date_added', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $balances]);
    }

    // Store new balance
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'source' => 'required|string|max:255',
            'date_added' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['balance_id'] = Str::uuid();

        $balance = Balance::create($data);

        return response()->json(['success' => true, 'message' => 'Balance created', 'data' => $balance], 201);
    }

    // Show single balance
    public function show(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json(['success' => false, 'message' => 'Balance not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $balance]);
    }

    // Update balance
    public function update(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json(['success' => false, 'message' => 'Balance not found'], 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'source' => 'required|string|max:255',
            'date_added' => 'required|date',
        ]);

        $balance->update($validated);

        return response()->json(['success' => true, 'message' => 'Balance updated', 'data' => $balance]);
    }

    // Delete balance
    public function destroy(Request $request, $id)
    {
        $balance = Balance::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$balance) {
            return response()->json(['success' => false, 'message' => 'Balance not found'], 404);
        }

        $balance->delete();
        return response()->json(['success' => true, 'message' => 'Balance deleted']);
    }

}
