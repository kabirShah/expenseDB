<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\OtpVerification;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\OtpService;
use RuntimeException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|digits:10|unique:users,phone',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:Male,Female,Other',
            'password' => 'required|string|min:8|confirmed',
            'currency' => 'nullable|string|max:10',
        ]);

        $resolvedName = trim((string) ($data['name'] ?? ''));
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if ((!$firstName || !$lastName) && $resolvedName !== '') {
            $parts = preg_split('/\s+/', $resolvedName, 2);
            $firstName = $firstName ?: ($parts[0] ?? 'User');
            $lastName = $lastName ?: ($parts[1] ?? 'User');
        }

        $currency = strtoupper($data['currency'] ?? 'INR');
        $fullName = $resolvedName !== '' ? $resolvedName : trim(($firstName ?? 'User') . ' ' . ($lastName ?? 'User'));

        $user = DB::transaction(function () use ($data, $firstName, $lastName, $fullName, $currency) {
            $user = User::create($this->filterColumns('users', [
                'first_name' => $firstName ?? 'User',
                'last_name' => $lastName ?? 'User',
                'name' => $fullName,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'currency' => $currency,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]));

            if (!$this->tableExists('wallets')) {
                return $user;
            }

            $walletLookup = $this->filterColumns('wallets', [
                'user_id' => $user->id,
                'is_default' => true,
            ]);

            $walletDefaults = $this->filterColumns('wallets', [
                'name' => 'Cash',
                'type' => 'cash',
                'currency' => $currency,
                'balance' => 0,
                'color' => '#4CAF50',
                'icon' => 'cash',
            ]);

            Wallet::firstOrCreate(
                empty($walletLookup) ? ['user_id' => $user->id] : $walletLookup,
                $walletDefaults
            );

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    private function filterColumns(string $table, array $attributes): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $columns = Schema::getColumnListing($table);

        return array_filter(
            $attributes,
            static fn ($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = collect();
        $totalBalance = 0.0;

        if ($this->tableExists('wallets')) {
            $wallets = Wallet::where('user_id', $user->id)->get();
            $totalBalance = (float) Wallet::where('user_id', $user->id)->sum('balance');
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'data' => $user,
            'wallets' => $wallets,
            'total_balance' => $totalBalance,
            'financial_container' => financialContainer($totalBalance),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|digits:10|unique:users,phone,' . $user->id,
            'dob' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:Male,Female,Other',
            'currency' => 'sometimes|string|max:10',
            'avatar' => 'sometimes|string|max:255',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk('public')->delete($user->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('profile-images', 'public');
            $data['avatar'] = $data['profile_image'];
        } elseif (isset($data['avatar']) && !isset($data['profile_image'])) {
            $data['profile_image'] = $data['avatar'];
        }

        if (isset($data['name']) && !isset($data['first_name']) && !isset($data['last_name'])) {
            $parts = preg_split('/\s+/', $data['name'], 2);
            $data['first_name'] = $parts[0] ?? $user->first_name;
            $data['last_name'] = $parts[1] ?? $user->last_name;
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
            'data' => $user->fresh(),
        ]);
    }

    public function setPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => 'required|digits_between:4,6',
        ]);

        $user = $request->user();
        $hash = Hash::make($data['pin']);
        $user->pin_code = $hash;
        $user->pin = $hash;
        $user->pin_enabled = true;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'PIN set successfully',
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => 'required',
        ]);

        $user = $request->user();
        $pinHash = $user->pin_code ?: $user->pin;
        $isValid = $pinHash && Hash::check($data['pin'], $pinHash);

        return response()->json([
            'success' => (bool) $isValid,
            'valid' => (bool) $isValid,
            'message' => $isValid ? 'PIN verified' : 'Invalid PIN',
        ], $isValid ? 200 : 422);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => 'nullable|digits:10',
            'phone' => 'nullable|digits:10',
        ]);

        $mobile = $data['mobile'] ?? $data['phone'] ?? null;
        if (!$mobile) {
            return response()->json([
                'success' => false,
                'message' => 'Mobile number is required.',
            ], 422);
        }

        $result = app(OtpService::class)->generateOtp($mobile);

        return response()->json([
            'success' => true,
            'message' => 'OTP generated',
            'otp' => $result['bypass'] ? $result['bypass_code'] : null,
            'expires_at' => $result['expires_at'],
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mobile' => 'nullable|digits:10',
            'phone' => 'nullable|digits:10',
            'otp' => 'nullable|digits:6',
            'otp_code' => 'nullable|digits:6',
        ]);

        $otpInput = $data['otp'] ?? $data['otp_code'] ?? null;
        if (!$otpInput) {
            return response()->json(['success' => false, 'message' => 'OTP is required'], 422);
        }

        $mobile = $data['mobile'] ?? $data['phone'] ?? null;
        if (!$mobile) {
            return response()->json([
                'success' => false,
                'message' => 'Mobile number is required.',
            ], 422);
        }

        try {
            $result = app(OtpService::class)->verifyOtp($mobile, $otpInput);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $result['token'],
            'user' => $result['user'],
            'bypass' => $result['bypass'],
        ]);
    }

    public function loginOtp(Request $request): JsonResponse
    {
        return $this->verifyOtp($request);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        $resetUrl = env('FRONTEND_URL') . "/reset-password?token={$token}&email={$request->email}";
        Mail::to($request->email)->send(new ResetPasswordMail($resetUrl));

        return response()->json(['message' => 'Password reset link sent']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return response()->json(['message' => 'Password reset successful']);
    }

    public function getSettings(Request $request): JsonResponse
    {
        $preferences = $request->user()->preferences;

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $request->user(),
                'notifications' => ['push' => true, 'email' => false],
                'security' => ['pin_enabled' => (bool) $request->user()->pin_enabled],
                'appearance' => [
                    'theme_mode' => $preferences?->theme_mode ?? 'system',
                    'use_system_theme' => $preferences?->use_system_theme ?? true,
                ],
            ],
        ]);
    }

    public function updateNotifications(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated',
            'data' => $request->all(),
        ]);
    }

    public function updateSecurity(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Security settings updated',
            'data' => $request->all(),
        ]);
    }

    public function supportRequest(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Support request submitted',
        ]);
    }
}
