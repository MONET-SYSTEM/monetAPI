@extends('adminlte::page')

@section('title', 'My Profile')

@section('content_header')
    <h1>My Profile</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <!-- Profile Picture Card -->
        <div class="card card-primary card-outline">
            <div class="card-body box-profile">
                <div class="text-center">
                    @if($user->avatar)
                        <img class="profile-user-img img-fluid img-circle"
                             src="{{ asset('storage/avatars/' . $user->avatar) }}"
                             alt="User profile picture">
                    @else
                        <img class="profile-user-img img-fluid img-circle"
                             src="{{ asset('vendor/adminlte/dist/img/user4-128x128.jpg') }}"
                             alt="User profile picture">
                    @endif
                </div>

                <h3 class="profile-username text-center">{{ $user->name }}</h3>

                <p class="text-muted text-center">{{ $user->email }}</p>

                <div class="text-center">
                    <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="{{ route('profile.password.edit') }}" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>
        </div>

        <!-- About Me Box -->
        @if($user->bio)
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">About Me</h3>
            </div>
            <div class="card-body">
                <p>{{ $user->bio }}</p>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-8">
        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Profile Information</h3>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fas fa-check"></i> {{ session('success') }}
                    </div>
                @endif

                <table class="table table-bordered">
                    <tr>
                        <th style="width: 30%">Full Name</th>
                        <td>{{ $user->name }}</td>
                    </tr>
                    <tr>
                        <th>Email Address</th>
                        <td>{{ $user->email }}</td>
                    </tr>
                    @if($user->phone)
                    <tr>
                        <th>Phone Number</th>
                        <td>{{ $user->phone }}</td>
                    </tr>
                    @endif
                    @if($user->date_of_birth)
                    <tr>
                        <th>Date of Birth</th>
                        <td>{{ \Carbon\Carbon::parse($user->date_of_birth)->format('F j, Y') }}</td>
                    </tr>
                    @endif
                    @if($user->gender)
                    <tr>
                        <th>Gender</th>
                        <td>{{ ucfirst($user->gender) }}</td>
                    </tr>
                    @endif
                    @if($user->country)
                    <tr>
                        <th>Country</th>
                        <td>{{ $user->country }}</td>
                    </tr>
                    @endif
                    @if($user->city)
                    <tr>
                        <th>City</th>
                        <td>{{ $user->city }}</td>                    </tr>
                    @endif
                    <tr>
                        <th>Member Since</th>
                        <td>{{ $user->created_at->format('F j, Y') }}</td>
                    </tr>
                    <tr>
                        <th>Email Verified</th>
                        <td>
                            @if($user->email_verified_at)
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Verified
                                </span>
                            @else
                                <span class="badge badge-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Not Verified
                                </span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Account Statistics Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Account Statistics</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-success">
                                <i class="fas fa-wallet"></i>
                            </span>
                            <h5 class="description-header">{{ $user->accounts()->count() }}</h5>
                            <span class="description-text">ACCOUNTS</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="description-block border-right">
                            <span class="description-percentage text-warning">
                                <i class="fas fa-exchange-alt"></i>
                            </span>
                            <h5 class="description-header">{{ $user->transactions()->count() }}</h5>
                            <span class="description-text">TRANSACTIONS</span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="description-block">
                            <span class="description-percentage text-info">
                                <i class="fas fa-clock"></i>
                            </span>
                            <h5 class="description-header">{{ $user->updated_at->diffForHumans() }}</h5>
                            <span class="description-text">LAST UPDATED</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection