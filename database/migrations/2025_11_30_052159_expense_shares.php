<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('expense_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('member_id');
            $table->decimal('share_amount', 12, 2);
            $table->decimal('amount_settled', 12, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'settled'])->default('pending');
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('group_expenses')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('group_members')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('expense_shares');
    }
};
