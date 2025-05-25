@extends('adminlte::page')

@section('title', 'Transactions')

@section('content_header')
    <div class="d-flex justify-content-between">
        <h1>Transactions</h1>
        <div>
            <a href="{{ route('admin.transactions.create') }}" class="btn btn-primary mr-2">
                <i class="fas fa-plus"></i> Add New Transaction
            </a>
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#transferModal">
                <i class="fas fa-exchange-alt"></i> Transfer Funds
            </button>
        </div>
    </div>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction Filters</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.transactions.index') }}" method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="account_id" class="mr-1">Account:</label>
                        <select name="account_id" id="account_id" class="form-control form-control-sm">
                            <option value="">All Accounts</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }} ({{ $account->currency->symbol }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="type" class="mr-1">Type:</label>
                        <select name="type" id="type" class="form-control form-control-sm">
                            <option value="">All Types</option>
                            <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Income</option>
                            <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Expense</option>
                            <option value="transfer" {{ request('type') == 'transfer' ? 'selected' : '' }}>Transfer</option>
                        </select>
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="category_id" class="mr-1">Category:</label>
                        <select name="category_id" id="category_id" class="form-control form-control-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="start_date" class="mr-1">From:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" 
                               value="{{ request('start_date', now()->subMonth()->format('Y-m-d')) }}">
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="end_date" class="mr-1">To:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" 
                               value="{{ request('end_date', now()->format('Y-m-d')) }}">
                    </div>
                    
                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-default ml-1">Reset</a>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction List</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.transactions.statistics') }}" class="btn btn-sm btn-info">
                        <i class="fas fa-chart-bar"></i> View Statistics
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td>{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                                    <td>{{ $transaction->account->name }}</td>                                    <td>
                                        @if($transaction->type == 'income')
                                            <span class="badge badge-success">Income</span>
                                        @elseif($transaction->type == 'expense')
                                            <span class="badge badge-danger">Expense</span>
                                        @elseif($transaction->isTransferOut())
                                            <span class="badge badge-warning">Transfer Out</span>
                                        @elseif($transaction->isTransferIn())
                                            <span class="badge badge-info">Transfer In</span>
                                        @else
                                            <span class="badge badge-secondary">Transfer</span>
                                        @endif
                                    </td>
                                    <td>{{ $transaction->category->name ?? 'N/A' }}</td>
                                    <td>{{ Str::limit($transaction->description, 30) }}</td>                                    <td class="{{ $transaction->type == 'income' ? 'text-success' : 
                                              ($transaction->type == 'expense' ? 'text-danger' : 
                                              ($transaction->isTransferOut() ? 'text-warning' : 
                                              ($transaction->isTransferIn() ? 'text-info' : ''))) }}">
                                        @if($transaction->isTransferOut())
                                            <i class="fas fa-arrow-right text-warning"></i> 
                                        @elseif($transaction->isTransferIn())
                                            <i class="fas fa-arrow-left text-info"></i> 
                                        @endif
                                        {{ $transaction->amount_text }}
                                        @if($transaction->isCurrencyTransfer())
                                            <span class="badge badge-secondary ml-1" title="Currency Exchange Transfer">
                                                <i class="fas fa-exchange-alt"></i> FX
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($transaction->is_reconciled)
                                            <span class="badge badge-success">Reconciled</span>
                                        @else
                                            <span class="badge badge-secondary">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('admin.transactions.show', $transaction) }}" class="btn btn-xs btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.transactions.edit', $transaction) }}" class="btn btn-xs btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-xs btn-danger" 
                                                    onclick="confirmDelete('{{ route('admin.transactions.destroy', $transaction) }}')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">No transactions found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $transactions->appends(request()->except('page'))->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this transaction? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Funds Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title" id="transferModalLabel">Transfer Funds</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="transferForm" action="{{ route('admin.transactions.transfer') }}" method="POST">
                @csrf
                <div class="modal-body">                    <div class="form-group">
                        <label for="from_account_id">From Account:</label>
                        <select name="from_account_id" id="from_account_id" class="form-control" required>
                            <option value="">Select Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" data-currency="{{ $account->currency->code }}" data-symbol="{{ $account->currency->symbol }}">
                                    {{ $account->name }} ({{ $account->currency->symbol }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_account_id">To Account:</label>
                        <select name="to_account_id" id="to_account_id" class="form-control" required>
                            <option value="">Select Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" data-currency="{{ $account->currency->code }}" data-symbol="{{ $account->currency->symbol }}">
                                    {{ $account->name }} ({{ $account->currency->symbol }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                      <div class="form-group">
                        <label for="amount">Amount to Transfer:</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="source-currency"></span>
                            </div>
                            <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    
                    <div id="currency-transfer-fields" class="d-none">
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="use_real_time_rate" name="use_real_time_rate" value="1">
                                <label class="custom-control-label" for="use_real_time_rate">Use real-time exchange rate</label>
                            </div>
                        </div>
                        
                        <div id="rate-info-group" class="form-group d-none">
                            <label>Current Exchange Rate:</label>
                            <div class="d-flex align-items-center">
                                <span id="exchange-rate-display" class="mr-2"></span>
                                <div id="rate-loading-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <button id="get-rate-button" class="btn btn-sm btn-outline-info ml-2">
                                    <i class="fas fa-sync-alt"></i> Update Rate
                                </button>
                            </div>
                            <small class="form-text text-muted">Rate provided by Fixer.io API</small>
                        </div>
                        
                        <div id="destination-amount-group" class="form-group">
                            <label for="destination_amount">Destination Amount:</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" id="dest-currency"></span>
                                </div>
                                <input type="number" name="destination_amount" id="destination_amount" class="form-control" step="0.01" min="0.01">
                            </div>
                            <small class="form-text text-muted">Amount in destination currency</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="transaction_date">Date:</label>
                        <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Transfer Funds</button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
@stop

@section('js')
    <script src="/js/exchange-rates.js"></script>
    <script>
        function confirmDelete(deleteUrl) {
            document.getElementById('deleteForm').action = deleteUrl;
            $('#deleteModal').modal('show');
        }
        
        $(function () {
            // Flash messages auto-hide
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
            
            // Update source and destination currency display when selections change
            $('#from_account_id, #to_account_id').change(function() {
                updateCurrencyDisplays();
            });
            
            // Initialize currency displays
            updateCurrencyDisplays();
        });
        
        function updateCurrencyDisplays() {
            const sourceSelect = document.getElementById('from_account_id');
            const destSelect = document.getElementById('to_account_id');
            
            // Update source currency display
            if (sourceSelect.selectedIndex > 0) {
                const sourceCurrency = sourceSelect.options[sourceSelect.selectedIndex].getAttribute('data-symbol');
                document.getElementById('source-currency').textContent = sourceCurrency || '';
            } else {
                document.getElementById('source-currency').textContent = '';
            }
            
            // Update destination currency display
            if (destSelect.selectedIndex > 0) {
                const destCurrency = destSelect.options[destSelect.selectedIndex].getAttribute('data-symbol');
                document.getElementById('dest-currency').textContent = destCurrency || '';
            } else {
                document.getElementById('dest-currency').textContent = '';
            }
        }
    </script>
@stop
