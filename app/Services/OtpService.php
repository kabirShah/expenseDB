<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class OtpService
{
    public function generateOtp(string $mobile): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $otp = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes((int) config('otp.expiry_minutes', 5));
        $user = User::where('phone', $mobile)->first();
        $hash = Hash::make($otp);

        DB::transaction(function () use ($mobile, $otp, $hash, $expiresAt, $user): void {
            $existingOtpQuery = OtpVerification::query();
            $this->whereMobileIdentifier($existingOtpQuery, $mobile);
            $this->whereUnusedOtp($existingOtpQuery);
            $existingOtpQuery->update($this->usedOtpAttributes());

            OtpVerification::create($this->filterColumns('otp_verifications', array_merge([
                'user_id' => $user?->id,
                'mobile' => $mobile,
                'phone' => $mobile,
                'identifier' => $mobile,
                'type' => 'login',
                'is_used' => false,
                'expires_at' => $expiresAt,
            ], $this->otpCodeAttributes($otp, $hash))));
        });

        return [
            'mobile' => $mobile,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'bypass' => $this->bypassEnabled(),
            'bypass_code' => (string) config('otp.bypass_code', '123456'),
        ];
    }

    public function verifyOtp(string $mobile, string $otp): array
    {
        $mobile = $this->normalizeMobile($mobile);

        return DB::transaction(function () use ($mobile, $otp): array {
            $bypass = $this->bypassEnabled() && hash_equals((string) config('otp.bypass_code', '123456'), $otp);

            $otpQuery = OtpVerification::query();
            $this->whereMobileIdentifier($otpQuery, $mobile);
            $this->whereUnusedOtp($otpQuery);

            $otpRow = $otpQuery->latest('id')->lockForUpdate()->first();

            if (!$bypass) {
                if (!$otpRow || $otpRow->expires_at->isPast() || !$this->otpMatches($otpRow, $otp)) {
                    throw new RuntimeException('Invalid or expired OTP');
                }
            }

            if ($otpRow) {
                $otpRow->forceFill($this->filterColumns('otp_verifications', [
                    'verified_at' => now(),
                    'is_used' => true,
                ]))->save();
            }

            $user = $this->findOrCreateUser($mobile);
            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'token' => $token,
                'user' => $user->fresh(),
                'bypass' => $bypass,
            ];
        });
    }

    public function bypassEnabled(): bool
    {
        return (bool) config('otp.bypass', false);
    }

    private function otpMatches(OtpVerification $otpRow, string $otp): bool
    {
        $hash = (string) ($otpRow->otp ?: $otpRow->otp_code);

        if ($hash === '') {
            return false;
        }

        if (password_get_info($hash)['algoName'] !== 'unknown') {
            return Hash::check($otp, $hash);
        }

        return hash_equals($otp, $hash);
    }

    private function findOrCreateUser(string $mobile): User
    {
        $user = User::where('phone', $mobile)->first();
        if ($user) {
            return $user;
        }

        $currency = 'INR';
        $user = User::create($this->filterColumns('users', [
            'first_name' => 'Mobile',
            'last_name' => 'User',
            'name' => 'Mobile User',
            'email' => $mobile . '@otp.pocketmoney.local',
            'phone' => $mobile,
            'dob' => '1970-01-01',
            'gender' => 'Other',
            'currency' => $currency,
            'password' => Hash::make(Str::random(32)),
            'is_active' => true,
        ]));

        if (Schema::hasTable('wallets')) {
            Wallet::firstOrCreate(
                $this->filterColumns('wallets', [
                    'user_id' => $user->id,
                    'is_default' => true,
                ]),
                $this->filterColumns('wallets', [
                    'name' => 'Cash',
                    'type' => 'cash',
                    'currency' => $currency,
                    'balance' => 0,
                    'color' => '#4CAF50',
                    'icon' => 'cash',
                ])
            );
        }

        return $user;
    }

    private function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/\D+/', '', $mobile) ?? '';
        if (strlen($mobile) > 10) {
            $mobile = substr($mobile, -10);
        }

        if (!preg_match('/^\d{10}$/', $mobile)) {
            throw new RuntimeException('Mobile number must be 10 digits');
        }

        return $mobile;
    }

    private function filterColumns(string $table, array $attributes): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function whereMobileIdentifier($query, string $mobile): void
    {
        $columns = array_values(array_intersect(
            ['mobile', 'phone', 'identifier'],
            $this->tableColumns('otp_verifications')
        ));

        if ($columns === []) {
            throw new RuntimeException('OTP verification table has no mobile identifier column');
        }

        $query->where(function ($query) use ($columns, $mobile) {
            foreach ($columns as $index => $column) {
                $index === 0
                    ? $query->where($column, $mobile)
                    : $query->orWhere($column, $mobile);
            }
        });
    }

    private function whereUnusedOtp($query): void
    {
        if (Schema::hasColumn('otp_verifications', 'is_used')) {
            $query->where('is_used', false);
            return;
        }

        if (Schema::hasColumn('otp_verifications', 'verified_at')) {
            $query->whereNull('verified_at');
        }
    }

    private function usedOtpAttributes(): array
    {
        return $this->filterColumns('otp_verifications', [
            'is_used' => true,
            'verified_at' => now(),
        ]);
    }

    private function otpCodeAttributes(string $otp, string $hash): array
    {
        if (Schema::hasColumn('otp_verifications', 'otp')) {
            return [
                'otp' => $hash,
                'otp_code' => $hash,
            ];
        }

        return [
            'otp_code' => $otp,
        ];
    }

    private function tableColumns(string $table): array
    {
        return Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
    }
}
