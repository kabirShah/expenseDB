<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('consent_id')->unique();
            $table->string('consent_handle')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'EXPIRED', 'REVOKED'])->default('PENDING');
            $table->string('purpose_code');
            $table->string('purpose_text');
            $table->json('fi_types'); // e.g., ["TRANSACTIONS", "BALANCES"]
            $table->date('data_from');
            $table->date('data_to');
            $table->string('frequency_unit'); // DAY, MONTH
            $table->integer('frequency_value');
            $table->timestamp('expiry_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('consent_id');
            $table->index('expiry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_consents');
    }
};