<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'wallet_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('multi_expenses') && !Schema::hasColumn('multi_expenses', 'wallet_id')) {
            Schema::table('multi_expenses', function (Blueprint $table) {
                $table->foreignId('wallet_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('wallets')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'wallet_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_id');
            });
        }

        if (Schema::hasTable('multi_expenses') && Schema::hasColumn('multi_expenses', 'wallet_id')) {
            Schema::table('multi_expenses', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wallet_id');
            });
        }
    }
};
