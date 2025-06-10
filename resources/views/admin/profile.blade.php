@extends('adminlte::page')

@section('title', 'Admin Profile')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                <i class="fas fa-user-circle text-primary"></i> Admin Profile
                <small class="text-muted">Manage your admin account</small>
            </h1>
        </div>
        <div>
            <span class="badge badge-info p-2">
                <i class="fas fa-user-shield"></i> 
                {{ Auth::guard('admin')->user()->name }}
            </span>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user"></i> Profile Information
                    </h3>
                </div>
                <form action="{{ route('admin.profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $admin->name) }}" 
                                   required>
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $admin->email) }}" 
                                   required>
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="role">
                                <i class="fas fa-shield-alt"></i> Role
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="role" 
                                   value="{{ ucfirst($admin->role) }}" 
                                   readonly>
                            <small class="form-text text-muted">
                                Your admin role cannot be changed from this interface.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar"></i> Account Created
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   value="{{ $admin->created_at->format('F d, Y \a\t H:i A') }}" 
                                   readonly>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-clock"></i> Last Updated
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   value="{{ $admin->updated_at->format('F d, Y \a\t H:i A') }}" 
                                   readonly>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-md-6">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-lock"></i> Change Password
                    </h3>
                </div>
                <form action="{{ route('admin.profile.password') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-key"></i> Current Password
                            </label>
                            <input type="password" 
                                   class="form-control @error('current_password') is-invalid @enderror" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                            @error('current_password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="password">
                                <i class="fas fa-lock"></i> New Password
                            </label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   minlength="8" 
                                   required>
                            @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                            <small class="form-text text-muted">
                                Password must be at least 8 characters long.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">
                                <i class="fas fa-lock"></i> Confirm New Password
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   minlength="8" 
                                   required>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Admin Statistics -->
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar"></i> Your Activity
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 text-center">
                            <div class="text-muted">Login Sessions</div>
                            <h4 class="text-info">{{ $stats['login_count'] ?? 'N/A' }}</h4>
                        </div>
                        <div class="col-6 text-center">
                            <div class="text-muted">Last Login</div>
                            <h4 class="text-info">
                                {{ $admin->last_login_at ? $admin->last_login_at->format('M d') : 'Never' }}
                            </h4>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-12">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Your admin account has access to all system features and user data.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .card-header {
            border-bottom: 2px solid rgba(0,0,0,0.1);
        }
        .form-group label {
            font-weight: 600;
        }
        .badge {
            font-size: 0.9em;
        }
        .text-info {
            color: #17a2b8 !important;
        }
    </style>
@stop

@section('js')
    <script>
        // Show success/error messages
        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        
        @if(session('error'))
            toastr.error('{{ session('error') }}');
        @endif
        
        // Password confirmation validation
        $('#password_confirmation').on('keyup', function() {
            var password = $('#password').val();
            var confirmPassword = $(this).val();
            
            if (password !== confirmPassword) {
                $(this).addClass('is-invalid');
                if ($('#password-match-error').length === 0) {
                    $(this).after('<div id="password-match-error" class="invalid-feedback">Passwords do not match.</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $('#password-match-error').remove();
            }
        });
    </script>
@stop
