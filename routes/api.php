<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Controllers
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCoreController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DebitController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\MultiExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ExpenseSplitController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParserEventController;
use App\Http\Controllers\PaymentProviderController;
use App\Http\Controllers\ExpenseSuggestionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UserPreferenceController;
use App\Models\Category;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::post('/google-login', [SocialAuthController::class, 'login']);

Route::get('/test-email', function () {
    Mail::raw('This is a test email from Pocket Money App', function ($message) {
        $message->to('test@example.com')->subject('Test Email');
    });

    return response()->json(['message' => 'Email sent']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (AUTH REQUIRED)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    | User
    */
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    | Preferences
    */
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::post('/preferences', [UserPreferenceController::class, 'store']);

    /*
    | Settings
    */
    Route::prefix('settings')->group(function () {
        Route::get('/', [AuthController::class, 'getSettings']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        Route::post('/update-notifications', [AuthController::class, 'updateNotifications']);
        Route::post('/update-security', [AuthController::class, 'updateSecurity']);
        Route::post('/support', [AuthController::class, 'supportRequest']);
    });

    /*
    | Dashboard
    */
    Route::get('/dashboard', [DashboardController::class, 'index']);

    /*
    | Analytics
    */
    Route::prefix('analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'graphs']);
        Route::get('/year-wise-expenses', [AnalyticsController::class, 'yearWiseExpenses']);
        Route::get('/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
        Route::get('/balance-trends', [AnalyticsController::class, 'balanceTrends']);
        Route::get('/transactions', [AnalyticsController::class, 'transactionsGraphs']);
        Route::get('/multi-transactions', [AnalyticsController::class, 'multiTransactionsGraphs']);
    });

    /*
    | Categories
    */
    Route::get('/categories', function () {
        return Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    });

    /*
    | Expenses
    */
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::post('/bulk', [ExpenseController::class, 'bulkStore']);
        Route::get('/{id}', [ExpenseController::class, 'show']);
        Route::put('/{id}', [ExpenseController::class, 'update']);
        Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    });

    Route::apiResource('multi-expenses', MultiExpenseController::class);
    Route::apiResource('balances', BalanceController::class);
    Route::apiResource('credit-cards', CreditController::class);
    Route::apiResource('debit-cards', DebitController::class);
    Route::apiResource('transactions', TransactionController::class);

    /*
    | Receipts & Invoices
    */
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('receipts', ReceiptController::class);

    /*
    | Notifications
    */
    Route::apiResource('notifications', NotificationController::class);

    /*
    | Expense Suggestions
    */
    Route::prefix('expense-suggestions')->group(function () {
        Route::get('/', [ExpenseSuggestionController::class, 'index']);
        Route::post('/{id}/accept', [ExpenseSuggestionController::class, 'accept']);
        Route::put('/{id}/dismiss', [ExpenseSuggestionController::class, 'dismiss']);
    });

});
