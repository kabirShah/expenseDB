<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DebitController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReceiptController;

use App\Http\Controllers\MultiExpenseController;
use App\Http\Controllers\MultiExpenseMemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentProviderController;
use App\Http\Controllers\SplitController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ExpenseCoreController;

// 🟢 Public Routes (No Auth Required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// 🌐 Social Logins
Route::post('/google-login', [SocialAuthController::class, 'login']);

// Route::post('/auth/google', [SocialAuthController::class, 'google']);
// Route::post('/auth/facebook', [SocialAuthController::class, 'facebook']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Analytics API routes
    Route::prefix('analytics')->group(function () {
        Route::get('/year-wise-expenses', [\App\Http\Controllers\AnalyticsController::class, 'yearWiseExpenses']);
        Route::get('/category-breakdown', [\App\Http\Controllers\AnalyticsController::class, 'categoryBreakdown']);
        Route::get('/balance-trends', [\App\Http\Controllers\AnalyticsController::class, 'balanceTrends']);
        Route::get('', [\App\Http\Controllers\AnalyticsController::class, 'graphs']);
        Route::get('/transactions', [\App\Http\Controllers\AnalyticsController::class, 'transactionsGraphs']);
        Route::get('/multi-transactions', [\App\Http\Controllers\AnalyticsController::class, 'multiTransactionsGraphs']);
    });
    
    
    // 🚪 Logout (optional)
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/settings/update-profile', [AuthController::class, 'updateProfile']);

    // Balance Routes
    Route::apiResource('balances', BalanceController::class);

    // Credit Cards
    Route::apiResource('credit-cards', CreditController::class);

    // Debit Cards
    Route::apiResource('debit-cards', DebitController::class);

    // Invoices / Receipts
    Route::apiResource('invoices', InvoiceController::class);
    Route::apiResource('receipts', ReceiptController::class);



    // Multi Expenses
    Route::apiResource('multi-expenses', MultiExpenseController::class);
    Route::post('multi-expenses/{id}/settle-member/{memberId}', [MultiExpenseController::class, 'settleMember']);

    // Parser Events
    Route::apiResource('parser-events', \App\Http\Controllers\ParserEventController::class);

    // Multi Expense Members
    Route::get('multi-expenses/{multiExpenseId}/members', [MultiExpenseMemberController::class, 'index']);
    Route::post('multi-expenses/{multiExpenseId}/members', [MultiExpenseMemberController::class, 'store']);
    Route::put('multi-expenses/{multiExpenseId}/members/{memberId}', [MultiExpenseMemberController::class, 'update']);
    Route::delete('multi-expenses/{multiExpenseId}/members/{memberId}', [MultiExpenseMemberController::class, 'destroy']);
    Route::post('multi-expenses/{multiExpenseId}/members/{memberId}/settle', [MultiExpenseMemberController::class, 'settle']);

    // Notifications
    Route::apiResource('notifications', NotificationController::class);
    Route::get('notifications/unread/count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/type/{type}', [NotificationController::class, 'byType']);

    // Payment Providers
    Route::get('payment-providers', [PaymentProviderController::class, 'index']);
    Route::get('payment-providers/{id}', [PaymentProviderController::class, 'show']);
    Route::get('payment-providers/type/{type}', [PaymentProviderController::class, 'byType']);
    Route::post('payment-providers/{id}/calculate-fee', [PaymentProviderController::class, 'calculateFee']);
    Route::post('payment-providers/{id}/check-feature', [PaymentProviderController::class, 'checkFeature']);
    Route::get('payment-providers/feature/{feature}', [PaymentProviderController::class, 'byFeature']);
    Route::post('payment-providers/{id}/validate-amount', [PaymentProviderController::class, 'validateAmount']);

    // Bank API Integration Routes
    Route::post('payments/initiate/{providerName}', [PaymentProviderController::class, 'initiatePayment']);
    Route::get('payments/verify/{providerName}/{transactionId}', [PaymentProviderController::class, 'verifyPayment']);
    Route::post('payments/callback/{providerName}', [PaymentProviderController::class, 'handleCallback']);
    Route::get('payments/methods/{providerName}', [PaymentProviderController::class, 'getSupportedMethods']);

    // Splits
    Route::apiResource('splits', SplitController::class);
    Route::post('splits/calculate', [SplitController::class, 'calculate']);
    Route::post('splits/{id}/settle/{participantId}', [SplitController::class, 'settle']);
    Route::get('splits/{id}/summary', [SplitController::class, 'summary']);

    // Transactions
    Route::apiResource('transactions', TransactionController::class);
    Route::get('transactions/type/{type}', [TransactionController::class, 'byType']);
    Route::get('transactions/status/{status}', [TransactionController::class, 'byStatus']);
    Route::get('transactions/date-range', [TransactionController::class, 'byDateRange']);
    Route::get('transactions/summary', [TransactionController::class, 'summary']);
    Route::get('transactions/category/{category}', [TransactionController::class, 'byCategory']);
    Route::post('transactions/{id}/status', [TransactionController::class, 'updateStatus']);

    // 💰 Expense Routes - Only for Logged-in User
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::post('/expenses/bulk', [ExpenseController::class, 'bulkStore']);
    Route::get('/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);
    
    // Settings APIs
    Route::get('/settings', [AuthController::class, 'getSettings']);
    Route::post('/settings/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/settings/update-notifications', [AuthController::class, 'updateNotifications']);
    Route::post('/settings/update-security', [AuthController::class, 'updateSecurity']);
    Route::post('/settings/support', [AuthController::class, 'supportRequest']);

    // 🆕 Splitwise-like Expense Sharing System Routes

    // Groups Management
    Route::apiResource('groups', \App\Http\Controllers\GroupController::class);
    Route::post('groups/{group}/members', [\App\Http\Controllers\GroupController::class, 'addMember']);
    Route::delete('groups/{group}/members', [\App\Http\Controllers\GroupController::class, 'removeMember']);

    // Expense Splits within Groups
    Route::get('groups/{group}/expenses', [\App\Http\Controllers\ExpenseSplitController::class, 'index']);
    Route::post('groups/{group}/expenses', [\App\Http\Controllers\ExpenseSplitController::class, 'store']);
    Route::get('groups/{group}/expenses/{expenseSplit}', [\App\Http\Controllers\ExpenseSplitController::class, 'show']);
    Route::put('groups/{group}/expenses/{expenseSplit}', [\App\Http\Controllers\ExpenseSplitController::class, 'update']);
    Route::delete('groups/{group}/expenses/{expenseSplit}', [\App\Http\Controllers\ExpenseSplitController::class, 'destroy']);
    Route::post('groups/{group}/expenses/{expenseSplit}/payment', [\App\Http\Controllers\ExpenseSplitController::class, 'updatePayment']);

    // Settlements within Groups
    Route::get('groups/{group}/settlements', [\App\Http\Controllers\SettlementController::class, 'index']);
    Route::post('groups/{group}/settlements', [\App\Http\Controllers\SettlementController::class, 'store']);
    Route::get('groups/{group}/settlements/{settlement}', [\App\Http\Controllers\SettlementController::class, 'show']);
    Route::put('groups/{group}/settlements/{settlement}', [\App\Http\Controllers\SettlementController::class, 'update']);
    Route::delete('groups/{group}/settlements/{settlement}', [\App\Http\Controllers\SettlementController::class, 'destroy']);
    Route::get('groups/{group}/settlements/suggestions', [\App\Http\Controllers\SettlementController::class, 'getSuggestions']);

    // Reports and Analytics for Groups
    Route::get('groups/{group}/balance', [\App\Http\Controllers\ReportController::class, 'getUserBalance']);
    Route::get('groups/{group}/balances', [\App\Http\Controllers\ReportController::class, 'getGroupBalances']);
    Route::get('groups/{group}/expense-history', [\App\Http\Controllers\ReportController::class, 'getExpenseHistory']);
    Route::get('groups/{group}/monthly-report', [\App\Http\Controllers\ReportController::class, 'getMonthlyReport']);
    Route::get('groups/{group}/settlement-suggestions', [\App\Http\Controllers\ReportController::class, 'getSettlementSuggestions']);

    // Dashboard for Splitwise features
    Route::get('dashboard/splitwise', [\App\Http\Controllers\ReportController::class, 'getDashboard']);

    // Expense Suggestions
    Route::get('expense-suggestions', [\App\Http\Controllers\ExpenseSuggestionController::class, 'index']);
    Route::post('expense-suggestions/{id}/accept', [\App\Http\Controllers\ExpenseSuggestionController::class, 'accept']);
    Route::put('expense-suggestions/{id}/dismiss', [\App\Http\Controllers\ExpenseSuggestionController::class, 'dismiss']);

    // 🆕 Expense Core Engine Routes
    Route::apiResource('expenses-core', ExpenseCoreController::class);
    Route::post('expenses-core/auto-split-suggestions', [ExpenseCoreController::class, 'getAutoSplitSuggestions']);
    Route::post('expenses-core/check-duplicates', [ExpenseCoreController::class, 'checkDuplicates']);
    Route::get('expenses-core/analytics', [ExpenseCoreController::class, 'analytics']);
    Route::post('expenses-core/{id}/settle', [ExpenseCoreController::class, 'settle']);
});



