<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_plans', 'include_group_expenses')) {
                $table->boolean('include_group_expenses')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('budget_plans', function (Blueprint $table) {
            if (Schema::hasColumn('budget_plans', 'include_group_expenses')) {
                $table->dropColumn('include_group_expenses');
            }
        });
    }
};
