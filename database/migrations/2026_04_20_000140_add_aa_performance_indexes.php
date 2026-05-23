<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'consents_user_status_idx');
        });

        Schema::table('raw_aa_data', function (Blueprint $table) {
            $table->index(['consent_id', 'processed'], 'raw_aa_consent_processed_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_id')) {
                $table->index(['user_id', 'account_id', 'transaction_date'], 'transactions_user_account_date_idx');
            }

            if (Schema::hasColumn('transactions', 'reference_id')) {
                $table->index('reference_id', 'transactions_reference_id_idx');
            }

            if (Schema::hasColumn('transactions', 'source_app')) {
                $table->index(['user_id', 'source_app'], 'transactions_user_source_app_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->dropIndex('consents_user_status_idx');
        });

        Schema::table('raw_aa_data', function (Blueprint $table) {
            $table->dropIndex('raw_aa_consent_processed_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_id')) {
                $table->dropIndex('transactions_user_account_date_idx');
            }

            if (Schema::hasColumn('transactions', 'reference_id')) {
                $table->dropIndex('transactions_reference_id_idx');
            }

            if (Schema::hasColumn('transactions', 'source_app')) {
                $table->dropIndex('transactions_user_source_app_idx');
            }
        });
    }
};
