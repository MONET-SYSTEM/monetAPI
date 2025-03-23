@extends('adminlte::page')

@section('title', 'Create Account')

@section('content_header')
    <h1>Create New Account</h1>
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

            <form action="{{ route('admin.accounts.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="account_type">Account Type:</label>
                    <select name="account_type" id="account_type" class="form-control">
                        @foreach($accountTypes as $accountType)
                            <option value="{{ $accountType->uuid }}">{{ $accountType->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="currency">Currency:</label>
                    <select name="currency" id="currency" class="form-control">
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->uuid }}">{{ $currency->name }} ({{ $currency->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="name">Account Name:</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter account name">
                </div>

                <div class="form-group">
                    <label for="initial_balance">Initial Balance:</label>
                    <div class="input-group">
                        <!-- Prefix container; hidden by default -->
                        <div id="balance-prefix" class="input-group-prepend" style="display:none;">
                            <span class="input-group-text"></span>
                        </div>
                        <input type="number" name="initial_balance" id="initial_balance" class="form-control" value="{{ old('initial_balance') }}" placeholder="Enter initial balance" step="0.01">
                        <!-- Suffix container; hidden by default -->
                        <div id="balance-suffix" class="input-group-append" style="display:none;">
                            <span class="input-group-text"></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="colour_code">Colour Code:</label>
                    <input type="text" name="colour_code" class="form-control" value="{{ old('colour_code') }}" placeholder="Enter colour code">
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save
                </button>
                <a href="{{ route('admin.accounts.index') }}" class="btn btn-secondary">
                    <i class="fas fa-undo"></i> Cancel
                </a>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script>
        // Pass currencies data to JavaScript as a JSON object, keyed by uuid.
        var currencies = @json($currencies->keyBy('uuid'));
        var currencySelect = document.getElementById('currency');
        var prefixDiv = document.getElementById('balance-prefix');
        var prefixText = prefixDiv.querySelector('.input-group-text');
        var suffixDiv = document.getElementById('balance-suffix');
        var suffixText = suffixDiv.querySelector('.input-group-text');

        function updateCurrencySymbol() {
            var selected = currencySelect.value;
            if (currencies[selected]) {
                var symbol = currencies[selected].symbol;
                var symbolPosition = currencies[selected].symbol_position; // expected to be 'before' or 'after'
                
                // Hide both prefix and suffix containers initially
                prefixDiv.style.display = 'none';
                suffixDiv.style.display = 'none';

                if (symbolPosition === 'before') {
                    prefixText.textContent = symbol;
                    prefixDiv.style.display = 'block';
                } else {
                    suffixText.textContent = symbol;
                    suffixDiv.style.display = 'block';
                }
            } else {
                prefixDiv.style.display = 'none';
                suffixDiv.style.display = 'none';
            }
        }

        // Update symbol when the currency selection changes
        currencySelect.addEventListener('change', updateCurrencySymbol);
        // Initialize on page load
        updateCurrencySymbol();
    </script>
@endsection
