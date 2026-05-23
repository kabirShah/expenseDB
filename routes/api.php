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
use App\Http\Controllers\AppConfigController;
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
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\GroupExpenseController;
use App\Http\Controllers\ExpenseSplitController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\SplitController;
use App\Http\Controllers\SplitwiseBalanceController;
use App\Http\Controllers\SplitwiseExpenseController;
use App\Http\Controllers\SplitwiseGroupController;
use App\Http\Controllers\SplitwiseSettlementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParserEventController;
use App\Http\Controllers\PaymentProviderController;
use App\Http\Controllers\ExpenseSuggestionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\VoiceController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\RoutineExpenseController;
use App\Http\Controllers\AccountAggregatorController;
use App\Http\Controllers\ExpenseCommentController;
use App\Http\Controllers\RecurringSharedExpenseController;
use App\Http\Controllers\SharedActivityController;
use App\Http\Controllers\SharedAnalyticsController;
use App\Http\Controllers\SharedContactController;
use App\Http\Controllers\SharedSplitController;
use App\Services\TransactionParserService;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-otp', [AuthController::class, 'sendOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/login-otp', [AuthController::class, 'loginOtp']);
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/app-config', [AppConfigController::class, 'show']);
Route::post('/aa/webhook', [AccountAggregatorController::class, 'webhook']);

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
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/set-pin', [AuthController::class, 'setPin']);
        Route::post('/verify-pin', [AuthController::class, 'verifyPin']);
    });

    /*
    | Preferences
    */
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::post('/preferences', [UserPreferenceController::class, 'store']);
    Route::get('/onboarding/status', [OnboardingController::class, 'status']);
    Route::post('/onboarding/save-step', [OnboardingController::class, 'saveStep']);
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
    Route::post('/sync/init', [SyncController::class, 'init']);
    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::get('/friends', [FriendController::class, 'index']);
    Route::post('/friends', [FriendController::class, 'store']);
    Route::delete('/friends/{friendUserId}', [FriendController::class, 'destroy']);

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
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/chart', [DashboardController::class, 'chart']);
        Route::get('/payment-breakdown', [DashboardController::class, 'paymentBreakdown']);
    });

    /*
    | Analytics
    */
    Route::prefix('analytics')->group(function () {
        Route::get('/summary', [AnalyticsController::class, 'summary']);
        Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
        Route::get('/daily-trend', [AnalyticsController::class, 'dailyTrend']);
        Route::get('/balance-trends', [AnalyticsController::class, 'balanceTrends']);
    });

    /*
    | Categories
    */
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
Route::post('/receipts/upload', [ReceiptController::class, 'upload']); // ✅ ADD THIS

    /*
    | Wallets (Phase 1 guide)
    */
    Route::apiResource('wallets', WalletController::class);
    Route::post('/wallets/{id}/add-balance', [WalletController::class, 'addBalance']);

    /*
    | Expenses
    */
    Route::prefix('expenses')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']);
        Route::post('/auto', [ExpenseController::class, 'auto']);
        Route::post('/', [ExpenseController::class, 'store']);
        Route::post('/bulk', [ExpenseController::class, 'bulkStore']);
        Route::get('/{id}', [ExpenseController::class, 'show']);
        Route::put('/{id}', [ExpenseController::class, 'update']);
        Route::delete('/{id}', [ExpenseController::class, 'destroy']);
    });

    Route::apiResource('multi-expenses', MultiExpenseController::class);

    /*
    | Groups
    */
    Route::prefix('groups')->group(function () {
        Route::get('/',                        [GroupController::class, 'index']);
        Route::post('/',                       [GroupController::class, 'store']);
        Route::get('/{expenseGroup}',          [GroupController::class, 'show']);
        Route::put('/{expenseGroup}',          [GroupController::class, 'update']);
        Route::delete('/{expenseGroup}',       [GroupController::class, 'destroy']);
        Route::post('/{expenseGroup}/members', [GroupController::class, 'addMember']);
        Route::delete('/{expenseGroup}/members/{member}', [GroupController::class, 'removeMember']);

        Route::get('/{expenseGroup}/expenses', [GroupExpenseController::class, 'index']);
        Route::post('/{expenseGroup}/expenses',[GroupExpenseController::class, 'store']);
        Route::put('/{expenseGroup}/expenses/{expense}', [GroupExpenseController::class, 'update']);
        Route::delete('/{expenseGroup}/expenses/{expense}', [GroupExpenseController::class, 'destroy']);
        Route::post('/{expenseGroup}/settle',  [GroupExpenseController::class, 'settle']);

        Route::get('/{expenseGroup}/activity', [GroupController::class, 'activity']);
        Route::get('/{expenseGroup}/balances', [GroupController::class, 'balances']);
        Route::get('/{expenseGroup}/debts',    [GroupController::class, 'debts']);
    });

    Route::post('/groups/{expenseGroup}/expenses', [GroupExpenseController::class, 'store']);
    Route::get('/groups/{expenseGroup}/expenses', [GroupExpenseController::class, 'index']);
    Route::post('/settlements', [SettlementController::class, 'store']);
    Route::get('/settlements', [SettlementController::class, 'index']);

    /*
    | Shared Finance Facade
    */
    Route::prefix('shared')->group(function () {
        Route::get('/friends', [SharedContactController::class, 'index']);
        Route::post('/contacts/sync', [SharedContactController::class, 'sync']);
        Route::get('/contacts', [SharedContactController::class, 'deviceContacts']);
        Route::post('/contacts/{contact}/invite', [SharedContactController::class, 'invite']);
        Route::patch('/friends/{friend}/favorite', [SharedContactController::class, 'favorite']);
        Route::patch('/friends/{friend}/respond', [SharedContactController::class, 'respond']);

        Route::post('/splits/calculate', [SharedSplitController::class, 'calculate']);
        Route::post('/balances/simplify', [SharedSplitController::class, 'simplify']);

        Route::get('/expenses/{expense}/comments', [ExpenseCommentController::class, 'index']);
        Route::post('/expenses/{expense}/comments', [ExpenseCommentController::class, 'store']);

        Route::apiResource('recurring-expenses', RecurringSharedExpenseController::class)
            ->except(['show']);

        Route::get('/activity', [SharedActivityController::class, 'index']);
        Route::get('/analytics/summary', [SharedAnalyticsController::class, 'summary']);
    });

    /*
    | Splitwise (Isolated Module)
    */
    Route::prefix('splitwise')->group(function () {
        Route::get('/groups', [SplitwiseGroupController::class, 'index']);
        Route::post('/groups', [SplitwiseGroupController::class, 'store']);
        Route::get('/groups/{group}', [SplitwiseGroupController::class, 'show']);

        Route::get('/expenses', [SplitwiseExpenseController::class, 'index']);
        Route::post('/expenses', [SplitwiseExpenseController::class, 'store']);

        Route::get('/balances/{group}', [SplitwiseBalanceController::class, 'show']);

        Route::get('/settlements', [SplitwiseSettlementController::class, 'index']);
        Route::post('/settlements', [SplitwiseSettlementController::class, 'store']);
    });

    /*
    | Splits
    */
    Route::apiResource('splits', SplitController::class);
    Route::post('/splits/calculate', [SplitController::class, 'calculate']);
    Route::post('/splits/{id}/settle/{participantId}', [SplitController::class, 'settle']);
    Route::get('/splits/{id}/summary', [SplitController::class, 'summary']);

    Route::get('/notifications',[NotificationController::class,'list']);
    Route::post('/notifications/read/{id}',[NotificationController::class,'markRead']);
    Route::get('/notifications/unread/count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/type/{type}', [NotificationController::class, 'byType']);
    Route::apiResource('credit-cards', CreditController::class);
    Route::apiResource('debit-cards', DebitController::class);
    Route::post('/transactions/parse-detection', [TransactionController::class, 'parseDetection']);
    Route::get('/transactions/summary', [TransactionController::class, 'transactionSummary']);
    Route::apiResource('transactions', TransactionController::class);
    Route::post('/transactions/auto-detect', [TransactionController::class, 'autoDetect']);
    Route::post('/transactions/multi', [TransactionController::class, 'multi']);
    Route::post('/transactions/scan', [TransactionController::class, 'scan']);
    Route::get('/transactions/by-batch/{batchId}', [TransactionController::class, 'byBatch']);

    Route::apiResource('balances', BalanceController::class);
    Route::get('/balances-summary', [BalanceController::class, 'summary']);
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
    | Phase 2: Budgets, Reports, Voice, Recurring
    */
    Route::get('/budgets/summary', [BudgetController::class, 'summary']);
    Route::get('/budgets/alerts', [BudgetController::class, 'alerts']);
    Route::get('/budgets/predictions', [BudgetController::class, 'predictions']);
    Route::apiResource('budgets', BudgetController::class);
    Route::apiResource('reports', FinanceReportController::class)->only(['index', 'show']);
    Route::post('/reports/generate', [FinanceReportController::class, 'generate']);

    Route::get('/voice', [VoiceController::class, 'index']);
    Route::post('/voice/parse', [VoiceController::class, 'parse']);
    Route::post('/voice/{voiceEntry}/confirm', [VoiceController::class, 'confirm']);

    Route::apiResource('recurring', RecurringController::class);
    Route::patch('/routine-expenses/{routineExpense}/toggle', [RoutineExpenseController::class, 'toggle']);
    Route::apiResource('routine-expenses', RoutineExpenseController::class)->except(['show']);
    Route::post('/aa/create-consent', [AccountAggregatorController::class, 'createConsent']);
    Route::get('/aa/fetch-transactions', [AccountAggregatorController::class, 'fetchTransactions']);

    /*
    | Expense Suggestions
    */
    Route::prefix('expense-suggestions')->group(function () {
        Route::get('/', [ExpenseSuggestionController::class, 'index']);
        Route::post('/{id}/accept', [ExpenseSuggestionController::class, 'accept']);
        Route::put('/{id}/dismiss', [ExpenseSuggestionController::class, 'dismiss']);
    });

});
