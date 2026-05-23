<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->notNull();
            $table->unsignedBigInteger('payer_id')->notNull()->comment('group_members.id — who paid');
            $table->unsignedBigInteger('payee_id')->notNull()->comment('group_members.id — who received');
            $table->decimal('amount', 15, 2)->notNull();
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->unsignedBigInteger('recorded_by')->notNull()->comment('users.id');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('expense_groups')->onDelete('cascade');
            $table->foreign('payer_id')->references('id')->on('group_members')->onDelete('cascade');
            $table->foreign('payee_id')->references('id')->on('group_members')->onDelete('cascade');
            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_settlements');
    }
};
