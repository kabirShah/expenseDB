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
        Schema::create('expense_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->notNull();
            $table->text('description')->nullable();
            $table->enum('type', ['trip','home','couple','office','other'])->notNull()->default('other');
            $table->unsignedBigInteger('created_by')->notNull();
            $table->string('avatar', 255)->nullable();
            $table->string('currency', 10)->notNull()->default('INR');
            $table->boolean('is_active')->notNull()->default(true);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_groups');
    }
};
