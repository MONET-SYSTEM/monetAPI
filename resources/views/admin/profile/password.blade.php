@extends('adminlte::page')

@section('title', 'Change Password')

@section('content_header')
    <h1>Change Password</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Update Your Password</h3>
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        <h5><i class="icon fas fa-ban"></i> Validation Errors</h5>
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fas fa-check"></i> {{ session('success') }}
                    </div>
                @endif

                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> Password Requirements</h5>
                    <ul class="mb-0">
                        <li>Minimum 6 characters long</li>
                        <li>Must confirm your new password</li>
                        <li>Current password is required for security</li>
                    </ul>
                </div>

                <form action="{{ route('profile.password.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="current_password">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   name="current_password" 
                                   id="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror" 
                                   placeholder="Enter your current password"
                                   required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleCurrentPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        @error('current_password')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   class="form-control @error('password') is-invalid @enderror" 
                                   placeholder="Enter your new password"
                                   required
                                   minlength="6">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleNewPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        @error('password')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" 
                                   name="password_confirmation" 
                                   id="password_confirmation"
                                   class="form-control @error('password_confirmation') is-invalid @enderror" 
                                   placeholder="Confirm your new password"
                                   required
                                   minlength="6">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleConfirmPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        @error('password_confirmation')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Password Strength Indicator -->
                    <div class="form-group">
                        <label>Password Strength:</label>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small id="passwordHelp" class="form-text text-muted">Enter a password to see strength indicator</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                        <a href="{{ route('profile.show') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="{{ route('profile.edit') }}" class="btn btn-info">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tips Card -->
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-shield-alt"></i> Security Tips
                </h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-check text-success"></i> Use a strong, unique password</li>
                    <li><i class="fas fa-check text-success"></i> Don't reuse passwords from other sites</li>
                    <li><i class="fas fa-check text-success"></i> Include numbers, symbols, and mixed case letters</li>
                    <li><i class="fas fa-check text-success"></i> Avoid personal information in passwords</li>
                    <li><i class="fas fa-check text-success"></i> Change passwords regularly</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@section('js')
<script>
    // Toggle password visibility
    function togglePasswordVisibility(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Event listeners for password toggle buttons
    document.getElementById('toggleCurrentPassword').addEventListener('click', function() {
        togglePasswordVisibility('current_password', 'toggleCurrentPassword');
    });

    document.getElementById('toggleNewPassword').addEventListener('click', function() {
        togglePasswordVisibility('password', 'toggleNewPassword');
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
        togglePasswordVisibility('password_confirmation', 'toggleConfirmPassword');
    });

    // Password strength checker
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('passwordStrength');
        const helpText = document.getElementById('passwordHelp');
        
        let strength = 0;
        let feedback = '';
        
        if (password.length >= 6) strength += 20;
        if (password.length >= 8) strength += 10;
        if (/[a-z]/.test(password)) strength += 20;
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[0-9]/.test(password)) strength += 15;
        if (/[^A-Za-z0-9]/.test(password)) strength += 15;
        
        strengthBar.style.width = strength + '%';
        
        if (strength < 30) {
            strengthBar.className = 'progress-bar bg-danger';
            feedback = 'Very weak password';
        } else if (strength < 50) {
            strengthBar.className = 'progress-bar bg-warning';
            feedback = 'Weak password';
        } else if (strength < 70) {
            strengthBar.className = 'progress-bar bg-info';
            feedback = 'Fair password';
        } else if (strength < 90) {
            strengthBar.className = 'progress-bar bg-primary';
            feedback = 'Good password';
        } else {
            strengthBar.className = 'progress-bar bg-success';
            feedback = 'Strong password';
        }
        
        helpText.textContent = feedback;
    });

    // Real-time password confirmation validation
    document.getElementById('password_confirmation').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmation = this.value;
        
        if (confirmation && password !== confirmation) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        } else if (confirmation && password === confirmation) {
            this.classList.add('is-valid');
            this.classList.remove('is-invalid');
        } else {
            this.classList.remove('is-invalid', 'is-valid');
        }
    });
</script>
@endsection
@endsection