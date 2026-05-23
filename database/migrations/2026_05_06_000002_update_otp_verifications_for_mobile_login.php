<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            if (!Schema::hasColumn('otp_verifications', 'mobile')) {
                $table->string('mobile', 10)->nullable()->index()->after('id');
            }
            if (!Schema::hasColumn('otp_verifications', 'otp')) {
                $table->string('otp')->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('otp_verifications', 'is_used')) {
                $table->boolean('is_used')->default(false)->after('verified_at');
            }
        });

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('otp_verifications', 'otp_code')) {
            DB::statement('ALTER TABLE otp_verifications MODIFY otp_code VARCHAR(255) NULL');
        }

        DB::table('otp_verifications')
            ->whereNull('mobile')
            ->whereNotNull('phone')
            ->update(['mobile' => DB::raw('phone')]);
    }

    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            $toDrop = [];
            foreach (['mobile', 'otp'] as $column) {
                if (Schema::hasColumn('otp_verifications', $column)) {
                    $toDrop[] = $column;
                }
            }

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
