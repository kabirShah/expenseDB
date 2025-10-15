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
        Schema::table('multi_expense_members', function (Blueprint $table) {
            $table->foreignId('multi_expense_id')->constrained('multi_expenses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount_owed', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partially_paid', 'settled'])->default('pending');
            $table->uuid('multi_expense_member_id')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multi_expense_members', function (Blueprint $table) {
            $table->dropForeign(['multi_expense_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['multi_expense_id', 'user_id', 'amount_owed', 'amount_paid', 'status', 'multi_expense_member_id']);
        });
    }
};
