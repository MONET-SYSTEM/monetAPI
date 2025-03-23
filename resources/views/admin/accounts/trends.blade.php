@extends('adminlte::page')

@section('title', 'Dashboard Trends')

@section('content_header')
    <h1>Dashboard Trends</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <!-- Card for Account Types Usage -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Account Types Usage</h3>
            </div>
            <div class="card-body">
                <canvas id="accountTypesChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <!-- Card for Currency Usage Trends -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Currency Usage Trends</h3>
            </div>
            <div class="card-body">
                <canvas id="currencyChart"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<!-- Load Chart.js from a CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Pass the PHP data to JavaScript
    const accountTypesData = @json($accountTypesStats);
    const currencyData = @json($currencyStats);

    // -----------------------------
    // Account Types Bar Chart
    // -----------------------------
    const accountTypesLabels = accountTypesData.map(item => item.name);
    const accountTypesCounts = accountTypesData.map(item => item.count);

    const accountTypesCtx = document.getElementById('accountTypesChart').getContext('2d');
    new Chart(accountTypesCtx, {
        type: 'bar',
        data: {
            labels: accountTypesLabels,
            datasets: [{
                label: 'Accounts',
                data: accountTypesCounts,
                backgroundColor: 'rgba(54, 162, 235, 1)' // Changed from 0.7 to 1 for a solid color
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1 // Force integer steps
                    }
                }
            }
        }
    });

    // -----------------------------
    // Currency Pie Chart
    // -----------------------------
    const currencyLabels = currencyData.map(item => `${item.name} (${item.code})`);
    const currencyCounts = currencyData.map(item => item.count);

    // A simpler color palette with fewer repeated colors
    const pieColors = [
        'rgba(54, 162, 235, 0.7)',
        'rgba(255, 99, 132, 0.7)',
        'rgba(255, 206, 86, 0.7)',
        'rgba(75, 192, 192, 0.7)',
        'rgba(153, 102, 255, 0.7)',
        'rgba(255, 159, 64, 0.7)'
    ];

    // If there are more slices than colors, we repeat the array
    const backgroundColors = currencyLabels.map((_, i) => pieColors[i % pieColors.length]);

    const currencyCtx = document.getElementById('currencyChart').getContext('2d');
    new Chart(currencyCtx, {
        type: 'pie',
        data: {
            labels: currencyLabels,
            datasets: [{
                data: currencyCounts,
                backgroundColor: backgroundColors
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                // Customize tooltip to show percentages
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            // The label (e.g., "US Dollar (USD)")
                            const label = context.label || '';
                            // The raw value for this slice
                            const value = context.parsed;
                            // Sum all values in this dataset
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            // Calculate percentage
                            const percentage = ((value / total) * 100).toFixed(2);

                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
</script>
@endsection
