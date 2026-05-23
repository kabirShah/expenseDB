<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('consent_id')->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('event_type');
            $table->index('consent_id');
            $table->index('processed');
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_webhook_logs');
    }
};