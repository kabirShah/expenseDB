<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'transaction_date'], 'transactions_user_date_idx');
            $table->index(['user_id', 'status', 'type', 'transaction_date'], 'transactions_user_status_type_date_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'expenses_user_date_idx');
            $table->index(['user_id', 'status', 'date'], 'expenses_user_status_date_idx');
        });

        if (Schema::hasTable('balances')) {
            Schema::table('balances', function (Blueprint $table) {
                $table->index(['user_id', 'date_added'], 'balances_user_date_added_idx');
            });
        }

        if (Schema::hasTable('multi_expenses')) {
            Schema::table('multi_expenses', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'multi_expenses_user_created_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_date_idx');
            $table->dropIndex('transactions_user_status_type_date_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_user_date_idx');
            $table->dropIndex('expenses_user_status_date_idx');
        });

        if (Schema::hasTable('balances')) {
            Schema::table('balances', function (Blueprint $table) {
                $table->dropIndex('balances_user_date_added_idx');
            });
        }

        if (Schema::hasTable('multi_expenses')) {
            Schema::table('multi_expenses', function (Blueprint $table) {
                $table->dropIndex('multi_expenses_user_created_at_idx');
            });
        }
    }
};
