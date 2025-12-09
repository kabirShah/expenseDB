<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('group_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('expense_uuid')->unique();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->decimal('total_amount', 12, 2);
            $table->enum('split_type', ['equal', 'custom', 'weight'])->default('equal');
            $table->date('date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('group_expenses');
    }
};
