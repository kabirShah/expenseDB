<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('aa_account_id')->constrained('aa_accounts')->cascadeOnDelete();
            $table->string('transaction_id');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['CREDIT', 'DEBIT']);
            $table->text('narration');
            $table->string('reference')->nullable();
            $table->timestamp('txn_date');
            $table->timestamp('value_date')->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('category')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // Constraints
            $table->unique(['transaction_id', 'aa_account_id']);

            // Indexes
            $table->index('user_id');
            $table->index('txn_date');
            $table->index('type');
            $table->index(['user_id', 'txn_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_transactions');
    }
};