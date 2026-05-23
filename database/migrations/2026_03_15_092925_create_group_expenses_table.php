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
        Schema::create('group_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->notNull();
            $table->unsignedBigInteger('paid_by')->notNull()->comment('group_members.id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('title', 200)->notNull();
            $table->decimal('amount', 15, 2)->notNull();
            $table->enum('split_type', ['equal','exact','percentage','shares'])->notNull()->default('equal');
            $table->text('notes')->nullable();
            $table->string('receipt_image', 255)->nullable();
            $table->date('expense_date')->notNull();
            $table->unsignedBigInteger('created_by')->notNull()->comment('users.id');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('expense_groups')->onDelete('cascade');
            $table->foreign('paid_by')->references('id')->on('group_members')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_expenses');
    }
};
