<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $wallets]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:cash,bank,upi,credit_card,debit_card,other',
            'currency' => 'nullable|string|size:3',
            'balance' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:10',
            'icon' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        $wallet = DB::transaction(function () use ($request, $data) {
            $wallet = Wallet::create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'currency' => strtoupper($data['currency'] ?? 'INR'),
                'balance' => $data['balance'] ?? 0,
                'color' => $data['color'] ?? null,
                'icon' => $data['icon'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);

            if ($wallet->is_default) {
                Wallet::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $wallet->id)
                    ->update(['is_default' => false]);
            }

            if ((float) ($data['balance'] ?? 0) > 0) {
                $this->recordAddedBalance(
                    $request->user()->id,
                    'Wallet opening balance - ' . $wallet->name,
                    (float) $data['balance']
                );
            }

            return $wallet;
        });

        return response()->json([
            'success' => true,
            'data' => $wallet,
            'financial_container' => financialContainer($wallet->balance),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::query()->where('user_id', $request->user()->id)->find($id);
        if (!$wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $wallet,
            'financial_container' => financialContainer($wallet->balance),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::query()->where('user_id', $request->user()->id)->find($id);
        if (!$wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'type' => 'sometimes|in:cash,bank,upi,credit_card,debit_card,other',
            'currency' => 'sometimes|string|size:3',
            'balance' => 'sometimes|numeric|min:0',
            'color' => 'sometimes|nullable|string|max:10',
            'icon' => 'sometimes|nullable|string|max:50',
            'is_default' => 'sometimes|boolean',
        ]);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $wallet->update($data);

        if (($data['is_default'] ?? false) === true) {
            Wallet::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $wallet->id)
                ->update(['is_default' => false]);
        }

        $freshWallet = $wallet->fresh();

        return response()->json([
            'success' => true,
            'data' => $freshWallet,
            'financial_container' => financialContainer($freshWallet->balance),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::query()->where('user_id', $request->user()->id)->find($id);
        if (!$wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found'], 404);
        }

        $wallet->delete();
        return response()->json(['success' => true, 'message' => 'Wallet deleted']);
    }

    public function addBalance(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::query()->where('user_id', $request->user()->id)->find($id);
        if (!$wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found'], 404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $wallet = DB::transaction(function () use ($request, $wallet, $data) {
            $amount = (float) $data['amount'];

            $this->recordAddedBalance(
                $request->user()->id,
                'Wallet top-up - ' . $wallet->name,
                $amount
            );

            return $this->applyWalletBalanceChange($wallet, $amount, 'credit');
        });

        return response()->json([
            'success' => true,
            'message' => 'Balance added successfully',
            'data' => $wallet,
            'financial_container' => financialContainer($wallet->balance),
        ]);
    }
}
