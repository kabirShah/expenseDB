<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add currency preference
            if (!Schema::hasColumn('users', 'currency')) {
                $table->string('currency', 3)->default('INR')->after('profile_image');
            }
            
            // Add monthly budget for analytics
            if (!Schema::hasColumn('users', 'monthly_budget')) {
                $table->decimal('monthly_budget', 12, 2)->nullable()->after('currency');
            }
            
            // Add notification preferences
            if (!Schema::hasColumn('users', 'notify_expense_threshold')) {
                $table->boolean('notify_expense_threshold')->default(true)->after('monthly_budget');
            }
            
            if (!Schema::hasColumn('users', 'notify_monthly_summary')) {
                $table->boolean('notify_monthly_summary')->default(true)->after('notify_expense_threshold');
            }
            
            if (!Schema::hasColumn('users', 'notify_large_transactions')) {
                $table->boolean('notify_large_transactions')->default(true)->after('notify_monthly_summary');
            }
            
            // Add analytics preferences
            if (!Schema::hasColumn('users', 'analytics_timezone')) {
                $table->string('analytics_timezone')->default('Asia/Kolkata')->after('notify_large_transactions');
            }
            
            // Add last login timestamp for analytics
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('analytics_timezone');
            }
            
            // Add total expenses count for quick analytics
            if (!Schema::hasColumn('users', 'total_expenses_count')) {
                $table->integer('total_expenses_count')->default(0)->after('last_login_at');
            }
            
            if (!Schema::hasColumn('users', 'total_expenses_amount')) {
                $table->decimal('total_expenses_amount', 12, 2)->default(0)->after('total_expenses_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'monthly_budget',
                'notify_expense_threshold',
                'notify_monthly_summary',
                'notify_large_transactions',
                'analytics_timezone',
                'last_login_at',
                'total_expenses_count',
                'total_expenses_amount'
            ]);
        });
    }
};
