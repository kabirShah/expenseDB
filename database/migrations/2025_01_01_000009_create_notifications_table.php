<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->string('channel'); // email / whatsapp / sms
            $table->string('type'); // reminder / summary / settlement
            $table->json('payload');
            $table->enum('status', ['queued','sent','failed'])->default('queued');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('group_members');
        });
    }

    public function down(): void {
        Schema::dropIfExists('notifications');
    }
};
