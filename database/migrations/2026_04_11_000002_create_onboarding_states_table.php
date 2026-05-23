<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('current_step')->default('START');
            $table->string('sync_status')->default('pending');
            $table->boolean('is_completed')->default(false);
            $table->boolean('permissions_granted')->default(false);
            $table->boolean('sync_consent_granted')->default(false);
            $table->string('last_sync_hash', 64)->nullable();
            $table->timestamp('sync_started_at')->nullable();
            $table->timestamp('sync_completed_at')->nullable();
            $table->json('step_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_states');
    }
};
