@extends('adminlte::page')

@section('title', 'Create User')

@section('content_header')
    <h1>Create New User</h1>
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

            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" name="name" 
                           class="form-control" 
                           value="{{ old('name') }}" 
                           placeholder="Enter name">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" 
                           class="form-control" 
                           value="{{ old('email') }}" 
                           placeholder="Enter email">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" 
                           class="form-control" 
                           placeholder="Enter password">
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Cancel
                </a>
            </form>
        </div>
    </div>
@endsection
