@extends('adminlte::page')

@section('title', 'Transaction Details')

@section('content_header')
    <h1>Transaction Details</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
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
                    
                    @if($transaction->isCurrencyTransfer())
                        <span class="badge badge-secondary ml-1" title="Currency Exchange Transfer">
                            <i class="fas fa-exchange-alt"></i> Currency Exchange
                        </span>
                    @endif
                    
                    Transaction #{{ $transaction->uuid }}
                </h3>
                <div class="card-tools">
                    <div class="btn-group">
                        <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-default">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <a href="{{ route('admin.transactions.edit', $transaction) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button type="button" class="btn btn-sm btn-danger" 
                                onclick="confirmDelete('{{ route('admin.transactions.destroy', $transaction) }}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 30%">Date</th>
                                <td>{{ $transaction->transaction_date->format('Y-m-d') }}</td>
                            </tr>
                            <tr>
                                <th>Account</th>
                                <td>{{ $transaction->account->name }} ({{ $transaction->account->currency->code }})</td>
                            </tr>                            <tr>
                                <th>Amount</th>
                                <td class="font-weight-bold {{ $transaction->type == 'income' ? 'text-success' : 
                                                             ($transaction->type == 'expense' ? 'text-danger' : 
                                                             ($transaction->isTransferOut() ? 'text-warning' : 
                                                             ($transaction->isTransferIn() ? 'text-info' : ''))) }}">
                                    @if($transaction->isTransferOut())
                                        <i class="fas fa-arrow-right text-warning"></i> 
                                    @elseif($transaction->isTransferIn())
                                        <i class="fas fa-arrow-left text-info"></i> 
                                    @endif
                                    {{ $transaction->amount_text }}
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if($transaction->is_reconciled)
                                        <span class="badge badge-success">Reconciled</span>
                                    @else
                                        <span class="badge badge-secondary">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 30%">Category</th>
                                <td>{{ $transaction->category->name ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Reference</th>
                                <td>{{ $transaction->reference ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Description</th>
                                <td>{{ $transaction->description ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Created</th>
                                <td>{{ $transaction->created_at->format('Y-m-d H:i:s') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                  <!-- Transfer Information Section -->
                @if($transaction->isTransfer())
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-exchange-alt"></i> Transfer Information
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        @php
                                            // Extract transfer reference ID
                                            $refParts = explode('-', $transaction->reference ?? '');
                                            $transferId = end($refParts);
                                            
                                            // Try to find the paired transaction
                                            $pairedRef = $transaction->isTransferOut() 
                                                ? 'TRANSFER-IN-' . $transferId 
                                                : 'TRANSFER-OUT-' . $transferId;
                                                
                                            $pairedTransaction = \App\Models\Transaction::where('reference', $pairedRef)->first();
                                        @endphp
                                        
                                        <p>
                                            <strong>Transfer Type:</strong> 
                                            @if($transaction->isCurrencyTransfer())
                                                <span class="badge badge-secondary">Currency Exchange</span>
                                            @else
                                                <span class="badge badge-info">Same Currency</span>
                                            @endif
                                        </p>
                                        
                                        <p>
                                            <strong>Direction:</strong> 
                                            @if($transaction->isTransferOut())
                                                <span class="text-warning">
                                                    <i class="fas fa-arrow-right"></i> Sent from this account
                                                </span>
                                            @else
                                                <span class="text-info">
                                                    <i class="fas fa-arrow-left"></i> Received in this account
                                                </span>
                                            @endif
                                        </p>
                                        
                                        @if($pairedTransaction)
                                        <p>
                                            <strong>
                                                {{ $transaction->isTransferOut() ? 'Destination' : 'Source' }} Account:
                                            </strong> 
                                            {{ $pairedTransaction->account->name }} ({{ $pairedTransaction->account->currency->code }})
                                        </p>
                                          @if($transaction->isCurrencyTransfer())
                                        <p>
                                            <strong>Exchange Rate:</strong> 
                                            1 {{ $transaction->account->currency->code }} = 
                                            {{ number_format($pairedTransaction->amount / $transaction->amount, 4) }} 
                                            {{ $pairedTransaction->account->currency->code }}
                                            
                                            @if(strpos($transaction->description, 'Real-time rate') !== false)
                                                <span class="badge badge-info ml-1">Real-time rate</span>
                                            @endif
                                        </p>
                                        @endif
                                        
                                        <p>
                                            <a href="{{ route('admin.transactions.show', $pairedTransaction) }}" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i> View {{ $transaction->isTransferOut() ? 'Destination' : 'Source' }} Transaction
                                            </a>
                                        </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Attachments -->
                @if($transaction->attachments->count() > 0)
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h5>Attachments</h5>
                        <div class="row">
                            @foreach($transaction->attachments as $attachment)
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    @if(in_array(pathinfo($attachment->file_path, PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'gif']))
                                        <img src="{{ asset('storage/' . $attachment->file_path) }}" class="card-img-top" alt="Attachment">
                                    @else
                                        <div class="card-img-top bg-light text-center py-5">
                                            <i class="fas fa-file fa-3x text-secondary"></i>
                                        </div>
                                    @endif
                                    <div class="card-body p-2">
                                        <p class="card-text text-truncate">{{ $attachment->original_name }}</p>
                                        <a href="{{ asset('storage/' . $attachment->file_path) }}" class="btn btn-sm btn-info btn-block" target="_blank">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>
            <div class="card-footer text-center">
                <a href="{{ route('admin.transactions.edit', $transaction) }}" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Transaction
                </a>
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
@stop

@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
@stop

@section('js')
    <script>
        function confirmDelete(deleteUrl) {
            document.getElementById('deleteForm').action = deleteUrl;
            $('#deleteModal').modal('show');
        }
    </script>
@stop
