<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void {
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id')->nullable(); // if member is app user
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            $table->boolean('is_app_user')->default(false);

            // Notification preferences
            $table->json('notification_preferences')->nullable(); 
            /*
                {
                  "email": true,
                  "sms": false,
                  "whatsapp": true
                }
            */

            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('group_members');
    }
};
