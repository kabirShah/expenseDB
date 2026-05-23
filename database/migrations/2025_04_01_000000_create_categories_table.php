<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
         Schema::create('categories', function (Blueprint $table) {

            $table->id();

            // 🔥 IMPORTANT: user-specific categories
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('name');
            $table->string('slug');

            // Optional (UI improvement)
            $table->string('icon')->nullable();

            $table->timestamps();

            // Index
            $table->index('user_id');

            // Unique per user
            $table->unique(['user_id', 'slug']);

            // Foreign key
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
