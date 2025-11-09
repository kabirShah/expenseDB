<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('expense_id')->nullable(); // ✅ removed ->after('id')

            // Relationships
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Expense core details
            $table->string('category');
            $table->string('transaction_type'); // Cash, Card, etc.
            $table->string('description')->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('date');

            // Extra details from model
            $table->text('notes')->nullable();
            $table->string('paid_by')->nullable();
            $table->string('location')->nullable();
            $table->string('receipt_url')->nullable();

            // ✅ Status and recurrence
            $table->string('status')->default('active');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable(); // daily, weekly, etc.
            $table->date('next_recurrence_date')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes(); // Optional but recommended for safe deletes
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
