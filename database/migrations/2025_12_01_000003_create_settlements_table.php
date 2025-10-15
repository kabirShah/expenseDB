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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('settlement_id')->unique();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('paid_by')->constrained('users')->onDelete('cascade'); // Who paid
            $table->foreignId('paid_to')->constrained('users')->onDelete('cascade'); // Who received
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->json('related_expenses')->nullable(); // IDs of expenses this settlement covers
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
