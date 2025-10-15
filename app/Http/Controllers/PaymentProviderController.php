<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentProvider;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentProviderController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->middleware('auth:sanctum');
        $this->paymentService = $paymentService;
    }

    // Get all active payment providers
    public function index(Request $request)
    {
        $providers = PaymentProvider::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $providers]);
    }

    // Get payment provider by type
    public function byType(Request $request, $type)
    {
        $validTypes = ['upi', 'bank', 'wallet', 'card_network'];
        
        if (!in_array($type, $validTypes)) {
            return response()->json(['success' => false, 'message' => 'Invalid provider type'], 400);
        }

        $providers = PaymentProvider::where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $providers]);
    }

    // Get single payment provider
    public function show(Request $request, $id)
    {
        $provider = PaymentProvider::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Payment provider not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $provider]);
    }

    // Calculate transaction fee for a provider
    public function calculateFee(Request $request, $id)
    {
        $provider = PaymentProvider::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Payment provider not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $amount = $validator->validated()['amount'];

        if (!$provider->isAmountWithinLimits($amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Amount is outside provider limits',
                'min_amount' => $provider->min_transaction_amount,
                'max_amount' => $provider->max_transaction_amount
            ], 400);
        }

        $fee = $provider->getTransactionFee($amount);
        $total = $amount + $fee;

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider->name,
                'amount' => $amount,
                'fee_percentage' => $provider->transaction_fee_percentage,
                'fee_amount' => $fee,
                'total_amount' => $total,
                'currency' => 'INR'
            ]
        ]);
    }

    // Check if provider supports a feature
    public function checkFeature(Request $request, $id)
    {
        $provider = PaymentProvider::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Payment provider not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'feature' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $feature = $validator->validated()['feature'];
        $supports = $provider->supportsFeature($feature);

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider->name,
                'feature' => $feature,
                'supported' => $supports
            ]
        ]);
    }

    // Get providers that support a specific feature
    public function byFeature(Request $request, $feature)
    {
        $providers = PaymentProvider::where('is_active', true)
            ->whereJsonContains('supported_features', $feature)
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => $providers]);
    }

    // Validate transaction amount against provider limits
    public function validateAmount(Request $request, $id)
    {
        $provider = PaymentProvider::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json(['success' => false, 'message' => 'Payment provider not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $amount = $validator->validated()['amount'];
        $valid = $provider->isAmountWithinLimits($amount);

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $provider->name,
                'amount' => $amount,
                'is_valid' => $valid,
                'min_amount' => $provider->min_transaction_amount,
                'max_amount' => $provider->max_transaction_amount,
                'message' => $valid ? 'Amount is within limits' : 'Amount is outside provider limits'
            ]
        ]);
    }

    // Initiate payment with a provider
    public function initiatePayment(Request $request, $providerName)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string|max:255',
            'metadata' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = array_merge($validator->validated(), [
            'user_id' => $request->user()->id,
            'currency' => $validator->validated()['currency'] ?? 'INR'
        ]);

        $result = $this->paymentService->initiatePayment($providerName, $data);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    // Verify payment status
    public function verifyPayment(Request $request, $providerName, $transactionId)
    {
        $result = $this->paymentService->verifyPayment($providerName, $transactionId);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    // Handle payment callback/webhook
    public function handleCallback(Request $request, $providerName)
    {
        $callbackData = $request->all();
        $success = $this->paymentService->handleCallback($providerName, $callbackData);

        if (!$success) {
            return response()->json(['success' => false, 'message' => 'Callback processing failed'], 400);
        }

        return response()->json(['success' => true, 'message' => 'Callback processed successfully']);
    }

    // Get supported payment methods for a provider
    public function getSupportedMethods(Request $request, $providerName)
    {
        $methods = $this->paymentService->getSupportedMethods($providerName);

        return response()->json([
            'success' => true,
            'data' => [
                'provider' => $providerName,
                'supported_methods' => $methods
            ]
        ]);
    }
}
