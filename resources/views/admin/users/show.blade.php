@extends('adminlte::page')

@section('title', 'View User')

@section('content_header')
    <h1>View User Details</h1>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Information</h3>
        </div>
        <div class="card-body">
            <p><strong>UUID:</strong> {{ $user->uuid }}</p>
            <p><strong>ID:</strong> {{ $user->id }}</p>
            <p><strong>Name:</strong> {{ $user->name }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
            <p><strong>Email Verified At:</strong> {{ $user->email_verified_at }}</p>
            <p><strong>Created At:</strong> {{ $user->created_at }}</p>
            <p><strong>Updated At:</strong> {{ $user->updated_at }}</p>
        </div>
        <div class="card-footer">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>

            @if($previousUser)
                <a href="{{ route('admin.users.show', $previousUser->id) }}" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
            @endif

            @if($nextUser)
                <a href="{{ route('admin.users.show', $nextUser->id) }}" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
            @endif
        </div>
    </div>
@endsection
