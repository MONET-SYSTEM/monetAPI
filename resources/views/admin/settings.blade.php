@extends('adminlte::page')

@section('title', 'System Settings')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                <i class="fas fa-cogs text-primary"></i> System Settings
                <small class="text-muted">Configure Monet application settings</small>
            </h1>
        </div>
        <div>
            <span class="badge badge-warning p-2">
                <i class="fas fa-exclamation-triangle"></i> 
                Admin Access Required
            </span>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <!-- Application Settings -->
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cog"></i> Application Settings
                    </h3>
                </div>
                <form action="{{ route('admin.settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="app_name">
                                <i class="fas fa-tag"></i> Application Name
                            </label>
                            <input type="text" 
                                   class="form-control @error('app_name') is-invalid @enderror" 
                                   id="app_name" 
                                   name="app_name" 
                                   value="{{ old('app_name', config('app.name')) }}" 
                                   required>
                            @error('app_name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="app_url">
                                <i class="fas fa-link"></i> Application URL
                            </label>
                            <input type="url" 
                                   class="form-control @error('app_url') is-invalid @enderror" 
                                   id="app_url" 
                                   name="app_url" 
                                   value="{{ old('app_url', config('app.url')) }}" 
                                   required>
                            @error('app_url')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="app_timezone">
                                <i class="fas fa-clock"></i> Application Timezone
                            </label>
                            <select class="form-control @error('app_timezone') is-invalid @enderror" 
                                    id="app_timezone" 
                                    name="app_timezone" 
                                    required>
                                @php
                                    $timezones = [
                                        'UTC' => 'UTC',
                                        'America/New_York' => 'Eastern Time',
                                        'America/Chicago' => 'Central Time',
                                        'America/Denver' => 'Mountain Time',
                                        'America/Los_Angeles' => 'Pacific Time',
                                        'Europe/London' => 'London',
                                        'Europe/Paris' => 'Paris',
                                        'Asia/Tokyo' => 'Tokyo',
                                        'Asia/Shanghai' => 'Shanghai',
                                        'Australia/Sydney' => 'Sydney'
                                    ];
                                @endphp
                                @foreach($timezones as $value => $label)
                                    <option value="{{ $value }}" {{ config('app.timezone') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('app_timezone')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" 
                                       class="custom-control-input" 
                                       id="app_debug" 
                                       name="app_debug" 
                                       value="1" 
                                       {{ config('app.debug') ? 'checked' : '' }}>
                                <label class="custom-control-label" for="app_debug">
                                    <i class="fas fa-bug"></i> Debug Mode
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Enable debug mode for development. <strong>Do not enable in production!</strong>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Application Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mail Settings -->
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-envelope"></i> Mail Configuration
                    </h3>
                </div>
                <form action="{{ route('admin.settings.mail') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="mail_driver">
                                <i class="fas fa-paper-plane"></i> Mail Driver
                            </label>
                            <select class="form-control" id="mail_driver" name="mail_driver">
                                <option value="smtp" {{ config('mail.default') == 'smtp' ? 'selected' : '' }}>SMTP</option>
                                <option value="mailgun" {{ config('mail.default') == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                                <option value="log" {{ config('mail.default') == 'log' ? 'selected' : '' }}>Log (Development)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="mail_host">
                                <i class="fas fa-server"></i> SMTP Host
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="mail_host" 
                                   name="mail_host" 
                                   value="{{ config('mail.mailers.smtp.host') }}" 
                                   placeholder="smtp.gmail.com">
                        </div>

                        <div class="form-group">
                            <label for="mail_port">
                                <i class="fas fa-plug"></i> SMTP Port
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   id="mail_port" 
                                   name="mail_port" 
                                   value="{{ config('mail.mailers.smtp.port') }}" 
                                   placeholder="587">
                        </div>

                        <div class="form-group">
                            <label for="mail_from_address">
                                <i class="fas fa-at"></i> From Email Address
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="mail_from_address" 
                                   name="mail_from_address" 
                                   value="{{ config('mail.from.address') }}" 
                                   placeholder="noreply@monet.com">
                        </div>

                        <div class="form-group">
                            <label for="mail_from_name">
                                <i class="fas fa-user"></i> From Name
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="mail_from_name" 
                                   name="mail_from_name" 
                                   value="{{ config('mail.from.name') }}" 
                                   placeholder="Monet Admin">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i> Save Mail Settings
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="testEmail()">
                            <i class="fas fa-paper-plane"></i> Test Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Database Information -->
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-database"></i> Database Information
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>Connection:</strong>
                        </div>
                        <div class="col-sm-8">
                            {{ config('database.default') }}
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>Host:</strong>
                        </div>
                        <div class="col-sm-8">
                            {{ config('database.connections.' . config('database.default') . '.host') ?: 'N/A' }}
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>Database:</strong>
                        </div>
                        <div class="col-sm-8">
                            {{ config('database.connections.' . config('database.default') . '.database') }}
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-4">
                            <strong>Tables:</strong>
                        </div>
                        <div class="col-sm-8">
                            <span class="badge badge-success">{{ $stats['table_count'] ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-success" onclick="testDatabase()">
                        <i class="fas fa-check"></i> Test Connection
                    </button>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="col-md-6">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server"></i> System Status
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>PHP Version:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="badge badge-info">{{ PHP_VERSION }}</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>Laravel Version:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="badge badge-info">{{ app()->version() }}</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>Environment:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="badge badge-{{ config('app.env') == 'production' ? 'success' : 'warning' }}">
                                {{ strtoupper(config('app.env')) }}
                            </span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-sm-6">
                            <strong>Storage:</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="badge badge-{{ is_writable(storage_path()) ? 'success' : 'danger' }}">
                                {{ is_writable(storage_path()) ? 'Writable' : 'Not Writable' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" class="btn btn-warning" onclick="clearCache()">
                        <i class="fas fa-broom"></i> Clear Cache
                    </button>
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
        hr {
            margin: 0.5rem 0;
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

        function testEmail() {
            $.post('{{ route('admin.settings.test-email') }}', {
                '_token': '{{ csrf_token() }}'
            })
            .done(function(response) {
                toastr.success('Test email sent successfully!');
            })
            .fail(function(xhr) {
                toastr.error('Failed to send test email: ' + xhr.responseJSON.message);
            });
        }

        function testDatabase() {
            $.post('{{ route('admin.settings.test-database') }}', {
                '_token': '{{ csrf_token() }}'
            })
            .done(function(response) {
                toastr.success('Database connection successful!');
            })
            .fail(function(xhr) {
                toastr.error('Database connection failed: ' + xhr.responseJSON.message);
            });
        }

        function clearCache() {
            $.post('{{ route('admin.settings.clear-cache') }}', {
                '_token': '{{ csrf_token() }}'
            })
            .done(function(response) {
                toastr.success('Cache cleared successfully!');
            })
            .fail(function(xhr) {
                toastr.error('Failed to clear cache: ' + xhr.responseJSON.message);
            });
        }
    </script>
@stop
