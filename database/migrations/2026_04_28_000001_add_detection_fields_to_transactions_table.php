<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'reference_id')) {
                $table->string('reference_id')->nullable()->after('description');
            }
            if (!Schema::hasColumn('transactions', 'raw_data')) {
                $table->json('raw_data')->nullable()->after('reference_id');
            }
            if (!Schema::hasColumn('transactions', 'source_type')) {
                $table->string('source_type', 20)->default('manual')->after('entry_type');
            }
            if (!Schema::hasColumn('transactions', 'merchant_name')) {
                $table->string('merchant_name')->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('transactions', 'raw_text')) {
                $table->text('raw_text')->nullable()->after(Schema::hasColumn('transactions', 'raw_data') ? 'raw_data' : 'description');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            try {
                $table->index(['user_id', 'amount', 'transaction_date'], 'transactions_user_amount_date_idx');
                if (Schema::hasColumn('transactions', 'reference_id')) {
                    $table->index(['user_id', 'reference_id'], 'transactions_user_reference_idx');
                }
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['transactions_user_amount_date_idx', 'transactions_user_reference_idx'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable $e) {
                }
            }

            $toDrop = [];
            foreach (['source_type', 'merchant_name', 'raw_text', 'raw_data', 'reference_id'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $toDrop[] = $column;
                }
            }
            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
