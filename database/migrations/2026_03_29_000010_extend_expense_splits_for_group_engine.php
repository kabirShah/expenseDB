<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_splits', 'expense_id')) {
                $table->foreignId('expense_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('expenses')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('expense_splits', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('expense_id')
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

            if (!Schema::hasColumn('expense_splits', 'shares')) {
                $table->decimal('shares', 12, 4)->nullable()->after('amount_paid');
            }

            if (!Schema::hasColumn('expense_splits', 'percentage')) {
                $table->decimal('percentage', 8, 2)->nullable()->after('shares');
            }

            if (!Schema::hasColumn('expense_splits', 'is_settled')) {
                $table->boolean('is_settled')->default(false)->after('percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            $toDrop = [];
            foreach ([
                'expense_id',
                'user_id',
                'payer_user_id',
                'amount_owed',
                'amount_paid',
                'shares',
                'percentage',
                'is_settled',
            ] as $column) {
                if (Schema::hasColumn('expense_splits', $column)) {
                    $toDrop[] = $column;
                }
            }

            if (Schema::hasColumn('expense_splits', 'expense_id')) {
                try {
                    $table->dropForeign(['expense_id']);
                } catch (\Throwable $e) {
                }
            }
            if (Schema::hasColumn('expense_splits', 'user_id')) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                }
            }
            if (Schema::hasColumn('expense_splits', 'payer_user_id')) {
                try {
                    $table->dropForeign(['payer_user_id']);
                } catch (\Throwable $e) {
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
