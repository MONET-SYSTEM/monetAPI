<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Nette\Utils\Random;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Carbon\Carbon;

class AuthService {    public function register(object $request) : User {

        // Create user
        $user = User::create([ 
            'uuid' => Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->otp($user);

        return $user;
    }

    public function login(object $request) : ?User
    {

        // Create user
        $user = User::where('email', $request->email)->first();
        if($user && Hash::check($request->password, $user->password)) {
            return $user;
        }
        return null;
    }

    public function otp(User $user, string $type = 'verification') : Otp {
        
        $tries = 3;
        $time = Carbon::now()->subMinutes(30);

        $count = Otp::where([
            'user_id' => $user->id,
            'type' => $type,
            'active' => 1
        ])->where('created_at', '>=', $time)->count();

        if($count >= $tries) {
            abort(422, __('app.to_many_requests'));
        }

        $code = random_int(100000, 999999);
        
        $otp = Otp::create([
            'user_id' => $user->id,
            'type' => $type, 
            'code' => $code,
            'active' => 1
        ]);
    
        // Send Mail
        Mail::to($user->email)->send(new OtpMail($user, $otp));
    
        return $otp;
    }    public function verify(User $user, object $request) : User {
        $otp = Otp::where([
            'user_id' => $user->id,
            'code' => $request->otp,
            'type' => 'verification',
            'active' => 1
        ])->first();

        if(!$otp) {
            abort(422, __('app.invalid_otp'));
        }    

        // Check if OTP is not too old (optional: add 10 minute expiry)
        $expiryTime = Carbon::parse($otp->created_at)->addMinutes(10);
        if (Carbon::now()->greaterThan($expiryTime)) {
            abort(422, __('app.otp_expired'));
        }

        // Update user verification
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Deactivate OTP
        $otp->active = 0;
        $otp->save();

        return $user;
    }

    public function getUserByEmail(string $email): User
    {
        return User::where('email', $email)->first();
    }    public function resetPassword(User $user, object $request): User
    {
        $otp = Otp::where([
            'user_id' => $user->id,
            'code' => $request->otp,
            'active' => 1,
            'type' => 'password-reset'
        ])->first();

        if (!$otp) {
            abort(422, __('app.invalid_otp'));
        }

        // Check if OTP is not too old (10 minute expiry for password reset)
        $expiryTime = Carbon::parse($otp->created_at)->addMinutes(10);
        if (Carbon::now()->greaterThan($expiryTime)) {
            abort(422, __('app.otp_expired'));
        }

        // Update password with proper hashing
        $user->password = Hash::make($request->password);
        $user->save();

        // Deactivate OTP
        $otp->active = 0;
        $otp->save();

        return $user;
    }
}