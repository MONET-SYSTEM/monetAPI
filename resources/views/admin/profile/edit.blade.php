@extends('adminlte::page')

@section('title', 'Edit Profile')

@section('content_header')
    <h1>Edit Profile</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Update Profile Information</h3>
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

                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="name" 
                                       id="name"
                                       class="form-control @error('name') is-invalid @enderror" 
                                       value="{{ old('name', $user->name) }}" 
                                       placeholder="Enter your full name"
                                       required>
                                @error('name')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address <span class="text-danger">*</span></label>
                                <input type="email" 
                                       name="email" 
                                       id="email"
                                       class="form-control @error('email') is-invalid @enderror" 
                                       value="{{ old('email', $user->email) }}" 
                                       placeholder="Enter your email address"
                                       required>
                                @error('email')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" 
                                       name="phone" 
                                       id="phone"
                                       class="form-control @error('phone') is-invalid @enderror" 
                                       value="{{ old('phone', $user->phone) }}" 
                                       placeholder="Enter your phone number">
                                @error('phone')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" 
                                       name="date_of_birth" 
                                       id="date_of_birth"
                                       class="form-control @error('date_of_birth') is-invalid @enderror" 
                                       value="{{ old('date_of_birth', $user->date_of_birth) }}">
                                @error('date_of_birth')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select name="gender" 
                                        id="gender" 
                                        class="form-control @error('gender') is-invalid @enderror">
                                    <option value="">Select Gender</option>
                                    <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="other" {{ old('gender', $user->gender) == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('gender')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" 
                                       name="country" 
                                       id="country"
                                       class="form-control @error('country') is-invalid @enderror" 
                                       value="{{ old('country', $user->country) }}" 
                                       placeholder="Enter your country">
                                @error('country')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" 
                                       name="city" 
                                       id="city"
                                       class="form-control @error('city') is-invalid @enderror" 
                                       value="{{ old('city', $user->city) }}" 
                                       placeholder="Enter your city">
                                @error('city')
                                    <span class="invalid-feedback">{{ $message }}</span>                            @enderror
                            </div>

                            <div class="form-group">
                                <label for="avatar">Profile Picture</label>
                                <div class="input-group">
                                    <div class="custom-file">
                                        <input type="file" 
                                               name="avatar" 
                                               id="avatar"
                                               class="custom-file-input @error('avatar') is-invalid @enderror" 
                                               accept="image/*">
                                        <label class="custom-file-label" for="avatar">Choose file</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Accepted formats: JPEG, PNG, JPG, GIF. Maximum size: 2MB.
                                </small>
                                @error('avatar')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                                
                                @if($user->avatar)
                                    <div class="mt-2">
                                        <img src="{{ asset('storage/avatars/' . $user->avatar) }}" 
                                             alt="Current Avatar" 
                                             class="img-thumbnail" 
                                             style="width: 100px; height: 100px; object-fit: cover;">
                                        <div class="mt-1">
                                            <form action="{{ route('profile.avatar.delete') }}" method="POST" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete your current avatar?')">
                                                    <i class="fas fa-trash"></i> Remove Avatar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Bio Section -->
                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea name="bio" 
                                  id="bio" 
                                  class="form-control @error('bio') is-invalid @enderror" 
                                  rows="4" 
                                  placeholder="Tell us about yourself">{{ old('bio', $user->bio) }}</textarea>
                        <small class="form-text text-muted">Maximum 500 characters.</small>
                        @error('bio')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-group text-center">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        <a href="{{ route('profile.show') }}" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="{{ route('profile.password.edit') }}" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@section('js')
<script>
    // Custom file input label update
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });
</script>
@endsection
@endsection