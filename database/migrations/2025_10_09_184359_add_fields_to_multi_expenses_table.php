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
        Schema::table('multi_expenses', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->decimal('total_amount', 10, 2);
            $table->text('description')->nullable();
            $table->string('category');
            $table->enum('split_type', ['equal', 'percentage', 'custom']);
            $table->json('members')->nullable();
            $table->uuid('multi_expense_id')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('multi_expenses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'title', 'total_amount', 'description', 'category', 'split_type', 'members', 'multi_expense_id']);
        });
    }
};
