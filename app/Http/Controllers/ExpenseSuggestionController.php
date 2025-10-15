<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseSuggestion;
use App\Services\ExpenseSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseSuggestionController extends Controller
{
    protected $suggestionService;

    public function __construct(ExpenseSuggestionService $suggestionService)
    {
        $this->middleware('auth:sanctum');
        $this->suggestionService = $suggestionService;
    }

    /**
     * Get list of expense suggestions for authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $userId = Auth::id();

        // Generate new suggestions if needed
        $this->suggestionService->generateSuggestions($userId);

        // Get unseen suggestions
        $suggestions = $this->suggestionService->getUnseenSuggestions($userId);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }

    /**
     * Accept a suggestion and create an expense.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function accept($id)
    {
        $userId = Auth::id();

        $suggestion = ExpenseSuggestion::where('id', $id)
            ->where('user_id', $userId)
            ->where('is_shown', false)
            ->first();

        if (!$suggestion) {
            return response()->json([
                'success' => false,
                'message' => 'Suggestion not found or already processed.',
            ], 404);
        }

        // Create new expense from suggestion
        $expense = Expense::create([
            'user_id' => $userId,
            'expense_id' => uniqid('exp_'),
            'category' => $suggestion->category,
            'transaction_type' => 'Cash', // Default, can be updated
            'description' => $suggestion->description,
            'amount' => $suggestion->suggested_amount,
            'date' => now(),
            'status' => 'active',
        ]);

        // Mark suggestion as shown
        $this->suggestionService->markAsShown($id, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Suggestion accepted and expense created.',
            'data' => $expense,
        ]);
    }

    /**
     * Dismiss a suggestion.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function dismiss($id)
    {
        $userId = Auth::id();

        $success = $this->suggestionService->markAsShown($id, $userId);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Suggestion not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Suggestion dismissed.',
        ]);
    }
}
