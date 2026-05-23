<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitwise_expense_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('splitwise_expense_id')->constrained('splitwise_expenses')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('splitwise_group_members')->cascadeOnDelete();
            $table->decimal('amount_owed', 15, 2);
            $table->boolean('is_settled')->default(false);
            $table->timestamps();

            $table->unique(['splitwise_expense_id', 'member_id'], 'splitwise_expense_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitwise_expense_splits');
    }
};
