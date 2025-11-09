<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');   // Who owns this balance
            $table->string('source');                // e.g., "Salary", "Bonus"
            $table->decimal('amount', 10, 2);        // Amount of money
            $table->date('date_added')->nullable();  // Optional date
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('balances');
    }
};
