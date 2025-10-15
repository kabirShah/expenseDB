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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('payment_provider_id')->nullable();
            $table->unsignedBigInteger('credit_card_id')->nullable();
            $table->unsignedBigInteger('debit_card_id')->nullable();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            
            $table->string('type')->comment('credit, debit, transfer, refund');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('completed')->comment('pending, completed, failed, refunded');
            $table->string('reference_id')->nullable()->comment('External transaction reference');
            $table->json('metadata')->nullable()->comment('Additional transaction details');
            $table->timestamp('transaction_date');
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('payment_provider_id')->references('id')->on('payment_providers')->onDelete('set null');
            $table->foreign('credit_card_id')->references('id')->on('credit_cards')->onDelete('set null');
            $table->foreign('debit_card_id')->references('id')->on('debit_cards')->onDelete('set null');
            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
