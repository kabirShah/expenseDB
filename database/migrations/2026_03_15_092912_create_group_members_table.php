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
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->notNull();
            $table->unsignedBigInteger('user_id')->nullable()->comment('NULL if non-app member');
            $table->string('name', 100)->nullable()->comment('Used when user_id is NULL');
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->enum('role', ['admin','member'])->notNull()->default('member');
            $table->timestamp('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('expense_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['group_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_members');
    }
};
