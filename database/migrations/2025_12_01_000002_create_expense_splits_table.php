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
        Schema::create('expense_splits', function (Blueprint $table) {
            $table->id();
            $table->uuid('expense_split_id')->unique();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('paid_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->enum('split_type', ['equal', 'exact', 'percentage'])->default('equal');
            $table->json('split_details'); // Store split breakdown: [{user_id, amount_owed, amount_paid, status}]
            $table->enum('status', ['active', 'settled'])->default('active');
            $table->timestamp('expense_date')->useCurrent();
            $table->string('category')->nullable();
            $table->json('receipt_images')->nullable(); // For storing receipt image URLs
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_splits');
    }
};
