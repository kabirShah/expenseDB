<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('otp_verifications', 'identifier')) {
                $table->string('identifier')->nullable()->index()->after('phone');
            }
            if (!Schema::hasColumn('otp_verifications', 'type')) {
                $table->string('type', 30)->default('login')->after('otp_code');
            }
            if (!Schema::hasColumn('otp_verifications', 'is_used')) {
                $table->boolean('is_used')->default(false)->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $toDrop = [];
            foreach (['identifier', 'type', 'is_used'] as $column) {
                if (Schema::hasColumn('otp_verifications', $column)) {
                    $toDrop[] = $column;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
