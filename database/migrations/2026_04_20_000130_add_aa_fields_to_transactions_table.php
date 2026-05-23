<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('user_id')->constrained('accounts')->nullOnDelete();
            }

            if (!Schema::hasColumn('transactions', 'merchant')) {
                $table->string('merchant')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('transactions', 'reference_id')) {
                $table->string('reference_id')->nullable()->after('merchant');
            }

            if (!Schema::hasColumn('transactions', 'raw_data')) {
                $table->json('raw_data')->nullable()->after('reference_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }

            if (Schema::hasColumn('transactions', 'merchant')) {
                $table->dropColumn('merchant');
            }

            if (Schema::hasColumn('transactions', 'reference_id')) {
                $table->dropColumn('reference_id');
            }

            if (Schema::hasColumn('transactions', 'raw_data')) {
                $table->dropColumn('raw_data');
            }
        });
    }
};
