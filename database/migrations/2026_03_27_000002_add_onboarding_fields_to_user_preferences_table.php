<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->string('storage_preference', 30)->default('cloud_sync')->after('notify_time');
            $table->json('favorite_categories')->nullable()->after('storage_preference');
            $table->string('setup_wallet_name')->nullable()->after('favorite_categories');
            $table->string('setup_wallet_type', 50)->nullable()->after('setup_wallet_name');
            $table->decimal('setup_wallet_balance', 12, 2)->nullable()->after('setup_wallet_type');
            $table->string('setup_budget_name')->nullable()->after('setup_wallet_balance');
            $table->decimal('setup_budget_amount', 12, 2)->nullable()->after('setup_budget_name');
            $table->string('setup_budget_period', 30)->nullable()->after('setup_budget_amount');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_completed');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'storage_preference',
                'favorite_categories',
                'setup_wallet_name',
                'setup_wallet_type',
                'setup_wallet_balance',
                'setup_budget_name',
                'setup_budget_amount',
                'setup_budget_period',
                'onboarding_completed_at',
            ]);
        });
    }
};
