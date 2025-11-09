<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Core transaction data
            $table->string('type'); // e.g. 'credit' or 'debit'
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('completed'); // completed, pending, failed, etc.
            $table->date('transaction_date'); // when it happened

            // Optional additional info
            $table->string('method')->nullable(); // e.g. UPI, Cash, Bank Transfer
            $table->text('description')->nullable();

            // Timestamps
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};