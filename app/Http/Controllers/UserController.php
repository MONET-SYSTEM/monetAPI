<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Only allow logged-in users to access these actions.
    public function __construct()
    {
        $this->middleware('auth');
    }

    // List all users.
    public function index()
    {
        // Get every user from the database.
        $all = User::all();
        
        // Show the users list in the AdminLTE-styled view.
        return view('admin.users.index', compact('all'));
    }

    // Show details for a single user.
    public function show(User $user)
    {
        // Display a view with this user's details.
        return view('admin.users.show', compact('user'));
    }

    // Display the form to create a new user.
    public function create()
    {
        // Show the form for adding a new user.
        return view('admin.users.create');
    }

    // Save a new user to the database.
    public function store(Request $request)
    {
        // Check that the input data is valid.
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        // Add a new user using the validated data.
        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Redirect to the user list with a success message.
        return redirect()->route('admin.users.index')->with('success', 'User created successfully!');
    }

    // Display the form to edit an existing user.
    public function edit(User $user)
    {
        // Show the form pre-filled with the user's current data.
        return view('admin.users.edit', compact('user'));
    }

    // Update an existing user's data.
    public function update(Request $request, User $user)
    {
        // Check that the updated data is valid.
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        // Update the user's name and email.
        $user->name  = $request->name;
        $user->email = $request->email;
        
        // If a new password is provided, update it.
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->save();

        // Redirect to the user list with a success message.
        return redirect()->route('admin.users.index')->with('success', 'User updated successfully!');
    }

    // Delete a user.
    public function destroy(User $user)
    {   
        // Delete associated OTP records first
        $user->otps()->delete();
        
        // Remove the user from the database.
        $user->delete();

        // Redirect back to the user list with a success message.
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully!');
    }
}
