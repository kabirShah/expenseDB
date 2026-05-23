<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expense_splits')) {
            return;
        }

        Schema::table('expense_splits', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_splits', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('expense_id')
                    ->constrained('expense_groups')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('expense_splits', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('expense_splits', 'payer_user_id')) {
                $table->foreignId('payer_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('expense_splits', 'amount_owed')) {
                $table->decimal('amount_owed', 12, 2)->default(0)->after('payer_user_id');
            }

            if (!Schema::hasColumn('expense_splits', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0)->after('amount_owed');
            }
        });
    }

    public function down(): void
    {
        // Keep this migration additive-only to avoid removing production compatibility columns.
    }
};
