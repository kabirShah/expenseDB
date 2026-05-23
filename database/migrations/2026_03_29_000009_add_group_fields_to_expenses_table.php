<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'group_id')) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('expense_groups')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('expenses', 'split_type')) {
                $table->enum('split_type', ['equal', 'exact', 'percentage', 'shares'])
                    ->nullable()
                    ->after('group_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'group_id')) {
                $table->dropConstrainedForeignId('group_id');
            }

            if (Schema::hasColumn('expenses', 'split_type')) {
                $table->dropColumn('split_type');
            }
        });
    }
};
