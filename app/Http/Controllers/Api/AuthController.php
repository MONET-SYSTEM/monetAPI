<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            'timezone' => 'nullable|string|max:50',
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
            'timezone' => $request->timezone ?? 'UTC',
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
            'current_password' => 'required|string',
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
    
}