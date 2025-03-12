@extends('adminlte::page')

@section('title', 'Edit User')

@section('content_header')
    <h1>Edit User</h1>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> Please correct the following errors.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT') 

                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" 
                           class="form-control" 
                           value="{{ old('name', $user->name) }}" 
                           placeholder="Enter name">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" 
                           class="form-control" 
                           value="{{ old('email', $user->email) }}" 
                           placeholder="Enter email">
                </div>

                <div class="form-group">
                    <label for="password">Password (leave blank to keep current):</label>
                    <input type="password" name="password" 
                           class="form-control" 
                           placeholder="Enter new password">
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Cancel
                </a>
            </form>
        </div>
    </div>
@endsection
