<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Controllers
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| User, Dashboard, Analytics
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;

/*
|--------------------------------------------------------------------------
| Core Expense / Finance Modules
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseCoreController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DebitController;

/*
|--------------------------------------------------------------------------
| Receipts, Invoices
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReceiptController;

/*
|--------------------------------------------------------------------------
| Multi Expense (Bulk WhatsApp Style)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\MultiExpenseController;

/*
|--------------------------------------------------------------------------
| Splitwise Style Group Splits
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ExpenseSplitController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Other Controllers
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParserEventController;
use App\Http\Controllers\PaymentProviderController;
use App\Http\Controllers\ExpenseSuggestionController;
use App\Http\Controllers\CategoryController;
use App\Models\Category;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// routes/api.php
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


Route::post('/google-login', [SocialAuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | User Profile
    |--------------------------------------------------------------------------
    */
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('settings')->group(function () {
        Route::get('/', [AuthController::class, 'getSettings']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        Route::post('/update-notifications', [AuthController::class, 'updateNotifications']);
        Route::post('/update-security', [AuthController::class, 'updateSecurity']);
        Route::post('/support', [AuthController::class, 'supportRequest']);
    });

    /*
    |--------------------------------------------------------------------------
    | Dashboard / Analytics
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('analytics')->group(function () {
        Route::get('/', [AnalyticsController::class, 'graphs']);
        Route::get('/year-wise-expenses', [AnalyticsController::class, 'yearWiseExpenses']);
        Route::get('/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
        Route::get('/balance-trends', [AnalyticsController::class, 'balanceTrends']);
        Route::get('/transactions', [AnalyticsController::class, 'transactionsGraphs']);
        Route::get('/multi-transactions', [AnalyticsController::class, 'multiTransactionsGraphs']);
    });

    /*
    |--------------------------------------------------------------------------
    | Categories API (NEW — used in Angular dropdown)
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', function () {
        return Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    });



    /*
    |--------------------------------------------------------------------------
    | REGULAR EXPENSES CRUD
    |--------------------------------------------------------------------------
    */
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::post('/bulk', [ExpenseController::class, 'bulkStore']);
        Route::get('/{id}', [ExpenseController::class, 'show']);
        Route::put('/{id}', [ExpenseController::class, 'update']);
        Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | MULTI EXPENSE (WhatsApp Bulk Expenses)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('multi-expenses', MultiExpenseController::class);

    /*
    |--------------------------------------------------------------------------
    | BALANCE / CREDIT / DEBIT
    |--------------------------------------------------------------------------
    */
    Route::apiResource('balances', BalanceController::class);
    Route::apiResource('credit-cards', CreditController::class);
    Route::apiResource('debit-cards', DebitController::class);

    /*
    |--------------------------------------------------------------------------
    | TRANSACTIONS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('transactions', TransactionController::class);

    Route::prefix('transactions')->group(function () {
        Route::get('/type/{type}', [TransactionController::class, 'byType']);
        Route::get('/status/{status}', [TransactionController::class, 'byStatus']);
        Route::get('/date-range', [TransactionController::class, 'byDateRange']);
        Route::get('/summary', [TransactionController::class, 'summary']);
        Route::get('/category/{category}', [TransactionController::class, 'byCategory']);
        Route::post('/{id}/status', [TransactionController::class, 'updateStatus']);
    });

    /*
    |--------------------------------------------------------------------------
    | RECEIPTS & INVOICES
    |--------------------------------------------------------------------------
    */
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('receipts', ReceiptController::class);

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('notifications', NotificationController::class);

    Route::prefix('notifications')->group(function () {
        Route::get('/unread/count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/type/{type}', [NotificationController::class, 'byType']);
    });

    /*
    |--------------------------------------------------------------------------
    | PARSER EVENTS (OCR / AI Parsing)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('parser-events', ParserEventController::class);

    /*
    |--------------------------------------------------------------------------
    | PAYMENT PROVIDERS
    |--------------------------------------------------------------------------
    */
    Route::prefix('payment-providers')->group(function () {
        Route::get('/', [PaymentProviderController::class, 'index']);
        Route::get('/{id}', [PaymentProviderController::class, 'show']);
        Route::get('/type/{type}', [PaymentProviderController::class, 'byType']);
        Route::get('/feature/{feature}', [PaymentProviderController::class, 'byFeature']);
        Route::post('/{id}/calculate-fee', [PaymentProviderController::class, 'calculateFee']);
        Route::post('/{id}/check-feature', [PaymentProviderController::class, 'checkFeature']);
        Route::post('/{id}/validate-amount', [PaymentProviderController::class, 'validateAmount']);
    });

    Route::prefix('payments')->group(function () {
        Route::post('/initiate/{providerName}', [PaymentProviderController::class, 'initiatePayment']);
        Route::get('/verify/{providerName}/{transactionId}', [PaymentProviderController::class, 'verifyPayment']);
        Route::post('/callback/{providerName}', [PaymentProviderController::class, 'handleCallback']);
        Route::get('/methods/{providerName}', [PaymentProviderController::class, 'getSupportedMethods']);
    });

    /*
    |--------------------------------------------------------------------------
    | SPLITWISE-LIKE GROUP SYSTEM
    |--------------------------------------------------------------------------
    */
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
    Route::delete('groups/{group}/members', [GroupController::class, 'removeMember']);

    Route::prefix('groups/{group}/expenses')->group(function () {
        Route::get('/', [ExpenseSplitController::class, 'index']);
        Route::post('/', [ExpenseSplitController::class, 'store']);
        Route::get('/{expenseSplit}', [ExpenseSplitController::class, 'show']);
        Route::put('/{expenseSplit}', [ExpenseSplitController::class, 'update']);
        Route::delete('/{expenseSplit}', [ExpenseSplitController::class, 'destroy']);
        Route::post('/{expenseSplit}/payment', [ExpenseSplitController::class, 'updatePayment']);
    });

    Route::prefix('groups/{group}/settlements')->group(function () {
        Route::get('/', [SettlementController::class, 'index']);
        Route::post('/', [SettlementController::class, 'store']);
        Route::get('/{settlement}', [SettlementController::class, 'show']);
        Route::put('/{settlement}', [SettlementController::class, 'update']);
        Route::delete('/{settlement}', [SettlementController::class, 'destroy']);
        Route::get('/suggestions', [SettlementController::class, 'getSuggestions']);
    });

    Route::prefix('groups/{group}')->group(function () {
        Route::get('/balance', [ReportController::class, 'getUserBalance']);
        Route::get('/balances', [ReportController::class, 'getGroupBalances']);
        Route::get('/expense-history', [ReportController::class, 'getExpenseHistory']);
        Route::get('/monthly-report', [ReportController::class, 'getMonthlyReport']);
        Route::get('/settlement-suggestions', [ReportController::class, 'getSettlementSuggestions']);
    });

    Route::get('dashboard/splitwise', [ReportController::class, 'getDashboard']);

    /*
    |--------------------------------------------------------------------------
    | Expense Suggestions
    |--------------------------------------------------------------------------
    */
    Route::prefix('expense-suggestions')->group(function () {
        Route::get('/', [ExpenseSuggestionController::class, 'index']);
        Route::post('/{id}/accept', [ExpenseSuggestionController::class, 'accept']);
        Route::put('/{id}/dismiss', [ExpenseSuggestionController::class, 'dismiss']);
    });

    /*
    |--------------------------------------------------------------------------
    | EXPENSE CORE ENGINE
    |--------------------------------------------------------------------------
    */
    Route::apiResource('expenses-core', ExpenseCoreController::class);

    Route::prefix('expenses-core')->group(function () {
        Route::post('/auto-split-suggestions', [ExpenseCoreController::class, 'getAutoSplitSuggestions']);
        Route::post('/check-duplicates', [ExpenseCoreController::class, 'checkDuplicates']);
        Route::get('/analytics', [ExpenseCoreController::class, 'analytics']);
        Route::post('/{id}/settle', [ExpenseCoreController::class, 'settle']);
    });

});
