@extends('adminlte::page')

@section('title', 'Transaction Statistics')

@section('content_header')
    <h1>Transaction Statistics</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Filter Options</h3>
                <div class="card-tools">
                    <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-default">
                        <i class="fas fa-arrow-left"></i> Back to Transactions
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.transactions.statistics') }}" method="GET" class="form-inline">
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
                        <label for="start_date" class="mr-1">From:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="end_date" class="mr-1">To:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>
                    
                    <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Financial Summary Cards -->
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ $stats['income_formatted'] }}</h3>
                <p>Total Income (Period)</p>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{ $stats['expense_formatted'] }}</h3>
                <p>Total Expenses (Period)</p>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-arrow-up"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ $stats['balance_formatted'] }}</h3>
                <p>Net Balance (Period)</p>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-calculator"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ $stats['transaction_count'] }}</h3>
                <p>Total Transactions</p>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-exchange-alt"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Account Balance Cards -->
    <div class="col-lg-4 col-md-6">
        <div class="small-box bg-secondary">
            <div class="inner">
                <h6>{{ request('account_id') ? $accounts->where('id', request('account_id'))->first()->name : 'All Accounts' }}</h6>
                <h3>{{ $stats['initial_balance_formatted'] }}</h3>
                <p>Initial Balance</p>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h6>{{ request('account_id') ? $accounts->where('id', request('account_id'))->first()->name : 'All Accounts' }}</h6>
                <h3>{{ $stats['current_balance_formatted'] }}</h3>
                <p>Current Balance</p>
                <small class="text-white">Initial Balance + All Income - All Expenses</small>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-university"></i>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6">
        <div class="small-box" style="background-color: {{ $stats['current_balance'] >= $stats['initial_balance'] ? '#28a745' : '#dc3545' }}; color: white;">
            <div class="inner">
                <h6>{{ request('account_id') ? $accounts->where('id', request('account_id'))->first()->name : 'All Accounts' }}</h6>
                <h3>{{ number_format($stats['current_balance'] - $stats['initial_balance'], 2) }}</h3>
                <p>Balance Change Since Start</p>
                <small class="text-white">
                    {{ $stats['current_balance'] >= $stats['initial_balance'] ? 'Positive growth' : 'Negative growth' }}
                    ({{ number_format(abs($stats['current_balance'] - $stats['initial_balance']) / max(0.01, abs($stats['initial_balance'])) * 100, 1) }}%)
                </small>
            </div>
            <div class="icon" style="font-size: 70px; top: 15px; right: 15px;">
                <i class="fas fa-{{ $stats['current_balance'] >= $stats['initial_balance'] ? 'arrow-up' : 'arrow-down' }}"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Income vs Expense Chart -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Income vs Expense</h3>
            </div>
            <div class="card-body">
                <canvas id="incomeExpenseChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Expense Category Breakdown -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Expense Categories</h3>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Income Category Breakdown -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Income Categories</h3>
            </div>
            <div class="card-body">
                <canvas id="incomeCategoryChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Monthly Trend -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Monthly Trend</h3>
            </div>
            <div class="card-body">
                <div style="height: 400px;">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Category Details Table -->
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Category Details</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $incomeTotal = $categoryBreakdown->where('type', 'income')->sum('total');
                            $expenseTotal = $categoryBreakdown->where('type', 'expense')->sum('total');
                        @endphp
                        
                        @foreach($categoryBreakdown as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>
                                    @if($category->type == 'income')
                                        <span class="badge badge-success">Income</span>
                                    @else
                                        <span class="badge badge-danger">Expense</span>
                                    @endif
                                </td>
                                <td>{{ number_format($category->total, 2) }}</td>
                                <td>
                                    @php
                                        $percentage = $category->type == 'income' 
                                            ? ($incomeTotal > 0 ? round(($category->total / $incomeTotal) * 100, 2) : 0)
                                            : ($expenseTotal > 0 ? round(($category->total / $expenseTotal) * 100, 2) : 0);
                                    @endphp
                                    <div class="progress">
                                        <div class="progress-bar bg-{{ $category->type == 'income' ? 'success' : 'danger' }}" 
                                             role="progressbar" 
                                             style="width: {{ $percentage }}%" 
                                             aria-valuenow="{{ $percentage }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {{ $percentage }}%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="/css/admin_custom.css">
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    $(function () {
        // Income vs Expense Chart
        new Chart(document.getElementById('incomeExpenseChart'), {
            type: 'pie',
            data: {
                labels: ['Income', 'Expense'],                datasets: [{
                    data: [{{ $stats['total_income'] }}, {{ $stats['total_expense'] }}],
                    backgroundColor: ['rgba(40, 167, 69, 0.8)', 'rgba(220, 53, 69, 0.8)']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
          // Category Breakdown Chart - Expense
        const expenseCategories = {!! json_encode($categoryBreakdown->where('type', 'expense')->pluck('name')) !!};
        const expenseValues = {!! json_encode($categoryBreakdown->where('type', 'expense')->pluck('total')) !!};
        
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: expenseCategories,
                datasets: [{
                    data: expenseValues,
                    backgroundColor: [
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(23, 162, 184, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(255, 102, 0, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(0, 123, 255, 0.8)',
                        'rgba(108, 117, 125, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Expense Categories'
                    }
                }
            }
        });
        
        // Category Breakdown Chart - Income
        const incomeCategories = {!! json_encode($categoryBreakdown->where('type', 'income')->pluck('name')) !!};
        const incomeValues = {!! json_encode($categoryBreakdown->where('type', 'income')->pluck('total')) !!};
        
        new Chart(document.getElementById('incomeCategoryChart'), {
            type: 'doughnut',
            data: {
                labels: incomeCategories,
                datasets: [{
                    data: incomeValues,
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(0, 123, 255, 0.8)',
                        'rgba(23, 162, 184, 0.8)',
                        'rgba(111, 66, 193, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(255, 102, 0, 0.8)',
                        'rgba(108, 117, 125, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    title: {
                        display: true,
                        text: 'Income Categories'
                    }
                }
            }
        });        // Monthly Trend Chart
        const monthlyTrendData = {!! json_encode($monthlyTrend) !!};
        // Get unique months and ensure they are in chronological order
        const months = [...new Set(monthlyTrendData.map(item => item.month))].sort();
        const incomeData = [];
        const expenseData = [];
        const formattedMonths = [];
        
        // Format months for better display (e.g., "2023-05" to "May 2023")
        months.forEach(month => {
            const [year, monthNum] = month.split('-');
            const date = new Date(year, parseInt(monthNum) - 1, 1);
            const formatter = new Intl.DateTimeFormat('en', { month: 'short', year: 'numeric' });
            formattedMonths.push(formatter.format(date));
            
            const incomeItem = monthlyTrendData.find(item => item.month === month && item.type === 'income');
            const expenseItem = monthlyTrendData.find(item => item.month === month && item.type === 'expense');
            
            incomeData.push(incomeItem ? parseFloat(incomeItem.total) : 0);
            expenseData.push(expenseItem ? parseFloat(expenseItem.total) : 0);
        });
          new Chart(document.getElementById('monthlyTrendChart'), {
            type: 'line',
            data: {
                labels: formattedMonths,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        borderColor: 'rgba(40, 167, 69, 0.8)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Expense',
                        data: expenseData,
                        borderColor: 'rgba(220, 53, 69, 0.8)',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-US', {
                                        style: 'currency',
                                        currency: 'USD'
                                    }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'Monthly Income & Expenses (Last 12 Months)'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@stop
