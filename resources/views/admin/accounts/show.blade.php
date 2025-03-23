@extends('adminlte::page')

@section('title', 'View Account')

@section('content_header')
    <h1>View Account Details</h1>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Account Information</h3>
        </div>
        <div class="card-body">
            <p><strong>UUID:</strong> {{ $account->uuid }}</p>
            <p><strong>ID:</strong> {{ $account->id }}</p>
            <p><strong>Account Name:</strong> {{ $account->name }}</p>
            <p><strong>Account Type:</strong> {{ $account->account_type->name ?? 'N/A' }}</p>
            <p>
                <strong>Currency:</strong>
                {{ $account->currency->name ?? 'N/A' }} ({{ $account->currency->code ?? '' }})
            </p>
            <p>
                <strong>Initial Balance:</strong>
                @if(isset($account->currency->symbol_position) && $account->currency->symbol_position === 'before')
                    {{ $account->currency->symbol }} {{ number_format($account->initial_balance, 2) }}
                @else
                    {{ number_format($account->initial_balance, 2) }} {{ $account->currency->symbol }}
                @endif
            </p>
            <p><strong>Colour Code:</strong> {{ $account->colour_code }}</p>
            <p><strong>Active:</strong> {{ $account->active ? 'Yes' : 'No' }}</p>
            <p><strong>Created At:</strong> {{ $account->created_at }}</p>
            <p><strong>Updated At:</strong> {{ $account->updated_at }}</p>
        </div>
        <div class="card-footer">
            <!-- Back to List -->
            <a href="{{ route('admin.accounts.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>

            <!-- Previous Button (only if a previous record exists) -->
            @if($previous)
                <a href="{{ route('admin.accounts.show', $previous->uuid) }}" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
            @endif

            <!-- Next Button (only if a next record exists) -->
            @if($next)
                <a href="{{ route('admin.accounts.show', $next->uuid) }}" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
            @endif
        </div>
    </div>
@endsection
