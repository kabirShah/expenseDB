<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();

            // REQUIRED BY CONTROLLER
            $table->uuid('balance_id')->unique();

            // Owner of the balance record
            $table->unsignedBigInteger('user_id');

            // Balance fields
            $table->string('source')->nullable();        // Salary, Bonus, etc.
            $table->decimal('amount', 10, 2);
            $table->dateTime('date_added')->nullable();  // Optional

            // Timestamps
            $table->timestamps();

            // Relationship
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('balances');
    }
};
