<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Split;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SplitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Get all splits for logged-in user
    public function index(Request $request)
    {
        $splits = Split::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $splits]);
    }

    // Store new split
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'participants' => 'required|array',
            'split_type' => 'required|in:equal,percentage,custom',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['split_id'] = Str::uuid();

        $split = Split::create($data);

        return response()->json(['success' => true, 'message' => 'Split created', 'data' => $split], 201);
    }

    // Show single split
    public function show(Request $request, $id)
    {
        $split = Split::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$split) {
            return response()->json(['success' => false, 'message' => 'Split not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $split]);
    }

    // Update split
    public function update(Request $request, $id)
    {
        $split = Split::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$split) {
            return response()->json(['success' => false, 'message' => 'Split not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'participants' => 'required|array',
            'split_type' => 'required|in:equal,percentage,custom',
            'description' => 'nullable|string',
        ]);

        $split->update($validated);

        return response()->json(['success' => true, 'message' => 'Split updated', 'data' => $split]);
    }

    // Delete split
    public function destroy(Request $request, $id)
    {
        $split = Split::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$split) {
            return response()->json(['success' => false, 'message' => 'Split not found'], 404);
        }

        $split->delete();
        return response()->json(['success' => true, 'message' => 'Split deleted']);
    }

    // Calculate split amounts
    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_amount' => 'required|numeric|min:0',
            'split_type' => 'required|in:equal,percentage,custom',
            'participants' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $totalAmount = $data['total_amount'];
        $splitType = $data['split_type'];
        $participants = $data['participants'];

        $calculated = $this->calculateSplitAmounts($totalAmount, $splitType, $participants);

        return response()->json(['success' => true, 'data' => $calculated]);
    }

    // Settle a participant's share
    public function settle(Request $request, $id, $participantId)
    {
        $split = Split::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$split) {
            return response()->json(['success' => false, 'message' => 'Split not found'], 404);
        }

        $validated = $request->validate([
            'amount_paid' => 'required|numeric|min:0',
        ]);

        $participants = $split->participants;
        foreach ($participants as &$participant) {
            if ($participant['user_id'] == $participantId) {
                $participant['amount_paid'] += $validated['amount_paid'];
                $participant['status'] = $participant['amount_paid'] >= $participant['amount_owed'] ? 'settled' : 'pending';
            }
        }

        $split->participants = $participants;
        $split->save();

        return response()->json(['success' => true, 'message' => 'Participant settled', 'data' => $split]);
    }

    // Get split summary
    public function summary(Request $request, $id)
    {
        $split = Split::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$split) {
            return response()->json(['success' => false, 'message' => 'Split not found'], 404);
        }

        $summary = $this->generateSplitSummary($split);

        return response()->json(['success' => true, 'data' => $summary]);
    }

    private function calculateSplitAmounts($totalAmount, $splitType, $participants)
    {
        $result = [];
        $remainingAmount = $totalAmount;

        switch ($splitType) {
            case 'equal':
                $share = $totalAmount / count($participants);
                foreach ($participants as $participant) {
                    $result[] = [
                        'user_id' => $participant['user_id'],
                        'amount_owed' => round($share, 2),
                        'amount_paid' => 0,
                        'status' => 'pending'
                    ];
                    $remainingAmount -= $share;
                }
                // Distribute remaining amount due to rounding
                if ($remainingAmount > 0) {
                    $result[0]['amount_owed'] += $remainingAmount;
                }
                break;

            case 'percentage':
                $totalPercentage = array_sum(array_column($participants, 'percentage'));
                if ($totalPercentage != 100) {
                    throw new \Exception('Total percentage must be 100%');
                }
                foreach ($participants as $participant) {
                    $amount = ($totalAmount * $participant['percentage']) / 100;
                    $result[] = [
                        'user_id' => $participant['user_id'],
                        'amount_owed' => round($amount, 2),
                        'amount_paid' => 0,
                        'status' => 'pending'
                    ];
                }
                break;

            case 'custom':
                $totalCustom = array_sum(array_column($participants, 'amount'));
                if ($totalCustom != $totalAmount) {
                    throw new \Exception('Total custom amounts must equal the total amount');
                }
                foreach ($participants as $participant) {
                    $result[] = [
                        'user_id' => $participant['user_id'],
                        'amount_owed' => $participant['amount'],
                        'amount_paid' => 0,
                        'status' => 'pending'
                    ];
                }
                break;
        }

        return $result;
    }

    private function generateSplitSummary($split)
    {
        $totalOwed = 0;
        $totalPaid = 0;
        $settledCount = 0;
        $pendingCount = 0;

        foreach ($split->participants as $participant) {
            $totalOwed += $participant['amount_owed'];
            $totalPaid += $participant['amount_paid'];
            
            if ($participant['status'] === 'settled') {
                $settledCount++;
            } else {
                $pendingCount++;
            }
        }

        return [
            'total_amount' => $split->total_amount,
            'total_owed' => $totalOwed,
            'total_paid' => $totalPaid,
            'balance_due' => $totalOwed - $totalPaid,
            'settled_count' => $settledCount,
            'pending_count' => $pendingCount,
            'completion_percentage' => ($totalPaid / $totalOwed) * 100,
            'participants' => $split->participants
        ];
    }
}
