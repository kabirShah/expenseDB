<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('wallet_id');
            }
            if (!Schema::hasColumn('expenses', 'source_ref_id')) {
                $table->unsignedBigInteger('source_ref_id')->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('expenses', 'merchant_name')) {
                $table->string('merchant_name')->nullable()->after('category_name');
            }
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('merchant_name');
            }
            if (!Schema::hasColumn('expenses', 'currency')) {
                $table->string('currency', 3)->default('INR')->after('amount');
            }
            if (!Schema::hasColumn('expenses', 'expense_date')) {
                $table->dateTime('expense_date')->nullable()->after('date');
            }
            if (!Schema::hasColumn('expenses', 'raw_hash')) {
                $table->string('raw_hash', 64)->nullable()->after('receipt_url');
            }
            if (!Schema::hasColumn('expenses', 'duplicate_of')) {
                $table->unsignedBigInteger('duplicate_of')->nullable()->after('raw_hash');
            }
            if (!Schema::hasColumn('expenses', 'is_duplicate')) {
                $table->boolean('is_duplicate')->default(false)->after('duplicate_of');
            }
            if (!Schema::hasColumn('expenses', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_duplicate');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'duplicate_of')) {
                $table->foreign('duplicate_of')
                    ->references('id')
                    ->on('expenses')
                    ->nullOnDelete();
            }

            $table->index(['user_id', 'source_type'], 'expenses_user_source_idx');
            $table->index(['user_id', 'expense_date'], 'expenses_user_expense_date_idx');
            $table->index(['raw_hash'], 'expenses_raw_hash_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['expenses_user_source_idx', 'expenses_user_expense_date_idx', 'expenses_raw_hash_idx'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable $e) {
                }
            }

            if (Schema::hasColumn('expenses', 'duplicate_of')) {
                try {
                    $table->dropForeign(['duplicate_of']);
                } catch (\Throwable $e) {
                }
            }

            $toDrop = [];
            foreach ([
                'source_type',
                'source_ref_id',
                'merchant_name',
                'payment_method',
                'currency',
                'expense_date',
                'raw_hash',
                'duplicate_of',
                'is_duplicate',
                'metadata',
            ] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
