<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class GoogleAuthService
{
    /**
     * Verify Google ID token and extract user information
     *
     * @param string $idToken
     * @return array|null
     * @throws Exception
     */
    public function verifyGoogleToken(string $idToken): ?array
    {
        try {
            // For now, we'll use Google's tokeninfo endpoint to verify the token
            // In production, you should use the Google API client library for better security
            $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $idToken;
            
            $response = file_get_contents($url);
            
            if ($response === false) {
                throw new Exception('Failed to verify Google token');
            }
            
            $payload = json_decode($response, true);
            
            if (isset($payload['error'])) {
                throw new Exception('Invalid Google token: ' . $payload['error']);
            }
            
            // Verify the token is for our app (you should set this in config)
            $expectedAudience = config('services.google.client_id');
            if ($expectedAudience && $payload['aud'] !== $expectedAudience) {
                throw new Exception('Token audience mismatch');
            }
            
            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'first_name' => $payload['given_name'] ?? '',
                'last_name' => $payload['family_name'] ?? '',
                'picture' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];
            
        } catch (Exception $e) {
            throw new Exception('Google token verification failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Find or create user from Google authentication
     *
     * @param array $googleUser
     * @return User
     */
    public function findOrCreateUser(array $googleUser): User
    {
        // First, try to find user by email
        $user = User::where('email', $googleUser['email'])->first();
        
        if ($user) {
            // Update Google ID if not set
            if (empty($user->google_id)) {
                $user->update(['google_id' => $googleUser['google_id']]);
            }
            
            // Update email verification if Google email is verified
            if ($googleUser['email_verified'] && !$user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }
            
            return $user;
        }
        
        // Create new user
        $user = User::create([
            'name' => $googleUser['name'],
            'email' => $googleUser['email'],
            'google_id' => $googleUser['google_id'],
            'password' => Hash::make(Str::random(32)), // Random password for Google users
            'email_verified_at' => $googleUser['email_verified'] ? now() : null,
            'profile_picture' => $googleUser['picture'],
        ]);
        
        return $user;
    }
    
    /**
     * Handle Google sign up/login process
     *
     * @param string $idToken
     * @return array
     * @throws Exception
     */
    public function authenticateWithGoogle(string $idToken): array
    {
        // Verify the Google token
        $googleUser = $this->verifyGoogleToken($idToken);
        
        if (!$googleUser) {
            throw new Exception('Invalid Google token');
        }
        
        // Find or create the user
        $user = $this->findOrCreateUser($googleUser);
        
        // Create access token
        $token = $user->createToken('google-auth')->plainTextToken;
        
        return [
            'user' => $user,
            'token' => $token,
            'is_new_user' => $user->wasRecentlyCreated
        ];
    }
}
