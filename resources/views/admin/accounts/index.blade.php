@extends('adminlte::page')

@section('title', 'Accounts')

@section('content_header')
    <h1>Account List</h1>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <a href="{{ route('admin.accounts.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Account
            </a>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Account Name</th>
                        <th>Account Type</th>
                        <th>Currency</th>
                        <th>Balance</th>
                        <th width="220">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td>{{ $account->id }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ $account->account_type->name ?? 'N/A' }}</td>
                            <td>{{ $account->currency->code ?? 'N/A' }}</td>
                            <td>{{ number_format($account->initial_balance, 2) }}</td>
                            <td>
                                <!-- Read Action -->
                                <a href="{{ route('admin.accounts.show', $account->uuid) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>

                                <!-- Edit Action -->
                                <a href="{{ route('admin.accounts.edit', $account->uuid) }}" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>

                                <!-- Delete Action -->
                                <form action="{{ route('admin.accounts.destroy', $account->uuid) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this account?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach

                    @if($accounts->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center">No accounts found.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
