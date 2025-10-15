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
        Schema::create('expenses_core', function (Blueprint $table) {
            $table->id();
            $table->uuid('expense_id')->unique();
            $table->foreignId('payer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('cascade');
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->enum('split_type', ['equal', 'percentage', 'ratio', 'income-based', 'exact'])->default('equal');
            $table->json('participants'); // Array of {user_id, share_info}
            $table->string('currency', 3)->default('INR');
            $table->decimal('confidence_score', 3, 2)->default(1.00); // 0.00 to 1.00
            $table->json('tags')->nullable(); // AI-generated tags
            $table->enum('status', ['active', 'settled', 'deleted'])->default('active');
            $table->timestamp('expense_date')->useCurrent();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['payer_id', 'expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses_core');
    }
};
