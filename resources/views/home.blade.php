@extends('adminlte::page')

@section('title', 'MONET | Money Expense Tracker')

@section('content_header')
    <h1>
        <i class="fas fa-wallet"></i> MONET Dashboard
        <small class="text-muted">Your financial overview at a glance</small>
    </h1>
@stop

@section('content')
    <!-- Navigator Section -->
    <div class="row">
                <!-- Users Section Navigator -->
                <div class="col-lg-4 col-12">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>Users</h3>
                            <p>Manage user information</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="{{ route('admin.users.index') }}" class="small-box-footer">
                            Go to Users <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
        <!-- Account Section Navigator -->
        <div class="col-lg-4 col-12">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>Accounts</h3>
                    <p>Manage your accounts</p>
                </div>
                <div class="icon">
                    <i class="fas fa-university"></i>
                </div>
                <a href="{{ route('admin.accounts.index') }}" class="small-box-footer">
                    Go to Accounts <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        <!-- Trends Section Navigator -->
        <div class="col-lg-4 col-12">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>Trends</h3>
                    <p>View financial trends</p>
                </div>
                <div class="icon">
                    <i class="fas fa-chart-area"></i>
                </div>
                <a href="{{ route('admin.accounts.trends') }}" class="small-box-footer">
                    View Trends <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>
@stop