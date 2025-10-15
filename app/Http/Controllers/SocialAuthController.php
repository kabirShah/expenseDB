<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SocialAuthController extends Controller
{
     public function login(Request $request)
    {
        $idToken = $request->input('id_token');

        // 🔑 Verify Google ID Token with Google API
        $response = Http::get("https://oauth2.googleapis.com/tokeninfo", [
            'id_token' => $idToken
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Invalid Google token'], 401);
        }

        $googleUser = $response->json();

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $googleUser['email']],
            [
                'name' => $googleUser['name'] ?? 'Google User', // Use default if missing
                'password' => bcrypt(str()->random(16)), // random password
                'google_id' => $googleUser['sub'],       // Google user ID
                'avatar' => $googleUser['picture'] ?? null,
            ]
        );

        // Create Laravel token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }
     public function handleGoogleLogin(Request $request)
    {
        try {
            // Token from Ionic app
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->token);

            // Find or create user
            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ]
            );

            // Generate Laravel Sanctum Token
            $token = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => $user
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Google login failed', 'details' => $e->getMessage()], 500);
        }
    }
public function google(Request $request)
    {
        $request->validate(['idToken' => 'required|string']);

        $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($request->idToken);

        if (!$payload) {
            return response()->json(['error' => 'Invalid Google token'], 401);
        }

        $user = User::updateOrCreate(
            ['email' => $payload['email']],
            [
                'name' => $payload['name'] ?? 'Google User',
                'email_verified_at' => now()
            ]
        );

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function facebook(Request $request)
    {
        $request->validate(['accessToken' => 'required|string']);

        $facebookUser = Socialite::driver('facebook')->stateless()->userFromToken($request->accessToken);

        $user = User::updateOrCreate(
            ['email' => $facebookUser->getEmail() ?? 'fb_' . $facebookUser->getId() . '@example.com'],
            [
                'name' => $facebookUser->getName() ?? 'Facebook User',
                'email_verified_at' => now()
            ]
        );

        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }
}
