<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
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
    
}