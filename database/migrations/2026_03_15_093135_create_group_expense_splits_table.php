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
        Schema::create('group_expense_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_expense_id')->notNull();
            $table->unsignedBigInteger('member_id')->notNull()->comment('group_members.id');
            $table->decimal('owed_amount', 15, 2)->notNull();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->integer('shares')->nullable();
            $table->boolean('is_settled')->notNull()->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->foreign('group_expense_id')->references('id')->on('group_expenses')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('group_members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_expense_splits');
    }
};
