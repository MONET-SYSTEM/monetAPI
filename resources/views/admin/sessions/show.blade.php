@extends('adminlte::page')

@section('title', 'Session Details')

@section('content_header')
    <div class="row">
        <div class="col-sm-6">
            <h1>Session Details</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.sessions.index') }}">Sessions</a></li>
                <li class="breadcrumb-item active">Session Details</li>
            </ol>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <!-- Session Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle mr-1"></i>
                        Session Information
                    </h3>
                    @if($session->is_current)
                        <span class="badge badge-success float-right">Current Session</span>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">Session ID</dt>
                        <dd class="col-sm-9"><code>{{ $session->id }}</code></dd>

                        <dt class="col-sm-3">User</dt>
                        <dd class="col-sm-9">
                            @if($session->user_id)
                                <div class="user-panel d-flex">
                                    <div class="image">
                                        <img src="{{ $session->user_avatar ? asset('storage/' . $session->user_avatar) : asset('vendor/adminlte/dist/img/user2-160x160.jpg') }}" 
                                             class="img-circle elevation-1" alt="User Image" style="width: 40px; height: 40px;">
                                    </div>
                                    <div class="info ml-3">
                                        <strong>{{ $session->user_name }}</strong><br>
                                        <small class="text-muted">{{ $session->user_email }}</small><br>
                                        <small class="text-info">User ID: {{ $session->user_id }}</small>
                                    </div>
                                </div>
                            @else
                                <span class="badge badge-secondary badge-lg">
                                    <i class="fas fa-user-secret"></i> Guest Session
                                </span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">IP Address</dt>
                        <dd class="col-sm-9">
                            <code>{{ $session->ip_address ?: 'Unknown' }}</code>
                            @if($session->ip_address)
                                <a href="https://whatismyipaddress.com/ip/{{ $session->ip_address }}" 
                                   target="_blank" class="btn btn-xs btn-outline-info ml-2">
                                    <i class="fas fa-external-link-alt"></i> Lookup
                                </a>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Last Activity</dt>
                        <dd class="col-sm-9">
                            <div>
                                <strong>{{ $session->last_activity_formatted }}</strong>
                            </div>
                            <small class="text-muted">{{ $session->last_activity_full }}</small>
                        </dd>

                        <dt class="col-sm-3">Device Type</dt>
                        <dd class="col-sm-9">
                            <span class="badge badge-{{ $session->user_agent_parsed['device'] === 'Mobile' ? 'info' : ($session->user_agent_parsed['device'] === 'Tablet' ? 'warning' : 'secondary') }} badge-lg">
                                <i class="fas fa-{{ $session->user_agent_parsed['device'] === 'Mobile' ? 'mobile-alt' : ($session->user_agent_parsed['device'] === 'Tablet' ? 'tablet-alt' : 'desktop') }}"></i>
                                {{ $session->user_agent_parsed['device'] }}
                            </span>
                        </dd>

                        <dt class="col-sm-3">Browser</dt>
                        <dd class="col-sm-9">
                            <strong>{{ $session->user_agent_parsed['browser'] }}</strong>
                        </dd>

                        <dt class="col-sm-3">Platform</dt>
                        <dd class="col-sm-9">
                            <strong>{{ $session->user_agent_parsed['platform'] }}</strong>
                        </dd>

                        <dt class="col-sm-3">User Agent</dt>
                        <dd class="col-sm-9">
                            <details>
                                <summary class="btn btn-sm btn-outline-secondary">Show Full User Agent</summary>
                                <div class="mt-2">
                                    <code style="word-break: break-all;">{{ $session->user_agent }}</code>
                                </div>
                            </details>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Session Data -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database mr-1"></i>
                        Session Data
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if(is_array($session->payload_decoded) && !isset($session->payload_decoded['error']))
                        <div class="row">
                            @foreach($session->payload_decoded as $key => $value)
                                <div class="col-md-6 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body p-3">
                                            <h6 class="card-title text-primary">{{ ucfirst(str_replace('_', ' ', $key)) }}</h6>
                                            <div class="card-text">
                                                @if(is_array($value) || is_object($value))
                                                    <pre class="bg-dark text-white p-2 rounded" style="font-size: 0.8rem; max-height: 200px; overflow-y: auto;">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                @else
                                                    <code>{{ $value }}</code>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ $session->payload_decoded['error'] ?? 'No session data available or unable to decode session payload.' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions Panel -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cogs mr-1"></i>
                        Actions
                    </h3>
                </div>
                <div class="card-body">
                    @if(!$session->is_current)
                        <form action="{{ route('admin.sessions.destroy', $session->id) }}" method="POST" class="mb-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-block"
                                    onclick="return confirm('Are you sure you want to terminate this session?')">
                                <i class="fas fa-times"></i> Terminate Session
                            </button>
                        </form>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            This is your current session and cannot be terminated.
                        </div>
                    @endif

                    <a href="{{ route('admin.sessions.index') }}" class="btn btn-secondary btn-block">
                        <i class="fas fa-arrow-left"></i> Back to Sessions
                    </a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-bar mr-1"></i>
                        Quick Stats
                    </h3>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Session Age
                            <span class="badge badge-primary badge-pill">
                                {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(\Carbon\Carbon::now(), true) }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Session Type
                            <span class="badge badge-{{ $session->user_id ? 'success' : 'secondary' }} badge-pill">
                                {{ $session->user_id ? 'Authenticated' : 'Guest' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Data Size
                            <span class="badge badge-info badge-pill">
                                {{ number_format(strlen($session->payload)) }} bytes
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Security Info -->
            @if($session->user_id)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Security Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-success">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Authenticated</span>
                                        <span class="info-box-number">Yes</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-box bg-light">
                                    <span class="info-box-icon bg-info">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Active</span>
                                        <span class="info-box-number">{{ $session->last_activity_formatted }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('css')
<style>
    .user-panel {
        align-items: center;
    }
    
    .badge-lg {
        font-size: 0.9rem;
        padding: 0.5rem 0.75rem;
    }
    
    .info-box {
        margin-bottom: 0;
    }
    
    .info-box-content {
        padding: 5px;
    }
    
    .info-box-number {
        font-size: 0.9rem;
    }
    
    .info-box-text {
        font-size: 0.8rem;
    }
    
    details summary {
        cursor: pointer;
    }
    
    details summary:hover {
        background-color: #f8f9fa;
    }
</style>
@endsection
