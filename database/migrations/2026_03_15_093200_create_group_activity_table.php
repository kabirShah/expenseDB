<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_activity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->notNull();
            $table->unsignedBigInteger('user_id')->notNull();
            $table->enum('type', ['expense_added','expense_edited','expense_deleted','member_added','member_removed','settlement'])->notNull();
            $table->unsignedBigInteger('entity_id')->nullable()->comment('ID of the related expense/settlement');
            $table->text('message')->notNull();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('expense_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_activity');
    }
};
