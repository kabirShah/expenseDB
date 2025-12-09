<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->unsignedBigInteger('from_member_id');
            $table->unsignedBigInteger('to_member_id');
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('cash'); // cash/upi/wallet/bank
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('group_expenses')->onDelete('cascade');
            $table->foreign('from_member_id')->references('id')->on('group_members');
            $table->foreign('to_member_id')->references('id')->on('group_members');
        });
    }

    public function down(): void {
        Schema::dropIfExists('settlements');
    }
};
