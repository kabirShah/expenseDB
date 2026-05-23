<?php

use App\Models\Expense;
use App\Models\MultiExpense;
use App\Services\UnifiedTransactionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        $service = app(UnifiedTransactionService::class);

        if (Schema::hasTable('expenses')) {
            Expense::query()
                ->orderBy('id')
                ->chunkById(100, function ($expenses) use ($service) {
                    foreach ($expenses as $expense) {
                        $service->syncExpense($expense, $expense->source_type ?? 'single');
                    }
                });
        }

        if (Schema::hasTable('multi_expenses')) {
            MultiExpense::query()
                ->orderBy('id')
                ->chunkById(50, function ($multiExpenses) use ($service) {
                    foreach ($multiExpenses as $multiExpense) {
                        $service->syncMultiExpense($multiExpense);
                    }
                });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('transactions')) {
            return;
        }

        DB::table('transactions')
            ->whereIn('source_type', ['single', 'multi', 'scan', 'voice'])
            ->delete();
    }
};
