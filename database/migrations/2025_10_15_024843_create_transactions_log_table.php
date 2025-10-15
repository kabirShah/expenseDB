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
        Schema::create('transactions_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type'); // 'expense', 'settlement', 'balance_update', 'interest'
            $table->unsignedBigInteger('entity_id'); // ID of the related entity
            $table->string('entity_type'); // 'expense', 'settlement', 'balance'
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('exchange_rate', 10, 6)->default(1.0);
            $table->string('action'); // 'created', 'updated', 'deleted', 'settled'
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');

            $table->index(['transaction_type', 'entity_type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['group_id', 'created_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions_log');
    }
};
