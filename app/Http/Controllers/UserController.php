<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{    // Only allow admin users to access these actions.
    public function __construct()
    {
        $this->middleware('admin');
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
        // Fetch previous user by ID
        $previousUser = User::where('id', '<', $user->id)->orderBy('id', 'desc')->first();

        // Fetch next user by ID
        $nextUser = User::where('id', '>', $user->id)->orderBy('id', 'asc')->first();

        return view('admin.users.show', compact('user', 'previousUser', 'nextUser'));
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
        return redirect()->route('admin.users.index')->with('success', __('app.user_create'));
    }

    // Display the form to edit an existing user.
    public function edit(User $user)
    {   
        // Fetch previous user by ID
        $previousUser = User::where('id', '<', $user->id)->orderBy('id', 'desc')->first();

        // Fetch next user by ID
        $nextUser = User::where('id', '>', $user->id)->orderBy('id', 'asc')->first();

        // Show the form pre-filled with the user's current data.
        return view('admin.users.edit', compact('user', 'previousUser', 'nextUser'));
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
        return redirect()->route('admin.users.index')->with('success', __('app.user_update'));
    }

    // Delete a user.
    public function destroy(User $user)
    {   
        
        // Delete associated accounts first
        $user->accounts()->forceDelete();

        // Delete associated OTP records first
        $user->otps()->delete();
        
        // Remove the user from the database.
        $user->forceDelete();

        // Redirect back to the user list with a success message.
        return redirect()->route('admin.users.index')->with('success', __('app.user_delete'));
    }
}
