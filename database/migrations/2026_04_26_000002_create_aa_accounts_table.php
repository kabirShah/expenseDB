<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aa_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consent_id')->constrained('aa_consents')->cascadeOnDelete();
            $table->string('account_ref');
            $table->string('masked_account_number');
            $table->string('bank_name');
            $table->string('account_type'); // SAVINGS, CURRENT
            $table->string('ifsc')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('account_ref');
            $table->index('status');
            $table->index(['user_id', 'consent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aa_accounts');
    }
};