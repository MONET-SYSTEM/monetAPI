<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{  
    protected AuthService $authService;
    
    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }
    
    public function register(Request $request) : Response {
        // Validate user
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'password' => 'required|min:6|max:255',
        ]);
        // Create user
        $user = $this->authService->register($request);
        // Create Access token
        $token = $user->createToken('auth')->plainTextToken;
        // Return
        return response([
            'message' => __('app.registration_success'),
            'result' => [
                'user' => new UserResource($user),
                'token' => $token
            ]
        ], 201);
    }
    
    public function login(Request $request) : Response {
        $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|min:6|max:255',
        ]);
        // Login user
        $user = $this->authService->login($request);
        if(!$user) {
            return response([
                'message' => __('auth.failed'),
            ], 401);
        }
        // Create Access token
        $token = $user->createToken('auth')->plainTextToken;
        // Return
        return response([
            'message' => $user->email_verified_at ? __('app.login_success') : __('app.login_success_verify'),
            'result' => [
                'user' => new UserResource($user),
                'token' => $token
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        // Get the authenticated user
        /** @var User $user */
        $user = Auth::user();
        
        // Perform logout based on the guard being used
        if (Auth::guard('sanctum')->check()) {
            // For Sanctum
            $request->user()->currentAccessToken();
        } else if (Auth::guard('api')->check()) {
            // For Passport
            $user->tokens->each(function ($token) {
                $token->revoke();
            });
        } else {
            // For session-based auth
            Auth::logout(); 
        }
        
        // return
        return response([
            'message' => __('app.logout_success')
        ]);
    }
    
    public function otp(Request $request) : Response {
        /** @var User $user */
        $user = Auth::user();
        
        /** @var Otp $otp */
        $otp = $this->authService->otp($user);
        
        return response([
            'message' => __('app.otp_sent'),
        ]);
    }


    public function verify(Request $request) : Response {

        // Validate the request
        $request->validate([
            'otp' => 'required|numeric',
        ]);

        /** @var User $user */
        $user = Auth::user();
        
        // Verify the OTP
        /** @var Otp $otp */
        $user = $this->authService->verify($user, $request);
        
        return response([
            'message' => __('app.verification_success'),
            'result' => [
                'user' => new UserResource($user)
            ]
        ]);
    }

    public function resetOtp(Request $request) : Response {

        // Validate request
        $request->validate([
            'email' => 'required|email|max:255|exists:users,email', 
        ]);

        // Get user
        $user = $this->authService->getUserByEmail($request->email);
        
        /** @var Otp $otp */
        $otp = $this->authService->otp($user, 'password-reset');
        
        return response([
            'message' => __('app.otp_sent'),
        ]);
    }

    public function resetPassword(Request $request) : Response {

        // Validate request
        $request->validate([
            'email' => 'required|email|max:255|exists:users,email', 
            'otp' => 'required|numeric',
            'password' => 'required|min:6|max:255|confirmed',
            'password_confirmation' => 'required|min:6|max:255',
            
        ]);

        // Get user
        $user = $this->authService->getUserByEmail($request->email);
        
        $user = $this->authService->resetPassword($user, $request);
        
        return response([
            'message' => __('app.password_reset_success'),
        ]);
    }
    
    /**
     * Get current user profile
     */
    public function profile(Request $request): Response 
    {
        /** @var User $user */
        $user = Auth::user();
        
        return response([
            'message' => 'Profile retrieved successfully',
            'result' => [
                'user' => new UserResource($user)
            ]
        ], 200);
    }
    
    /**
     * Update current user profile
     */
    public function updateProfile(Request $request): Response 
    {
        // Validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
        ]);

        /** @var User $user */
        $user = Auth::user();
        
        // Update user profile
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'bio' => $request->bio,
            'avatar' => $request->avatar,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'country' => $request->country,
            'city' => $request->city,
        ]);

        return response([
            'message' => 'Profile updated successfully',
            'result' => [
                'user' => new UserResource($user->fresh())
            ]
        ], 200);
    }
    
    /**
     * Update current user password
     */
    public function updatePassword(Request $request): Response 
    {
        // Validate request
        $request->validate([
            'current_password' => 'required|string|min:6',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
        ]);

        /** @var User $user */
        $user = Auth::user();
        
        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response([
                'message' => 'Current password is incorrect',
                'errors' => [
                    'current_password' => ['Current password is incorrect']
                ]
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response([
            'message' => 'Password updated successfully'
        ], 200);
    }
    
    /**
     * Handle Google Sign Up
     */
    public function googleSignUp(Request $request){
    try {
        $validator = Validator::make($request->all(), [
            'id_token' => 'required_without:access_token|string',
            'access_token' => 'required_without:id_token|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please provide either id_token or access_token.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the token (prefer id_token, fallback to access_token)
        $token = $request->id_token ?? $request->access_token;
        $tokenType = $request->id_token ? 'id_token' : 'access_token';

        // Verify Google Token
        if ($tokenType === 'id_token') {
            // Verify ID Token using tokeninfo endpoint
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $token
            ]);
        } else {
            // Verify Access Token using tokeninfo endpoint
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'access_token' => $token
            ]);
        }

        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token'
            ], 401);
        }

        $googleUser = $response->json();

        // Debug: Log the token details for troubleshooting
        Log::info('Google Token Debug', [
            'audience' => $googleUser['aud'] ?? 'not_provided',
            'issuer' => $googleUser['iss'] ?? 'not_provided',
            'expected_audience' => config('services.google.android_client_id'),
        ]);

        // Verify the token is for your Flutter app
        $tokenAudience = $googleUser['aud'] ?? '';
        
        // Accept multiple possible client IDs from your Firebase project
        $validAudiences = [
            '167138283065-bmvbrj0esp9290mh9j65uhmssr6agioo.apps.googleusercontent.com', // Android client
            '167138283065-omjm4amr3ko1q887uv3keu17opj7pl6i.apps.googleusercontent.com', // Web client
        ];
        
        if (!in_array($tokenAudience, $validAudiences)) {
            return response()->json([
                'success' => false,
                'message' => 'Token not issued for this application',
                'debug' => [
                    'received_audience' => $tokenAudience,
                    'expected_audiences' => $validAudiences
                ]
            ], 401);
        }

        // Check if user exists
        $user = User::where('email', $googleUser['email'])->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $googleUser['name'] ?? $googleUser['email'],
                'email' => $googleUser['email'],
                'google_id' => $googleUser['sub'],
                'profile_picture' => $googleUser['picture'] ?? null,
                'email_verified_at' => now(), // Google emails are pre-verified
                'password' => Hash::make(Str::random(32)), // Random password since they use Google
            ]);
        } else {
            // Update existing user with Google info
            $user->update([
                'google_id' => $googleUser['sub'],
                'profile_picture' => $googleUser['picture'] ?? $user->profile_picture,
            ]);
        }

        // Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Google authentication successful',
            'result' => [
                'user' => new UserResource($user),
                'token' => $token
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Google authentication failed',
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Handle Google Login (Existing Users Only)
     */
    public function googleLogin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_token' => 'required_without:access_token|string',
                'access_token' => 'required_without:id_token|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please provide either id_token or access_token.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the token (prefer id_token, fallback to access_token)
            $token = $request->id_token ?? $request->access_token;
            $tokenType = $request->id_token ? 'id_token' : 'access_token';

            // Verify Google Token
            if ($tokenType === 'id_token') {
                // Verify ID Token using tokeninfo endpoint
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $token
                ]);
            } else {
                // Verify Access Token using tokeninfo endpoint
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'access_token' => $token
                ]);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token'
                ], 401);
            }

            $googleUser = $response->json();

            // Debug: Log the token details for troubleshooting
            Log::info('Google Token Debug - Login', [
                'audience' => $googleUser['aud'] ?? 'not_provided',
                'issuer' => $googleUser['iss'] ?? 'not_provided',
                'expected_audience' => config('services.google.android_client_id'),
            ]);

            // Verify token audience (Flutter app)
            $tokenAudience = $googleUser['aud'] ?? '';
            
            // Accept multiple possible client IDs from your Firebase project
            $validAudiences = [
                '167138283065-bmvbrj0esp9290mh9j65uhmssr6agioo.apps.googleusercontent.com', // Android client
                '167138283065-omjm4amr3ko1q887uv3keu17opj7pl6i.apps.googleusercontent.com', // Web client
            ];
            
            if (!in_array($tokenAudience, $validAudiences)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not issued for this application',
                    'debug' => [
                        'received_audience' => $tokenAudience,
                        'expected_audiences' => $validAudiences
                    ]
                ], 401);
            }

            // Check if user exists
            $user = User::where('email', $googleUser['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email. Please sign up first.'
                ], 404);
            }

            // Update existing user with Google info
            $user->update([
                'google_id' => $user->google_id ?? $googleUser['sub'],
                'profile_picture' => $googleUser['picture'] ?? $user->profile_picture,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            // Create Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Google login successful',
                'result' => [
                    'user' => new UserResource($user),
                    'token' => $token
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handle Google Authentication (Auto Login/Signup)
     */
    public function googleAuth(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_token' => 'required_without:access_token|string',
                'access_token' => 'required_without:id_token|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please provide either id_token or access_token.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the token (prefer id_token, fallback to access_token)
            $token = $request->id_token ?? $request->access_token;
            $tokenType = $request->id_token ? 'id_token' : 'access_token';

            // Verify Google Token
            if ($tokenType === 'id_token') {
                // Verify ID Token using tokeninfo endpoint
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $token
                ]);
            } else {
                // Verify Access Token using tokeninfo endpoint
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'access_token' => $token
                ]);
            }

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token'
                ], 401);
            }

            $googleUser = $response->json();

            // Debug: Log the token details for troubleshooting
            Log::info('Google Token Debug - Auth', [
                'audience' => $googleUser['aud'] ?? 'not_provided',
                'issuer' => $googleUser['iss'] ?? 'not_provided',
                'expected_audience' => config('services.google.android_client_id'),
            ]);

            // Verify token audience (Flutter app)
            $tokenAudience = $googleUser['aud'] ?? '';
            
            // Accept multiple possible client IDs from your Firebase project
            $validAudiences = [
                '167138283065-bmvbrj0esp9290mh9j65uhmssr6agioo.apps.googleusercontent.com', // Android client
                '167138283065-omjm4amr3ko1q887uv3keu17opj7pl6i.apps.googleusercontent.com', // Web client
            ];
            
            if (!in_array($tokenAudience, $validAudiences)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not issued for this application',
                    'debug' => [
                        'received_audience' => $tokenAudience,
                        'expected_audiences' => $validAudiences
                    ]
                ], 401);
            }

            // Check if user exists
            $user = User::where('email', $googleUser['email'])->first();
            $isNewUser = false;

            if (!$user) {
                // Create new user (Sign-up)
                $user = User::create([
                    'name' => $googleUser['name'] ?? $googleUser['email'],
                    'email' => $googleUser['email'],
                    'google_id' => $googleUser['sub'],
                    'profile_picture' => $googleUser['picture'] ?? null,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                ]);
                $isNewUser = true;
            } else {
                // Update existing user (Login)
                $user->update([
                    'google_id' => $user->google_id ?? $googleUser['sub'],
                    'profile_picture' => $googleUser['picture'] ?? $user->profile_picture,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }

            // Create Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => $isNewUser ? 'Google signup successful' : 'Google login successful',
                'result' => [
                    'user' => new UserResource($user),
                    'token' => $token
                ],
                'is_new_user' => $isNewUser
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}