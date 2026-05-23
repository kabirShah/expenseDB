<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type', 30);
            $table->unsignedTinyInteger('threshold_percent');
            $table->decimal('spent_amount', 15, 2)->default(0);
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->date('budget_period_start');
            $table->date('budget_period_end');
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['budget_plan_id', 'threshold_percent', 'budget_period_start'], 'budget_alerts_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};
