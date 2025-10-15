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
        Schema::create('user_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('owes_to_user_id');
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('original_amount', 15, 2)->nullable(); // Amount in original currency
            $table->decimal('exchange_rate', 10, 6)->default(1.0);
            $table->string('group_id')->nullable(); // For group-specific balances
            $table->text('description')->nullable();
            $table->timestamp('last_updated')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('owes_to_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');

            // Ensure unique balance between two users per group/currency
            $table->unique(['user_id', 'owes_to_user_id', 'group_id', 'currency_id'], 'unique_user_balance');

            $table->index(['user_id', 'group_id']);
            $table->index(['owes_to_user_id', 'group_id']);
            $table->index(['last_updated']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_balances');
    }
};
