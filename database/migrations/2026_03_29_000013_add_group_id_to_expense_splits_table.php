<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_splits', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('expense_id')
                    ->constrained('expense_groups')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_splits', function (Blueprint $table) {
            if (Schema::hasColumn('expense_splits', 'group_id')) {
                try {
                    $table->dropForeign(['group_id']);
                } catch (\Throwable $e) {
                }

                $table->dropColumn('group_id');
            }
        });
    }
};
