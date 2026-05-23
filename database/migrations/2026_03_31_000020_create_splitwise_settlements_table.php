<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitwise_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('splitwise_group_id')->constrained('splitwise_groups')->cascadeOnDelete();
            $table->foreignId('payer_member_id')->constrained('splitwise_group_members')->cascadeOnDelete();
            $table->foreignId('payee_member_id')->constrained('splitwise_group_members')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('settled_at');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitwise_settlements');
    }
};
