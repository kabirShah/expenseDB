<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 100);
            $table->decimal('amount', 15, 2);
            $table->enum('period', ['weekly', 'monthly', 'yearly', 'custom'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('alert_at')->default(80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};
