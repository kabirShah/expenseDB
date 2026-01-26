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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Budget
            $table->enum('budget_mode', ['monthly', 'category', 'alerts', 'tips'])->nullable();
            $table->decimal('monthly_budget', 10, 2)->nullable();
            $table->json('category_budget')->nullable(); // { food: 3000, travel: 2000 }
            $table->integer('warning_threshold')->default(80);

            // Saving goals
            $table->string('saving_goal')->nullable();
            $table->decimal('saving_target', 10, 2)->nullable();

            // Tips & notifications
            $table->boolean('tips_enabled')->default(true);
            $table->json('tips_types')->nullable(); 
            // ["daily_tips","weekly_summary","overspending"]

            $table->enum('notification_frequency', ['realtime', 'daily', 'weekly', 'none'])
                ->default('weekly');
            $table->time('notify_time')->nullable();

            // Onboarding
            $table->boolean('onboarding_completed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
