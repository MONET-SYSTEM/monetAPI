@extends('layouts.app')  

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-r from-yellow-400 to-yellow-500">
        <!-- Container for the form -->
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
            
            <!-- Heading -->
            <h1 class="text-2xl font-bold text-gray-800 mb-1">CRUD OPERATIONS</h1>
            <p class="text-gray-500 mb-6">Sign In</p>
            <p class="text-gray-500 mb-8">Enter your credentials to access your account</p>
            
            <!-- Sign in form -->
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block mb-2 font-medium text-gray-700">Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"
                        placeholder="Enter your email"
                        required
                        autofocus
                    >
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label for="password" class="block mb-2 font-medium text-gray-700">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <!-- Sign in button -->
                <button
                    type="submit"
                    class="w-full bg-yellow-500 text-white py-2 px-4 rounded hover:bg-yellow-600 transition-colors"
                >
                    SIGN IN
                </button>
            </form>

            <!-- Forgot password link -->
            <div class="mt-4 text-center">
                @if (Route::has('password.request'))
                    <a class="text-sm text-yellow-500 hover:underline" href="{{ route('password.request') }}">
                        Forgot your password? Reset Password
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
