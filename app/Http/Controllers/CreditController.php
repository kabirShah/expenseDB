<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CreditCard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreditController extends Controller
{
public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => CreditCard::where('user_id', $request->user()->id)->get()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|unique:credit_cards,card_number',
            'holder_name' => 'required|string|max:255',
            'expiry_date' => 'required|date',
            'credit_limit' => 'nullable|numeric|min:0',
            'added_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['credit_card_id'] = Str::uuid();
        $data['added_date'] = $data['added_date'] ?? now();

        $card = CreditCard::create($data);

        return response()->json(['success' => true, 'message' => 'Card added', 'data' => $card], 201);
    }

    public function show(Request $request, $id)
    {
        $card = CreditCard::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$card) {
            return response()->json(['success' => false, 'message' => 'Card not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $card]);
    }

    public function update(Request $request, $id)
    {
        $card = CreditCard::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$card) {
            return response()->json(['success' => false, 'message' => 'Card not found'], 404);
        }

        $validated = $request->validate([
            'card_number' => 'required|string|unique:credit_cards,card_number,' . $id,
            'holder_name' => 'required|string|max:255',
            'expiry_date' => 'required|date',
            'credit_limit' => 'nullable|numeric|min:0',
            'added_date' => 'nullable|date',
        ]);

        $card->update($validated);

        return response()->json(['success' => true, 'message' => 'Card updated', 'data' => $card]);
    }

    public function destroy(Request $request, $id)
    {
        $card = CreditCard::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$card) {
            return response()->json(['success' => false, 'message' => 'Card not found'], 404);
        }

        $card->delete();

        return response()->json(['success' => true, 'message' => 'Card deleted']);
    }
}
