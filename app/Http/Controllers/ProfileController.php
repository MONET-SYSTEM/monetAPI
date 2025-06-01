<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the user's profile
     */
    public function show()
    {
        $user = Auth::user();
        return view('admin.profile.show', compact('user'));
    }

    /**
     * Show the form for editing the user's profile
     */
    public function edit()
    {
        $user = Auth::user();
        return view('admin.profile.edit', compact('user'));
    }

    /**
     * Update the user's profile information
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|max:50',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->only([
            'name', 'email', 'phone', 'bio', 'date_of_birth', 
            'gender', 'country', 'city', 'timezone'
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::exists('public/avatars/' . $user->avatar)) {
                Storage::delete('public/avatars/' . $user->avatar);
            }

            $avatarName = time() . '_' . $user->id . '.' . $request->avatar->getClientOriginalExtension();
            $request->avatar->storeAs('public/avatars', $avatarName);
            $data['avatar'] = $avatarName;
        }

        $user->update($data);

        return redirect()->route('profile.show')
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Show the form for changing password
     */
    public function editPassword()
    {
        return view('admin.profile.password');
    }

    /**
     * Update the user's password
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.'
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Password updated successfully!');
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar()
    {
        $user = Auth::user();
        
        if ($user->avatar && Storage::exists('public/avatars/' . $user->avatar)) {
            Storage::delete('public/avatars/' . $user->avatar);
        }

        $user->update(['avatar' => null]);

        return redirect()->route('profile.edit')
            ->with('success', 'Avatar deleted successfully!');
    }
}
