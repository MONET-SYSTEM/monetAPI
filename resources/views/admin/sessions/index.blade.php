@extends('adminlte::page')

@section('title', 'Sessions Management')

@section('content_header')
    <div class="row">
        <div class="col-sm-6">
            <h1>Sessions Management</h1>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Sessions</li>
            </ol>
        </div>
    </div>
@endsection

@section('content')
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($stats['total']) }}</h3>
                    <p>Total Sessions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-globe"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($stats['authenticated']) }}</h3>
                    <p>Authenticated Users</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ number_format($stats['guests']) }}</h3>
                    <p>Guest Sessions</p>
                </div>
                <div class="icon">
                    <i class="fas fa-user-secret"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ number_format($stats['active_last_hour']) }}</h3>
                    <p>Active Last Hour</p>
                </div>
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>
                Active Sessions
            </h3>
            <div class="card-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-danger btn-sm dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-trash"></i> Bulk Actions
                    </button>
                    <div class="dropdown-menu">
                        <form action="{{ route('admin.sessions.destroy-all') }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to terminate all sessions except yours?')">
                                <i class="fas fa-users-slash text-danger"></i> Terminate All Sessions
                            </button>
                        </form>
                        <form action="{{ route('admin.sessions.destroy-guests') }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to terminate all guest sessions?')">
                                <i class="fas fa-user-slash text-warning"></i> Terminate Guest Sessions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('admin.sessions.index') }}" class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Name, email, or IP address" value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="user_type">User Type</label>
                        <select name="user_type" id="user_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="authenticated" {{ request('user_type') === 'authenticated' ? 'selected' : '' }}>Authenticated</option>
                            <option value="guest" {{ request('user_type') === 'guest' ? 'selected' : '' }}>Guest</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label for="activity_period">Activity Period</label>
                        <select name="activity_period" id="activity_period" class="form-control">
                            <option value="">All Time</option>
                            <option value="last_hour" {{ request('activity_period') === 'last_hour' ? 'selected' : '' }}>Last Hour</option>
                            <option value="last_day" {{ request('activity_period') === 'last_day' ? 'selected' : '' }}>Last 24 Hours</option>
                            <option value="last_week" {{ request('activity_period') === 'last_week' ? 'selected' : '' }}>Last Week</option>
                            <option value="last_month" {{ request('activity_period') === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="input-group">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            @if($sessions->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th style="width: 10px">#</th>
                                <th>User</th>
                                <th>Device Info</th>
                                <th>IP Address</th>
                                <th>Last Activity</th>
                                <th>Status</th>
                                <th style="width: 150px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $index => $session)
                                <tr class="{{ $session->is_current ? 'table-info' : '' }}">
                                    <td>{{ $sessions->firstItem() + $index }}</td>
                                    <td>
                                        @if($session->user_id)
                                            <div class="user-panel d-flex">
                                                <div class="image">
                                                    <img src="{{ $session->user_avatar ? asset('storage/' . $session->user_avatar) : asset('vendor/adminlte/dist/img/user2-160x160.jpg') }}" 
                                                         class="img-circle elevation-1" alt="User Image" style="width: 30px; height: 30px;">
                                                </div>
                                                <div class="info">
                                                    <strong>{{ $session->user_name }}</strong><br>
                                                    <small class="text-muted">{{ $session->user_email }}</small>
                                                </div>
                                            </div>
                                        @else
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-user-secret"></i> Guest
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $session->user_agent_parsed['browser'] }}</strong>
                                            <small class="text-muted">on {{ $session->user_agent_parsed['platform'] }}</small>
                                        </div>
                                        <div>
                                            <span class="badge badge-{{ $session->user_agent_parsed['device'] === 'Mobile' ? 'info' : ($session->user_agent_parsed['device'] === 'Tablet' ? 'warning' : 'secondary') }}">
                                                <i class="fas fa-{{ $session->user_agent_parsed['device'] === 'Mobile' ? 'mobile-alt' : ($session->user_agent_parsed['device'] === 'Tablet' ? 'tablet-alt' : 'desktop') }}"></i>
                                                {{ $session->user_agent_parsed['device'] }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <code>{{ $session->ip_address ?: 'Unknown' }}</code>
                                    </td>
                                    <td>
                                        <div title="{{ $session->last_activity_full }}">
                                            {{ $session->last_activity_formatted }}
                                        </div>
                                        <small class="text-muted">{{ $session->last_activity_full }}</small>
                                    </td>
                                    <td>
                                        @if($session->is_current)
                                            <span class="badge badge-success">
                                                <i class="fas fa-circle"></i> Current
                                            </span>
                                        @else
                                            <span class="badge badge-primary">
                                                <i class="fas fa-circle"></i> Active
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('admin.sessions.show', $session->id) }}" 
                                               class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if(!$session->is_current)
                                                <form action="{{ route('admin.sessions.destroy', $session->id) }}" 
                                                      method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" 
                                                            title="Terminate Session"
                                                            onclick="return confirm('Are you sure you want to terminate this session?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <button class="btn btn-sm btn-secondary" disabled title="Cannot terminate your own session">
                                                    <i class="fas fa-lock"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No sessions found</h5>
                    <p class="text-muted">Try adjusting your filters or search criteria.</p>
                </div>
            @endif
        </div>

        @if($sessions->hasPages())
            <div class="card-footer">
                <div class="row">
                    <div class="col-sm-5">
                        <div class="dataTables_info">
                            Showing {{ $sessions->firstItem() }} to {{ $sessions->lastItem() }} of {{ $sessions->total() }} sessions
                        </div>
                    </div>
                    <div class="col-sm-7">
                        {{ $sessions->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('js')
<script>
    // Auto-refresh every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);

    // Real-time updates notification
    $(document).ready(function() {
        // Add a subtle indicator that the page auto-refreshes
        $('<div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 70px; right: 20px; z-index: 1050;">' +
          '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
          '<i class="fas fa-sync-alt"></i> This page refreshes automatically every 30 seconds' +
          '</div>').appendTo('body').delay(5000).fadeOut();
    });
</script>
@endsection

@section('css')
<style>
    .table-responsive {
        max-height: 600px;
    }
    
    .user-panel {
        align-items: center;
    }
    
    .user-panel .info {
        margin-left: 10px;
        line-height: 1.2;
    }
    
    .small-box .inner h3 {
        font-size: 2.2rem;
        font-weight: bold;
    }
    
    .badge {
        font-size: 0.875em;
    }
    
    .table th {
        border-top: none;
    }
    
    .table-info {
        background-color: rgba(23, 162, 184, 0.1) !important;
    }
</style>
@endsection
