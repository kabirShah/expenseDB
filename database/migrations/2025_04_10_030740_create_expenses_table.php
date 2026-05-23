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
            // Expense unique identifier
            $table->uuid('expense_id')->nullable();
            // User relation
            $table->unsignedBigInteger('user_id');
            // NO ->after() allowed here
            $table->unsignedBigInteger('category_id')->nullable();
            // 🔥 Snapshot (VERY IMPORTANT)
            $table->string('category_name')->nullable();
            // Expense core details
            $table->string('transaction_type'); // Cash, Card, UPI, etc.
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('date');

            // Additional info
            $table->text('notes')->nullable();
            $table->string('paid_by')->nullable();
            $table->string('location')->nullable();
            $table->string('receipt_url')->nullable();

            // Recurrence + Status
            $table->string('status')->default('active');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable();
            $table->date('next_recurrence_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
