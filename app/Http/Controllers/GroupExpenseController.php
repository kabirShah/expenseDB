<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use App\Models\GroupExpense;
use App\Models\ExpenseContribution;
use App\Models\ExpenseShare;
use App\Services\SplitCalculatorService;

class GroupExpenseController extends Controller
{
    protected $splitter;

    public function __construct(SplitCalculatorService $splitter)
    {
        $this->splitter = $splitter;
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'title' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'split_type' => 'required|in:equal,custom,weight',
            'participants' => 'required|array|min:1',
            'participants.*' => 'required|integer', // member_id
            'contributions' => 'sometimes|array',
            'contributions.*.member_id' => 'required_with:contributions|integer',
            'contributions.*.amount_paid' => 'required_with:contributions|numeric|min:0',
            // if custom provided, we expect shares array or custom_shares mapping on body
        ]);

        $groupId = $request->input('group_id');
        $title = $request->input('title');
        $totalAmount = (float) $request->input('total_amount');
        $splitType = $request->input('split_type');
        $participants = $request->input('participants'); // array of member ids

        // Optional inputs
        $customShares = $request->input('custom_shares', null); // associative member_id => amount
        $weights = $request->input('weights', null); // associative member_id => weight
        $contributionsList = $request->input('contributions', []);

        DB::beginTransaction();

        try {
            $expense = GroupExpense::create([
                'expense_uuid' => Str::uuid(),
                'group_id' => $groupId,
                'created_by' => $request->user()->id,
                'title' => $title,
                'total_amount' => $totalAmount,
                'split_type' => $splitType,
                'date' => now(),
                'note' => $request->input('note', null)
            ]);

            // Persist contributions
            $contributionsMap = []; // member_id => amount_paid
            if (!empty($contributionsList)) {
                foreach ($contributionsList as $c) {
                    $memberId = (int)$c['member_id'];
                    $amountPaid = (float)$c['amount_paid'];

                    ExpenseContribution::create([
                        'expense_id' => $expense->id,
                        'member_id' => $memberId,
                        'amount_paid' => $amountPaid
                    ]);

                    // accumulate if multiple entries for a member
                    if (!isset($contributionsMap[$memberId])) $contributionsMap[$memberId] = 0.0;
                    $contributionsMap[$memberId] += $amountPaid;
                }
            }

            // Compute shares via split service
            $options = [];
            if ($splitType === 'custom') {
                $options['custom_shares'] = $customShares ?? [];
            } elseif ($splitType === 'weight') {
                $options['weights'] = $weights ?? [];
            }

            $shares = $this->splitter->computeShares($totalAmount, $participants, $splitType, $options);

            // Persist shares
            foreach ($shares as $memberId => $shareAmt) {
                ExpenseShare::create([
                    'expense_id' => $expense->id,
                    'member_id' => $memberId,
                    'share_amount' => $shareAmt,
                    'amount_settled' => 0,
                    'status' => 'pending'
                ]);
            }

            DB::commit();

            // After commit we compute net balances and suggested settlements
            $nets = $this->splitter->computeNetBalances($contributionsMap, $shares);
            $suggested = $this->splitter->minimizeTransactions($nets);

            return response()->json([
                'success' => true,
                'message' => 'Expense created and shares computed.',
                'data' => [
                    'expense' => $expense,
                    'shares' => $shares,
                    'contributions' => $contributionsMap,
                    'net_balances' => $nets,
                    'suggested_settlements' => $suggested
                ]
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Expense store failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to create expense', 'error' => $e->getMessage()], 500);
        }
    }
}
