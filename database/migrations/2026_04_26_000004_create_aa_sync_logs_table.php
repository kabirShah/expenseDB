<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consent_id')->constrained('aa_consents')->cascadeOnDelete();
            $table->enum('status', ['SUCCESS', 'FAILED']);
            $table->string('response_code')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('consent_id');
            $table->index('status');
            $table->index('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_sync_logs');
    }
};