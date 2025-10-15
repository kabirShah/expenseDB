<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('split_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('split_expense_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->decimal('total_amount', 12, 2);
            $table->json('participants'); // array of participants [{user_id, amount_owed, amount_paid, status}]
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_expenses');
    }
};
