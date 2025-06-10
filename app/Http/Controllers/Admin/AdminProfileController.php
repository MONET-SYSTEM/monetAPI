<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminProfileController extends Controller
{
    /**
     * Show the admin profile page.
     *
     * @return \Illuminate\View\View
     */
    public function show()
    {
        $admin = Auth::guard('admin')->user();
        
        $stats = [
            'login_count' => 0, // You can implement login tracking if needed
        ];

        return view('admin.profile', compact('admin', 'stats'));
    }

    /**
     * Update the admin profile information.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email,' . $admin->id,
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.profile')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $admin->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            return redirect()->route('admin.profile')
                ->with('success', 'Profile updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.profile')
                ->with('error', 'Error updating profile: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Update the admin password.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePassword(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->route('admin.profile')
                ->withErrors($validator)
                ->withInput();
        }

        // Check if current password is correct
        if (!Hash::check($request->current_password, $admin->password)) {
            return redirect()->route('admin.profile')
                ->with('error', 'Current password is incorrect.')
                ->withInput();
        }

        try {
            $admin->update([
                'password' => Hash::make($request->password),
            ]);

            return redirect()->route('admin.profile')
                ->with('success', 'Password updated successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.profile')
                ->with('error', 'Error updating password: ' . $e->getMessage());
        }
    }
}
