<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Ensure only authorized users (e.g., admins) can access
    public function __construct()
    {
        $this->middleware('auth');
        // You can also use a custom 'admin' middleware if needed
        // $this->middleware('admin');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Retrieve all users
        $all = User::all();
        
        // Return the AdminLTE-styled index view
        return view('admin.users.index', compact('all'));
    }

    /**
     * Display the specified user information.
     */
    public function show(User $user)
    {
        // Return a view to display a single user's details
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Return the create form
        return view('admin.users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate incoming data
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        // Create a new user
        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Redirect back with a success message
        return redirect()->route('admin.users.index')->with('success', 'User created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // Return the edit form
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // Validate incoming data
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:6',
        ]);

        // Update user details
        $user->name  = $request->name;
        $user->email = $request->email;
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->save();

        // Redirect back with a success message
        return redirect()->route('admin.users.index')->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Delete user
        $user->delete();

        // Redirect back with a success message
        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully!');
    }
}
