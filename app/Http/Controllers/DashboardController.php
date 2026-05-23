<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Balance;
use App\Models\Expense;
use App\Models\MultiExpense;
use App\Models\SmsEntry;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\ExpenseGroup;
use App\Models\FriendRelationship;
use App\Models\Settlement;
use App\Models\UserPreference;
use App\Models\Wallet;
use App\Services\GroupExpenseService;
use App\Services\Budget\BudgetInsightService;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BudgetInsightService $budgetInsightService,
        private readonly DashboardService $dashboardService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $timezone = $this->resolveTimezone($request->query('timezone') ?? $request->header('X-Timezone'));
        $localNow = now()->setTimezone($timezone);

        $month = (int) $request->query('month', $localNow->month);
        $year  = (int) $request->query('year', $localNow->year);
        $preferences = UserPreference::where('user_id', $user->id)->first();
        $wallets = Schema::hasTable('wallets')
            ? Wallet::where('user_id', $user->id)->orderByDesc('is_default')->orderBy('name')->get()
            : collect();
        $expenseHasWallet = $this->hasColumn('expenses', 'wallet_id');
        $expenseHasGroup = $this->hasColumn('expenses', 'group_id');
        $expenseHasPaymentSource = $this->hasColumn('expenses', 'payment_source');
        $expenseHasSourceType = $this->hasColumn('expenses', 'source_type');
        $expenseHasSourceRefId = $this->hasColumn('expenses', 'source_ref_id');
        $multiExpenseHasWallet = $this->hasColumn('multi_expenses', 'wallet_id');
        $transactionHasWallet = $this->hasColumn('transactions', 'wallet_id');
        $transactionHasCategory = $this->hasColumn('transactions', 'category_id');

        $localToday = $localNow->copy()->startOfDay();
        $startOfMonth = $localNow->copy()->setDate($year, $month, 1)->startOfDay();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $startOfYear  = $localNow->copy()->setDate($year, 1, 1)->startOfDay();
        $endOfYear    = $startOfYear->copy()->endOfYear();
        $userGroupIds = $this->userGroupIds($user->id);

        /*
        |--------------------------------------------------------------------------
        | BALANCES
        |--------------------------------------------------------------------------
        */

        $legacyBalance = (float) Balance::where('user_id', $user->id)->sum('amount');
        $totalBalanceCount = Balance::where('user_id', $user->id)->count();
        $walletBalance = (float) $wallets->sum('balance');
        $onboardingBalance = (float) ($preferences?->setup_wallet_balance ?? 0);

        /*
        |--------------------------------------------------------------------------
        | SINGLE EXPENSES
        |--------------------------------------------------------------------------
        */

        $todaySingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->when($expenseHasGroup, fn ($query) => $query->whereNull('group_id'))
            ->whereDate('date', $localToday->toDateString())
            ->sum('amount');

        $monthSingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->when($expenseHasGroup, fn ($query) => $query->whereNull('group_id'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $yearSingle = (float) Expense::active()
            ->where('user_id', $user->id)
            ->when($expenseHasGroup, fn ($query) => $query->whereNull('group_id'))
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->sum('amount');

        $todayGroupExpense = $expenseHasGroup && $userGroupIds->isNotEmpty()
            ? (float) Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereNotNull('group_id')
                ->whereDate('date', $localToday->toDateString())
                ->sum('amount')
            : 0.0;

        $monthGroupExpense = $expenseHasGroup && $userGroupIds->isNotEmpty()
            ? (float) Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereNotNull('group_id')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('amount')
            : 0.0;

        $yearGroupExpense = $expenseHasGroup && $userGroupIds->isNotEmpty()
            ? (float) Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereNotNull('group_id')
                ->whereBetween('date', [$startOfYear, $endOfYear])
                ->sum('amount')
            : 0.0;

        /*
        |--------------------------------------------------------------------------
        | MULTI EXPENSES
        |--------------------------------------------------------------------------
        */

        $todayMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereDate('created_at', $localToday->toDateString())
            ->sum('total_amount');

        $monthMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $yearMulti = (float) MultiExpense::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->sum('total_amount');

        /*
        |--------------------------------------------------------------------------
        | TOTAL EXPENSES
        |--------------------------------------------------------------------------
        */

        $todayTransactionExpense = $this->standaloneTransactionExpenseQuery($user->id)
            ->whereDate('transaction_date', $localToday->toDateString())
            ->sum('amount');

        $monthTransactionExpense = $this->standaloneTransactionExpenseQuery($user->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $yearTransactionExpense = $this->standaloneTransactionExpenseQuery($user->id)
            ->whereBetween('transaction_date', [$startOfYear, $endOfYear])
            ->sum('amount');

        $todaySmsExpense = $this->smsExpenseTotal($user->id, $localToday, $localToday->copy()->endOfDay());
        $monthSmsExpense = $this->smsExpenseTotal($user->id, $startOfMonth, $endOfMonth);
        $yearSmsExpense = $this->smsExpenseTotal($user->id, $startOfYear, $endOfYear);

        $todayExpense = $todaySingle + $todayGroupExpense + $todayMulti + (float) $todayTransactionExpense + $todaySmsExpense;
        $monthExpense = $monthSingle + $monthGroupExpense + $monthMulti + (float) $monthTransactionExpense + $monthSmsExpense;
        $yearExpense  = $yearSingle + $yearGroupExpense + $yearMulti + (float) $yearTransactionExpense + $yearSmsExpense;
        $totalExpense = $this->allTimeExpenseTotal($user->id);

        /*
        |--------------------------------------------------------------------------
        | INCOME / BALANCE / SAVINGS
        |--------------------------------------------------------------------------
        */

        $totalIncome = $this->allTimeIncomeTotal($user->id, $onboardingBalance);
        $monthIncome = $this->monthlyIncomeTotal($user->id, $startOfMonth, $endOfMonth);
        $yearIncome = $this->yearIncomeTotal($user->id, $startOfYear, $endOfYear, $onboardingBalance);
        $currentBalance = $totalIncome - $totalExpense;
        $monthSaving = $monthIncome - $monthExpense;
        $yearSaving  = $yearIncome - $yearExpense;

        /*
        |--------------------------------------------------------------------------
        | RECENT BALANCES
        |--------------------------------------------------------------------------
        */

        $recentBalances = Balance::where('user_id', $user->id)
            ->whereBetween('date_added', [$startOfMonth, $endOfMonth])
            ->orderByDesc('date_added')
            ->limit(5)
            ->get([
                'id',
                'amount',
                'source',
                'date_added'
            ]);

        /*
        |--------------------------------------------------------------------------
        | RECENT SINGLE EXPENSES
        |--------------------------------------------------------------------------
        */

        $recentSingleQuery = Expense::active()
            ->where('user_id', $user->id)
            ->when($expenseHasGroup, fn ($query) => $query->whereNull('group_id'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->orderByDesc('date')
            ->limit(5);

        $recentSingleRelations = ['category:id,name'];
        if ($expenseHasWallet) {
            $recentSingleRelations[] = 'wallet:id,name,balance';
        }

        $recentSingleColumns = [
            'id',
            'amount',
            'category_id',
            'transaction_type',
            'description',
            'date',
        ];
        if ($expenseHasPaymentSource) {
            $recentSingleColumns[] = 'payment_source';
        }
        if ($expenseHasWallet) {
            $recentSingleColumns[] = 'wallet_id';
        }

        $recentSingle = $recentSingleQuery
            ->with($recentSingleRelations)
            ->get($recentSingleColumns);

        $recentGroupExpenses = $expenseHasGroup && $userGroupIds->isNotEmpty()
            ? Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereNotNull('group_id')
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->with(['group:id,name', 'splits.user:id,name'])
                ->orderByDesc('date')
                ->limit(5)
                ->get([
                    'id',
                    'group_id',
                    'amount',
                    'description',
                    'merchant_name',
                    'split_type',
                    'date',
                ])
            : collect();

        /*
        |--------------------------------------------------------------------------
        | RECENT MULTI EXPENSES
        |--------------------------------------------------------------------------
        */

        $recentMultiQuery = MultiExpense::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->orderByDesc('created_at')
            ->limit(5);

        $recentMultiColumns = [
            'id',
            'title',
            'total_amount',
            'created_at',
            'category',
        ];
        if ($multiExpenseHasWallet) {
            $recentMultiColumns[] = 'wallet_id';
            $recentMultiQuery->with(['wallet:id,name,balance']);
        }

        $recentMulti = $recentMultiQuery->get($recentMultiColumns);

        /*
        |--------------------------------------------------------------------------
        | RECENT TRANSACTIONS (Optional)
        |--------------------------------------------------------------------------
        */

        $recentTransactions = [];
        if (class_exists(Transaction::class)) {
            $recentTransactionQuery = Transaction::where('user_id', $user->id)
                ->orderByDesc('transaction_date')
                ->limit(5);

            $recentTransactionRelations = [];
            if ($transactionHasWallet) {
                $recentTransactionRelations[] = 'wallet';
            }
            if ($transactionHasCategory) {
                $recentTransactionRelations[] = 'category';
            }
            if ($recentTransactionRelations !== []) {
                $recentTransactionQuery->with($recentTransactionRelations);
            }

            $recentTransactionColumns = [
                'id',
                'entry_type',
                'type',
                'amount',
                'status',
                'transaction_date',
            ];
            if ($transactionHasWallet) {
                $recentTransactionColumns[] = 'wallet_id';
            }
            if ($transactionHasCategory) {
                $recentTransactionColumns[] = 'category_id';
            }

            $recentTransactions = $recentTransactionQuery->get($recentTransactionColumns);
        }

        $groupOverview = $this->buildGroupOverview($user->id, $startOfMonth, $endOfMonth);
        $friendOverview = $this->buildFriendOverview($user->id);
        $budgetStatus = $this->budgetInsightService->dashboardStatusForUser($user->id);
        $autoDetectedCount = $this->autoDetectedExpenseCount($user->id, $startOfMonth, $endOfMonth);

        /*
        |--------------------------------------------------------------------------
        | CATEGORY BREAKDOWN (MONTH)
        |--------------------------------------------------------------------------
        */

        $categoryBreakdown = Expense::active()
            ->where('expenses.user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->join('categories', 'categories.id', '=', 'expenses.category_id')
            ->selectRaw('categories.name as category, SUM(expenses.amount) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();
        $transactionBreakdown = Expense::active()
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->select(
                'transaction_type',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('transaction_type')
            ->orderByDesc('total')
            ->get();
        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION COUNT
        |--------------------------------------------------------------------------
        */

        $notificationCount = class_exists(Notification::class)
            ? Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count()
            : 0;

        $todayModuleSummary = $this->moduleExpenseSummary($user->id, $localToday, $localToday->copy()->endOfDay());
        $monthModuleSummary = $this->moduleExpenseSummary($user->id, $startOfMonth, $endOfMonth);
        $yearModuleSummary = $this->moduleExpenseSummary($user->id, $startOfYear, $endOfYear);
        $lifetimeModuleSummary = $this->moduleExpenseSummary($user->id);

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'total_balance' => round($currentBalance, 2),
            'financial_container' => financialContainer($currentBalance),
            'total_month_expense' => round($monthExpense, 2),
            'month_income' => round($monthIncome, 2),
            'month_saving' => round($monthSaving, 2),

            'user' => [
                'id'    => $user->id,
                'name'  => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
            ],

            'filters' => [
                'month' => $month,
                'year'  => $year,
                'timezone' => $timezone,
            ],

            'greeting' => [
                'label' => $this->greetingLabel($localNow->hour),
                'period' => $this->greetingPeriod($localNow->hour),
                'message' => $this->greetingLabel($localNow->hour) . ', ' . ($user->first_name ?? $user->name ?? 'there'),
                'timezone' => $timezone,
                'local_time' => $localNow->toDateTimeString(),
            ],

            'totals' => [
                'balance'        => round($currentBalance, 2),
                'total_balance'  => round($currentBalance, 2),
                'current_balance' => round($currentBalance, 2),
                'financial_container' => financialContainer($currentBalance),
                'total_income' => round($totalIncome, 2),
                'month_income' => round($monthIncome, 2),
                'year_income' => round($yearIncome, 2),
                'total_expense' => round($totalExpense, 2),
                'total_month_expense' => round($monthExpense, 2),
                'wallet_balance' => $walletBalance,
                'onboarding_balance' => $onboardingBalance,
                'legacy_added_balance' => $legacyBalance,
                'balance_count'  => $totalBalanceCount,
                'today_expense'  => round($todayExpense, 2),
                'month_expense'  => round($monthExpense, 2),
                'year_expense'   => round($yearExpense, 2),
                'today_group_expense' => round($todayGroupExpense, 2),
                'month_group_expense' => round($monthGroupExpense, 2),
                'year_group_expense' => round($yearGroupExpense, 2),
                'month_saving'   => round($monthSaving, 2),
                'year_saving'    => round($yearSaving, 2),
                'module_expenses' => [
                    'today' => $todayModuleSummary,
                    'month' => $monthModuleSummary,
                    'year' => $yearModuleSummary,
                    'lifetime' => $lifetimeModuleSummary,
                ],
            ],

            'recent' => [
                'balances'     => $recentBalances,
                'expenses'     => $recentSingle,
                'group_expenses' => $recentGroupExpenses,
                'settlements'  => $groupOverview['recent_settlements'],
                'groups'       => $groupOverview['recent_groups'],
                'multi'        => $recentMulti,
                'transactions' => $recentTransactions,
                'wallets'      => $wallets,
            ],

            'groups' => $groupOverview,
            'friends' => $friendOverview,
            'auto_detected_count' => $autoDetectedCount,
            'budget_status' => $budgetStatus,

            'breakdowns' => [
                'category_month' => $categoryBreakdown,
                'transaction_month' => $transactionBreakdown
            ],

            'notifications' => [
                'unread' => $notificationCount
            ],
            'features' => $this->featureFlags(),
        ]);
    }

    public function summary(Request $request)
    {
        $user = $request->user();
        $timezone = $this->resolveTimezone($request->query('timezone') ?? $request->header('X-Timezone'));
        $localNow = now()->setTimezone($timezone);
        $month = (int) $request->query('month', $localNow->month);
        $year = (int) $request->query('year', $localNow->year);
        $preferences = UserPreference::where('user_id', $user->id)->first();
        $onboardingBalance = (float) ($preferences?->setup_wallet_balance ?? 0);
        $startOfMonth = $localNow->copy()->setDate($year, $month, 1)->startOfDay();
        $endOfMonth   = $startOfMonth->copy()->endOfMonth();

        $monthIncome = $this->monthlyIncomeTotal($user->id, $startOfMonth, $endOfMonth);
        $monthExpense = $this->dashboardService->getTotalExpense($user->id, $startOfMonth, $endOfMonth);
        $breakdown = $this->dashboardService->getExpenseBySource($user->id, $startOfMonth, $endOfMonth);
        $breakdownTotal = round(array_sum($breakdown), 2);
        if (abs($monthExpense - $breakdownTotal) >= 0.01) {
            $monthExpense = $breakdownTotal;
        }
        $totalIncome = $this->allTimeIncomeTotal($user->id, $onboardingBalance);
        $totalExpense = $this->dashboardService->getTotalExpense($user->id);
        $totalBalance = $totalIncome - $totalExpense;
        $groupOverview = $this->buildGroupOverview($user->id, $startOfMonth, $endOfMonth);
        $expenseHasPaymentSource = $this->hasColumn('expenses', 'payment_source');
        $expenseHasSourceType = $this->hasColumn('expenses', 'source_type');
        $expenseHasSourceRefId = $this->hasColumn('expenses', 'source_ref_id');
        $budgetStatus = $this->budgetInsightService->dashboardStatusForUser($user->id);
        $autoDetectedCount = $this->autoDetectedExpenseCount($user->id, $startOfMonth, $endOfMonth);

        $wallets = Schema::hasTable('wallets')
            ? Wallet::where('user_id', $user->id)->orderByDesc('is_default')->orderBy('name')->get()
            : collect();

        $recentTransactionsQuery = Transaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(5);

        $relations = [];
        if ($this->hasColumn('transactions', 'category_id')) {
            $relations[] = 'category';
        }
        if ($this->hasColumn('transactions', 'wallet_id')) {
            $relations[] = 'wallet';
        }
        if ($relations !== []) {
            $recentTransactionsQuery->with($relations);
        }

        $recentTransactions = $recentTransactionsQuery->get();

        $recentExpensesColumns = [
            'id',
            'amount',
            'category_id',
            'transaction_type',
            'description',
            'date',
        ];
        if ($expenseHasSourceType) {
            $recentExpensesColumns[] = 'source_type';
        }
        if ($expenseHasSourceRefId) {
            $recentExpensesColumns[] = 'source_ref_id';
        }
        if ($expenseHasPaymentSource) {
            $recentExpensesColumns[] = 'payment_source';
        }

        $recentExpenses = Expense::active()
            ->where('user_id', $user->id)
            ->when($this->hasColumn('expenses', 'group_id'), fn ($query) => $query->whereNull('group_id'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with(['category:id,name'])
            ->orderByDesc('date')
            ->limit(5)
            ->get($recentExpensesColumns);

        $transactionBreakdown = collect($breakdown)
            ->map(fn (float $total, string $source): array => [
                'transaction_type' => ucfirst($source),
                'source_type' => $source,
                'total' => $total,
            ])
            ->values();
        $categoryBreakdown = $this->dashboardService->getExpenseByCategory($user->id, $startOfMonth, $endOfMonth);

        return response()->json([
            'success' => true,
            'total_expense' => round($monthExpense, 2),
            'breakdown' => $breakdown,
            'category_breakdown' => $categoryBreakdown,
            'user' => [
                'id' => $user->id,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
            ],
            'filters' => [
                'month' => $month,
                'year' => $year,
                'timezone' => $timezone,
            ],
            'total_balance' => round($totalBalance, 2),
            'total_month_expense' => round($monthExpense, 2),
            'month_income' => round($monthIncome, 2),
            'month_saving' => round($monthIncome - $monthExpense, 2),
            'transaction_breakdown' => $transactionBreakdown,
            'recent_groups' => $groupOverview['recent_groups'],
            'recent_expenses' => $recentExpenses,
            'wallets' => $wallets,
            'recent_transactions' => $recentTransactions,
            'groups' => $groupOverview,
            'auto_detected_count' => $autoDetectedCount,
            'budget_status' => $budgetStatus,
            'features' => $this->featureFlags(),
        ]);
    }

    public function chart(Request $request)
    {
        $data = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('type', ['expense', 'debit'])
            ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as period, SUM(amount) as total')
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->limit(12)
            ->get();

        return response()->json(['chart_data' => $data]);
    }

    public function paymentBreakdown(Request $request)
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $userId = $request->user()->id;

        $breakdown = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['expense', 'debit'])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $sourceApps = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', ['expense', 'debit'])
            ->whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->whereNotNull('source_app')
            ->select('source_app', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('source_app')
            ->get();

        return response()->json([
            'by_payment_method' => $breakdown,
            'by_source_app' => $sourceApps,
        ]);
    }

    private function transactionExpenseQuery(int $userId)
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['expense', 'debit']);
    }

    private function standaloneTransactionExpenseQuery(int $userId)
    {
        $query = $this->transactionExpenseQuery($userId);

        if ($this->hasColumn('transactions', 'expense_id')) {
            $query->whereNull('expense_id');
        }

        return $query;
    }

    private function transactionIncomeQuery(int $userId)
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->whereIn('type', ['income', 'credit']);
    }

    private function smsExpenseTotal(int $userId, Carbon $from, Carbon $to): float
    {
        return (float) SmsEntry::query()
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->whereBetween(DB::raw('COALESCE(received_at, created_at)'), [$from, $to])
            ->get()
            ->filter(function (SmsEntry $entry) {
                $parsed = $entry->parsed_data ?? [];

                return $entry->transaction_id === null
                    && ($parsed['type'] ?? null) === 'debit'
                    && is_numeric($parsed['amount'] ?? null);
            })
            ->sum(fn (SmsEntry $entry) => (float) ($entry->parsed_data['amount'] ?? 0));
    }

    private function moduleExpenseSummary(int $userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $summary = [
            'single' => ['total' => 0.0, 'count' => 0],
            'group' => ['total' => 0.0, 'count' => 0],
            'multi' => ['total' => 0.0, 'count' => 0],
            'voice' => ['total' => 0.0, 'count' => 0],
            'sms' => ['total' => 0.0, 'count' => 0],
            'scan' => ['total' => 0.0, 'count' => 0],
        ];
        $userGroupIds = $this->userGroupIds($userId);

        $transactionColumns = ['amount', 'entry_type'];
        if ($this->hasColumn('transactions', 'metadata')) {
            $transactionColumns[] = 'metadata';
        }

        $transactions = $this->standaloneTransactionExpenseQuery($userId);
        if ($from && $to) {
            $transactions->whereBetween('transaction_date', [$from, $to]);
        }

        $transactions = $transactions->get($transactionColumns);

        foreach ($transactions as $transaction) {
            $module = $this->resolveExpenseModule($transaction);
            $summary[$module]['total'] += (float) $transaction->amount;
            $summary[$module]['count']++;
        }

        $legacySingle = Expense::active()
            ->where('user_id', $userId);
        if ($from && $to) {
            $legacySingle->whereBetween('date', [$from, $to]);
        }
        if ($this->hasColumn('expenses', 'group_id')) {
            $legacySingle->whereNull('group_id');
        }
        $legacySingle = $legacySingle
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        $groupExpenses = null;
        if ($this->hasColumn('expenses', 'group_id') && $userGroupIds->isNotEmpty()) {
            $groupExpenses = Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereNotNull('group_id');
            if ($from && $to) {
                $groupExpenses->whereBetween('date', [$from, $to]);
            }
            $groupExpenses = $groupExpenses
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->first();
        }

        $legacyMulti = MultiExpense::query()
            ->where('user_id', $userId);
        if ($from && $to) {
            $legacyMulti->whereBetween('created_at', [$from, $to]);
        }
        $legacyMulti = $legacyMulti
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $summary['single']['total'] += (float) ($legacySingle->total ?? 0);
        $summary['single']['count'] += (int) ($legacySingle->count ?? 0);
        $summary['group']['total'] += (float) ($groupExpenses->total ?? 0);
        $summary['group']['count'] += (int) ($groupExpenses->count ?? 0);
        $summary['multi']['total'] += (float) ($legacyMulti->total ?? 0);
        $summary['multi']['count'] += (int) ($legacyMulti->count ?? 0);

        $smsEntries = SmsEntry::query()
            ->where('user_id', $userId)
            ->where('status', 'confirmed');
        if ($from && $to) {
            $smsEntries->whereBetween(DB::raw('COALESCE(received_at, created_at)'), [$from, $to]);
        }
        $smsEntries = $smsEntries->get();

        foreach ($smsEntries as $entry) {
            $parsed = $entry->parsed_data ?? [];

            if (($parsed['type'] ?? null) !== 'debit' || !is_numeric($parsed['amount'] ?? null)) {
                continue;
            }

            if ($entry->transaction_id !== null) {
                continue;
            }

            $summary['sms']['total'] += (float) $parsed['amount'];
            $summary['sms']['count']++;
        }

        $moduleBuckets = array_filter($summary, static fn ($item) => is_array($item));

        $summary['total'] = array_sum(array_map(
            static fn (array $item): float => (float) $item['total'],
            $moduleBuckets
        ));
        $summary['total_count'] = array_sum(array_map(
            static fn (array $item): int => (int) $item['count'],
            $moduleBuckets
        ));

        return $summary;
    }

    private function resolveExpenseModule(Transaction $transaction): string
    {
        $metadata = $this->hasColumn('transactions', 'metadata') && is_array($transaction->metadata)
            ? $transaction->metadata
            : [];

        if (($transaction->entry_type ?? null) === 'scan' || ($metadata['source'] ?? null) === 'scan') {
            return 'scan';
        }

        return match ($transaction->entry_type) {
            'multi' => 'multi',
            'voice' => 'voice',
            'sms' => 'sms',
            default => 'single',
        };
    }

    private function expenseTotalForPeriod(int $userId, Carbon $from, Carbon $to): float
    {
        $userGroupIds = $this->userGroupIds($userId);

        $single = (float) Expense::active()
            ->where('user_id', $userId)
            ->when($this->hasColumn('expenses', 'group_id'), fn ($query) => $query->whereNull('group_id'))
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        $group = $this->hasColumn('expenses', 'group_id') && $userGroupIds->isNotEmpty()
            ? (float) Expense::active()
                ->whereIn('group_id', $userGroupIds)
                ->whereBetween('date', [$from, $to])
                ->sum('amount')
            : 0.0;

        $multi = (float) MultiExpense::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');

        $transactionExpense = (float) $this->standaloneTransactionExpenseQuery($userId)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        $smsExpense = $this->smsExpenseTotal($userId, $from, $to);

        return $single + $group + $multi + $transactionExpense + $smsExpense;
    }

    private function allTimeExpenseTotal(int $userId): float
    {
        return $this->expenseTotalForPeriod($userId, Carbon::create(2000, 1, 1)->startOfDay(), now()->endOfDay());
    }

    private function monthlyIncomeTotal(int $userId, Carbon $from, Carbon $to): float
    {
        $balanceIncome = (float) Balance::query()
            ->where('user_id', $userId)
            ->whereBetween('date_added', [$from, $to])
            ->sum('amount');

        $transactionIncome = (float) $this->transactionIncomeQuery($userId)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        return $balanceIncome + $transactionIncome;
    }

    private function yearIncomeTotal(int $userId, Carbon $from, Carbon $to, float $onboardingBalance): float
    {
        $yearIncome = (float) Balance::query()
            ->where('user_id', $userId)
            ->whereBetween('date_added', [$from, $to])
            ->sum('amount');

        $transactionIncome = (float) $this->transactionIncomeQuery($userId)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        return $yearIncome + $transactionIncome + ($from->year === now()->year ? $onboardingBalance : 0.0);
    }

    private function allTimeIncomeTotal(int $userId, float $onboardingBalance): float
    {
        $balanceIncome = (float) Balance::query()
            ->where('user_id', $userId)
            ->sum('amount');

        $transactionIncome = (float) $this->transactionIncomeQuery($userId)
            ->sum('amount');

        return $onboardingBalance + $balanceIncome + $transactionIncome;
    }

    private function buildGroupOverview(int $userId, Carbon $from, Carbon $to): array
    {
        if (!Schema::hasTable('expense_groups') || !Schema::hasTable('group_members')) {
            return [
                'count' => 0,
                'admin_count' => 0,
                'today_expense' => 0,
                'month_expense' => 0,
                'year_expense' => 0,
                'recent_groups' => [],
                'recent_settlements' => [],
                'group_summaries' => [],
                'debts' => [],
                'balances' => ['to_receive' => 0, 'to_pay' => 0],
            ];
        }

        $groups = ExpenseGroup::query()
            ->whereHas('members', fn ($query) => $query->where('user_id', $userId))
            ->withCount('members')
            ->with([
                'members' => fn ($query) => $query
                    ->where('user_id', $userId)
                    ->select('id', 'group_id', 'user_id', 'role'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        $groupIds = $groups->pluck('id');
        $balances = ['to_receive' => 0.0, 'to_pay' => 0.0];
        $groupSummaries = [];
        $today = $to->copy()->startOfDay();
        $service = app(GroupExpenseService::class);
        $hasExpenseSplitBalances = $this->hasColumn('expense_splits', 'group_id')
            && $this->hasColumn('expense_splits', 'user_id')
            && $this->hasColumn('expense_splits', 'payer_user_id')
            && $this->hasColumn('expense_splits', 'amount_owed')
            && $this->hasColumn('expense_splits', 'amount_paid');

        foreach ($groups as $group) {
            $groupId = $group->id;
            $owed = $hasExpenseSplitBalances
                ? (float) DB::table('expense_splits')
                    ->where('group_id', $groupId)
                    ->where('user_id', $userId)
                    ->sum('amount_owed')
                : 0.0;

            $paid = $hasExpenseSplitBalances
                ? (float) DB::table('expense_splits')
                    ->where('group_id', $groupId)
                    ->where('payer_user_id', $userId)
                    ->sum('amount_paid')
                : 0.0;

            $settledOut = Schema::hasTable('settlements')
                ? (float) Settlement::query()->where('group_id', $groupId)->where('from_user_id', $userId)->sum('settled_amount')
                : 0.0;

            $settledIn = Schema::hasTable('settlements')
                ? (float) Settlement::query()->where('group_id', $groupId)->where('to_user_id', $userId)->sum('settled_amount')
                : 0.0;

            $net = round(($paid + $settledIn) - ($owed + $settledOut), 2);

            if ($net > 0) {
                $balances['to_receive'] += $net;
            } elseif ($net < 0) {
                $balances['to_pay'] += abs($net);
            }

            $groupMonthExpense = $this->hasColumn('expenses', 'group_id')
                ? (float) Expense::active()->where('group_id', $groupId)->whereBetween('date', [$from, $to])->sum('amount')
                : 0.0;

            $groupSettlements = Schema::hasTable('settlements')
                ? (float) Settlement::query()->where('group_id', $groupId)->sum('settled_amount')
                : 0.0;

            $groupDebts = $hasExpenseSplitBalances
                ? collect($service->balancesForGroup($group)['simplified'])
                    ->filter(fn (array $debt) => $debt['amount'] > 0)
                    ->values()
                : collect();

            $groupSummaries[] = [
                'id' => $groupId,
                'name' => $group->name,
                'description' => $group->description,
                'member_count' => (int) ($group->members_count ?? 0),
                'role' => $group->members->first()?->role ?? 'member',
                'month_expense' => round($groupMonthExpense, 2),
                'settled_total' => round($groupSettlements, 2),
                'your_balance' => $net,
                'you_owe' => $net < 0 ? round(abs($net), 2) : 0.0,
                'you_are_owed' => $net > 0 ? round($net, 2) : 0.0,
                'pending_settlement_count' => $groupDebts->count(),
            ];
        }

        $adminCount = (int) DB::table('group_members')
            ->where('user_id', $userId)
            ->where('role', 'admin')
            ->count();

        return [
            'count' => $groups->count(),
            'admin_count' => $adminCount,
            'today_expense' => $this->hasColumn('expenses', 'group_id') && $groupIds->isNotEmpty()
                ? (float) Expense::active()->whereIn('group_id', $groupIds)->whereDate('date', $today->toDateString())->sum('amount')
                : 0.0,
            'month_expense' => $this->hasColumn('expenses', 'group_id') && $groupIds->isNotEmpty()
                ? (float) Expense::active()->whereIn('group_id', $groupIds)->whereBetween('date', [$from, $to])->sum('amount')
                : 0.0,
            'year_expense' => $this->hasColumn('expenses', 'group_id') && $groupIds->isNotEmpty()
                ? (float) Expense::active()->whereIn('group_id', $groupIds)->whereYear('date', $from->year)->sum('amount')
                : 0.0,
            'recent_groups' => $groupSummaries === []
                ? collect()
                : collect($groupSummaries)->sortByDesc('month_expense')->take(5)->values(),
            'recent_settlements' => Schema::hasTable('settlements')
                ? Settlement::query()
                    ->whereIn('group_id', $groupIds)
                    ->where(function ($query) use ($userId) {
                        $query->where('from_user_id', $userId)->orWhere('to_user_id', $userId);
                    })
                    ->with(['group:id,name', 'fromUser:id,name', 'toUser:id,name'])
                    ->latest('settled_at')
                    ->limit(5)
                    ->get()
                : collect(),
            'group_summaries' => collect($groupSummaries)
                ->sortByDesc('month_expense')
                ->values(),
            'debts' => $hasExpenseSplitBalances
                ? collect($groupSummaries)
                    ->filter(fn (array $group) => $group['pending_settlement_count'] > 0)
                    ->values()
                : collect(),
            'balances' => [
                'to_receive' => round($balances['to_receive'], 2),
                'to_pay' => round($balances['to_pay'], 2),
                'net' => round($balances['to_receive'] - $balances['to_pay'], 2),
            ],
        ];
    }

    private function buildFriendOverview(int $userId): array
    {
        if (!Schema::hasTable('friend_relationships')) {
            return [
                'count' => 0,
                'recent' => [],
            ];
        }

        $friends = FriendRelationship::query()
                ->where('user_id', $userId)
            ->with('friend:id,name,first_name,last_name,email')
            ->latest()
            ->get();

        return [
            'count' => $friends->count(),
            'recent' => $friends->take(5)->values(),
        ];
    }

    private function userGroupIds(int $userId)
    {
        if (!Schema::hasTable('group_members')) {
            return collect();
        }

        return DB::table('group_members')
            ->where('user_id', $userId)
            ->pluck('group_id');
    }

    private function resolveTimezone(?string $timezone): string
    {
        if (is_string($timezone) && $timezone !== '') {
            try {
                new \DateTimeZone($timezone);

                return $timezone;
            } catch (\Throwable $e) {
            }
        }

        return config('app.timezone', 'UTC');
    }

    private function greetingLabel(int $hour): string
    {
        return match (true) {
            $hour < 12 => 'Good Morning',
            $hour < 18 => 'Good Afternoon',
            default => 'Good Night',
        };
    }

    private function greetingPeriod(int $hour): string
    {
        return match (true) {
            $hour < 12 => 'morning',
            $hour < 18 => 'afternoon',
            default => 'night',
        };
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function featureFlags(): array
    {
        return [
            'enable_payment_source_detection' => (bool) config('features.enable_payment_source_detection', true),
            'enable_auto_tracking' => (bool) config('features.enable_auto_tracking', true),
        ];
    }

    private function autoDetectedExpenseCount(int $userId, Carbon $from, Carbon $to): int
    {
        if (!$this->hasColumn('expenses', 'source_type')) {
            return 0;
        }

        $query = Expense::active()
            ->where('user_id', $userId)
            ->whereIn('source_type', ['sms', 'notification'])
            ->whereBetween('date', [$from, $to]);

        if ($this->hasColumn('expenses', 'source_ref_id')) {
            $query->whereNull('source_ref_id');
        }

        return (int) $query->count();
    }
}
