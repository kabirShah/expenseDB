<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('type')->comment('budget_alert, expense_threshold, goal_reminder, monthly_summary, large_transaction, etc.');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable()->comment('Additional notification data');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_at')->nullable()->comment('For scheduled notifications');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
