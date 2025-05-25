@extends('adminlte::page')

@section('title', 'Create Transaction')

@section('content_header')
    <h1>Create New Transaction</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transaction Details</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-default">
                        <i class="fas fa-arrow-left"></i> Back to Transactions
                    </a>
                </div>
            </div>
            <form action="{{ route('admin.transactions.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Account -->
                            <div class="form-group">
                                <label for="account_id">Account <span class="text-danger">*</span></label>
                                <select name="account_id" id="account_id" class="form-control @error('account_id') is-invalid @enderror" required>
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }} ({{ $account->currency->code }} - Balance: {{ $account->current_balance_text }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Transaction Type -->
                            <div class="form-group">
                                <label for="type">Transaction Type <span class="text-danger">*</span></label>
                                <select name="type" id="type" class="form-control @error('type') is-invalid @enderror" required>
                                    <option value="income" {{ old('type') == 'income' ? 'selected' : '' }}>Income</option>
                                    <option value="expense" {{ old('type') == 'expense' ? 'selected' : '' }}>Expense</option>
                                    <option value="transfer" {{ old('type') == 'transfer' ? 'selected' : '' }}>Transfer</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Amount -->
                            <div class="form-group">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="currency-symbol">$</span>
                                    </div>
                                    <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror" 
                                           value="{{ old('amount') }}" step="0.01" min="0.01" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <!-- Transaction Date -->
                            <div class="form-group">
                                <label for="transaction_date">Transaction Date <span class="text-danger">*</span></label>
                                <input type="date" name="transaction_date" id="transaction_date" class="form-control @error('transaction_date') is-invalid @enderror" 
                                       value="{{ old('transaction_date', now()->format('Y-m-d')) }}" required>
                                @error('transaction_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Category -->
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select name="category_id" id="category_id" class="form-control @error('category_id') is-invalid @enderror">
                                    <option value="">Select Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror" 
                                          rows="3">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Reference -->
                            <div class="form-group">
                                <label for="reference">Reference</label>
                                <input type="text" name="reference" id="reference" class="form-control @error('reference') is-invalid @enderror" 
                                       value="{{ old('reference') }}" placeholder="Receipt #, Invoice #, etc.">
                                @error('reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <!-- Is Reconciled -->
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_reconciled" name="is_reconciled" value="1" 
                                          {{ old('is_reconciled') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="is_reconciled">Mark as reconciled</label>
                                </div>
                                <small class="form-text text-muted">
                                    Check this if the transaction has been verified with your bank statement
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attachments -->
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="attachments">Attachments</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="attachments" name="attachments[]" multiple>
                                    <label class="custom-file-label" for="attachments">Choose files</label>
                                </div>
                                <small class="form-text text-muted">
                                    You can upload receipts, invoices, or other documents related to this transaction
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-default float-right">Cancel</a>
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
    <script>
        $(function () {
            // Update currency symbol based on selected account
            $('#account_id').change(function() {
                var accountId = $(this).val();
                if (accountId) {
                    $.ajax({
                        url: '/admin/accounts/' + accountId + '/get-currency',
                        type: 'GET',
                        dataType: 'json',
                        success: function(data) {
                            $('#currency-symbol').text(data.symbol);
                        }
                    });
                } else {
                    $('#currency-symbol').text('$');
                }
            });
            
            // Trigger change on page load if account is selected
            if ($('#account_id').val()) {
                $('#account_id').trigger('change');
            }
            
            // Show selected file names in file input
            $(document).on('change', '.custom-file-input', function() {
                var fileNames = Array.from(this.files).map(f => f.name).join(', ');
                $(this).next('.custom-file-label').html(fileNames || 'Choose files');
            });
        });
    </script>
@stop
