<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitwise_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('splitwise_group_id')->constrained('splitwise_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->string('role', 30)->default('member');
            $table->timestamps();

            $table->unique(['splitwise_group_id', 'user_id'], 'splitwise_group_member_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitwise_group_members');
    }
};
