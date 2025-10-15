<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void {
        Schema::create('multi_expense_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('multi_expense_id')->constrained('multi_expenses')->onDelete('cascade'); // Ensure this line exists
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount_owed', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partially_paid', 'settled'])->default('pending');
            $table->uuid('multi_expense_member_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multi_expense_members');
    }
};
