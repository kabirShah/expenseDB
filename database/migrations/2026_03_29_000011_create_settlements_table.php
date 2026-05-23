<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('expense_groups')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('related_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('settled_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'settled'])->default('pending');
            $table->string('method', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
