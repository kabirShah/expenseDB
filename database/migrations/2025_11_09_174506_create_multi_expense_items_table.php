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
        Schema::create('multi_expense_items', function (Blueprint $table) {
           $table->id();

            // Reference to multi_expenses table
            $table->foreignId('multi_expense_id')
                ->constrained('multi_expenses')
                ->onDelete('cascade');

            // Item details
            $table->string('item_name');
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('date')->nullable();

            // UUID for offline sync or unique tracking
            $table->uuid('multi_expense_item_id')->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multi_expense_items');
    }
};
