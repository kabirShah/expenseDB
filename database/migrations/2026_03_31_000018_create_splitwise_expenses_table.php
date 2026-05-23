<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitwise_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('splitwise_group_id')->constrained('splitwise_groups')->cascadeOnDelete();
            $table->foreignId('paid_by_member_id')->constrained('splitwise_group_members')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('INR');
            $table->date('expense_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitwise_expenses');
    }
};
