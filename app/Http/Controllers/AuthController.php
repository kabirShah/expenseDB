<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; // 👈 Add this line
use Illuminate\Support\Facades\Password; // ✅ Add this
use Illuminate\Support\Facades\Hash;     
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;


class AuthController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset link sent!'])
            : response()->json(['message' => 'Unable to send reset link'], 400);
    }

    // Reset password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset!'])
            : response()->json(['message' => 'Invalid token or email'], 400);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'phone'      => 'sometimes|digits:10|unique:users,phone,' . $user->id,
            'dob'        => 'sometimes|date|before:today',
            'gender'     => 'sometimes|in:Male,Female,Other',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('profiles', $filename, 'public');
            
            // delete old image if exists
            if ($user->profile_image) {
                \Storage::disk('public')->delete($user->profile_image);
            }

            $user->profile_image = $path;
        }

        $user->update($request->except('profile_image'));

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);
    }
    public function googleLogin(Request $request)
    {
        $idToken = $request->input('idToken');

        if (!$idToken) {
            return response()->json(['error' => 'No token provided'], 400);
        }

        try {
            $client = new GoogleClient(['client_id' => env('GOOGLE_CLIENT_ID')]); 
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return response()->json(['error' => 'Invalid Google token'], 401);
            }

            $googleId = $payload['sub'];
            $email    = $payload['email'];
            $name     = $payload['name'];

            // 🔍 Find or Create user
            $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name'      => $name,
                    'email'     => $email,
                    'google_id' => $googleId,
                    'password'  => bcrypt(str()->random(16)) // random password
                ]);
            }

            // ✅ Create Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user'  => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    
    public function login(Request $request)
    {
    try {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8'
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
            'user' => $user
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Login failed',
            'error' => $e->getMessage()
        ], 500);
    }
}
    

    public function register(Request $request){
         try {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|digits:10|unique:users,phone',
            'dob' => 'required|date|before:today',
            'gender' => 'required|in:Male,Female,Other',
            'password' => 'required|string|min:8|confirmed',
        ]);

         if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'dob' => $request->dob,
            'gender' => $request->gender,
            'password' => Hash::make($request->password),
        ]);

        // Generate Token
        $token = $user->createToken('auth_token')->plainTextToken;

         return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
    }
}
