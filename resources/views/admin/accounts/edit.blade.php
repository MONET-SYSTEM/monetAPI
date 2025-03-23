@extends('adminlte::page')

@section('title', 'Edit Account')

@section('content_header')
    <h1>Edit Account ({{ $account->name}})</h1>
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

        <form action="{{ route('admin.accounts.update', $account->uuid) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="account_type">Account Type:</label>
                <select name="account_type" id="account_type" class="form-control">
                    @foreach($accountTypes as $accountType)
                        <option value="{{ $accountType->uuid }}" 
                            @if(old('account_type', $account->account_type->uuid ?? '') == $accountType->uuid) selected @endif>
                            {{ $accountType->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="currency">Currency:</label>
                <select name="currency" id="currency" class="form-control">
                    @foreach($currencies as $currency)
                        <option value="{{ $currency->uuid }}"
                            @if(old('currency', $account->currency->uuid ?? '') == $currency->uuid) selected @endif>
                            {{ $currency->code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="name">Account Name:</label>
                <input type="text" name="name" 
                       class="form-control" 
                       value="{{ old('name', $account->name) }}" 
                       placeholder="Enter account name">
            </div>

            <div class="form-group">
                <label for="initial_balance">Initial Balance:</label>
                <input type="number" name="initial_balance" 
                       class="form-control" 
                       value="{{ old('initial_balance', $account->initial_balance) }}" 
                       placeholder="Enter initial balance" step="0.01">
            </div>

            <div class="form-group">
                <label for="colour_code">Colour Code:</label>
                <input type="text" name="colour_code" 
                       class="form-control" 
                       value="{{ old('colour_code', $account->colour_code) }}" 
                       placeholder="Enter colour code">
            </div>

            <div class="form-group">
                <label for="active">Active:</label>
                <select name="active" id="active" class="form-control">
                    <option value="1" @if(old('active', $account->active)) selected @endif>Yes</option>
                    <option value="0" @if(!old('active', $account->active)) selected @endif>No</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update
            </button>
        </form>
    </div>
    <!-- Navigation buttons -->
    <div class="card-footer d-flex justify-content-between align-items-center">
        <a href="{{ route('admin.accounts.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <div>
            @if($previousAccount)
                <a href="{{ route('admin.accounts.edit', $previousAccount->uuid) }}" class="btn btn-primary mr-1">
                    <i class="fas fa-arrow-left"></i> Previous
                </a>
            @endif

            @if($nextAccount)
                <a href="{{ route('admin.accounts.edit', $nextAccount->uuid) }}" class="btn btn-primary">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
