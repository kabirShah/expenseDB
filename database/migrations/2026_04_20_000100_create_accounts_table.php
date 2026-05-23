<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('setu');
            $table->string('account_ref')->index();
            $table->string('masked_account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->enum('type', ['savings', 'current'])->default('savings');
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'account_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
