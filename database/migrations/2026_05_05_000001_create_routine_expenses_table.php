<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->dateTime('last_generated_at')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('reminder')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'start_date'], 'routine_user_status_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_expenses');
    }
};
