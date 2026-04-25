@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ── DATE HEADING ──────────────────────────────────────────────── --}}
<div>
    <h2 class="text-dark"><strong id="currentDate"></strong></h2>
</div>

@if (!auth()->user()->hasRole('superadmin'))
<div class="alert alert-info">Welcome! Use the menu to navigate.</div>

@else

{{-- ── ROW 1: SALES OVERVIEW ───────────────────────────────────── --}}
<h6 class="card-title text-uppercase text-dark fw-bold mb-2" style="font-size:11px;letter-spacing:1px;">
    <i class="fas fa-chart-line me-1"></i> Sales Overview
</h6>
<div class="row mb-3">

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-success">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Today's Sales</strong></h3>
                <h2 class="amount m-0 text-success">
                    <strong>{{ number_format($salesToday, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-success text-uppercase" href="{{ route('reports.sale') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-primary">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>This Week's Sales</strong></h3>
                <h2 class="amount m-0 text-primary">
                    <strong>{{ number_format($salesWeek, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-primary text-uppercase" href="{{ route('reports.sale') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-info">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>This Month's Sales</strong></h3>
                <h2 class="amount m-0 text-info">
                    <strong>{{ number_format($salesMonth, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-info text-uppercase" href="{{ route('reports.sale') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-secondary">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Invoices Today</strong></h3>
                <h2 class="amount m-0 text-secondary">
                    <strong>{{ number_format($invoicesToday) }}</strong>
                    <span class="title text-end text-dark h6"> Nos</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-secondary text-uppercase" href="{{ route('sale_invoices.index') }}">View All</a>
                </div>
            </div>
        </section>
    </div>

</div>

{{-- ── ROW 2: PROFIT ────────────────────────────────────────────── --}}
<h6 class="card-title text-uppercase text-dark fw-bold mb-2" style="font-size:11px;letter-spacing:1px;">
    <i class="fas fa-coins me-1"></i> Profit (Revenue − COGS)
</h6>
<div class="row mb-3">

    <div class="col-12 col-md-4 mb-2">
        <section class="card card-featured-left {{ $profitToday >= 0 ? 'card-featured-success' : 'card-featured-danger' }}">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Today's Profit</strong></h3>
                <h2 class="amount m-0 {{ $profitToday >= 0 ? 'text-success' : 'text-danger' }}">
                    <strong>{{ number_format($profitToday, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="{{ $profitToday >= 0 ? 'text-success' : 'text-danger' }} text-uppercase" href="{{ route('reports.sale', ['tab'=>'PR']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-4 mb-2">
        <section class="card card-featured-left {{ $profitWeek >= 0 ? 'card-featured-success' : 'card-featured-danger' }}">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>This Week's Profit</strong></h3>
                <h2 class="amount m-0 {{ $profitWeek >= 0 ? 'text-success' : 'text-danger' }}">
                    <strong>{{ number_format($profitWeek, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="{{ $profitWeek >= 0 ? 'text-success' : 'text-danger' }} text-uppercase" href="{{ route('reports.sale', ['tab'=>'PR']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-4 mb-2">
        <section class="card card-featured-left {{ $profitMonth >= 0 ? 'card-featured-success' : 'card-featured-danger' }}">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>This Month's Profit</strong></h3>
                <h2 class="amount m-0 {{ $profitMonth >= 0 ? 'text-success' : 'text-danger' }}">
                    <strong>{{ number_format($profitMonth, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="{{ $profitMonth >= 0 ? 'text-success' : 'text-danger' }} text-uppercase" href="{{ route('reports.sale', ['tab'=>'PR']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

</div>

{{-- ── ROW 3: FINANCIAL POSITION ────────────────────────────────── --}}
<h6 class="card-title text-uppercase text-dark fw-bold mb-2" style="font-size:11px;letter-spacing:1px;">
    <i class="fas fa-balance-scale me-1"></i> Financial Position
</h6>
<div class="row mb-3">

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-danger">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Total Receivables</strong></h3>
                <h2 class="amount m-0 text-danger">
                    <strong>{{ number_format($totalReceivables, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-danger text-uppercase" href="{{ route('reports.accounts', ['tab'=>'receivables']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-warning">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Total Payables</strong></h3>
                <h2 class="amount m-0 text-warning">
                    <strong>{{ number_format($totalPayables, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-warning text-uppercase" href="{{ route('reports.accounts', ['tab'=>'payables']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-success">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Cash Balance</strong></h3>
                <h2 class="amount m-0 text-success">
                    <strong>{{ number_format($cashBalance, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-success text-uppercase" href="{{ route('reports.accounts', ['tab'=>'cash_book']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-primary">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Bank Balance</strong></h3>
                <h2 class="amount m-0 text-primary">
                    <strong>{{ number_format($bankBalance, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-primary text-uppercase" href="{{ route('reports.accounts', ['tab'=>'bank_book']) }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

</div>

{{-- ── ROW 4: PURCHASES & RETURNS ───────────────────────────────── --}}
<h6 class="card-title text-uppercase text-dark fw-bold mb-2" style="font-size:11px;letter-spacing:1px;">
    <i class="fas fa-shopping-cart me-1"></i> Purchases & Returns
</h6>
<div class="row mb-3">

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-warning">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Today's Purchases</strong></h3>
                <h2 class="amount m-0 text-warning">
                    <strong>{{ number_format($purchaseToday, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-warning text-uppercase" href="{{ route('reports.purchase') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-warning">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Month's Purchases</strong></h3>
                <h2 class="amount m-0 text-warning">
                    <strong>{{ number_format($purchaseMonth, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-warning text-uppercase" href="{{ route('reports.purchase') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-danger">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Sale Returns (Month)</strong></h3>
                <h2 class="amount m-0 text-danger">
                    <strong>{{ number_format($saleReturnsMonth, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-danger text-uppercase" href="{{ route('sale_return.index') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-secondary">
            <div class="card-body icon-container data-container">
                <h3 class="card-title amount text-dark"><strong>Purchase Returns (Mo)</strong></h3>
                <h2 class="amount m-0 text-secondary">
                    <strong>{{ number_format($purchaseReturnsMonth, 2) }}</strong>
                    <span class="title text-end text-dark h6"> PKR</span>
                </h2>
                <div class="summary-footer">
                    <a class="text-secondary text-uppercase" href="{{ route('purchase_return.index') }}">View Details</a>
                </div>
            </div>
        </section>
    </div>

</div>

{{-- ── ROW 5: CHART + TOP ITEMS + STOCK ALERT ──────────────────── --}}
<div class="row mb-3">

    {{-- 6-Month Sales Trend --}}
    <div class="col-12 col-lg-5 mb-3">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-chart-bar me-1 text-primary"></i> 6-Month Sales Trend</h2>
            </header>
            <div class="card-body">
                <canvas id="salesTrendChart" height="200"></canvas>
            </div>
        </section>
    </div>

    {{-- Top Selling Items --}}
    <div class="col-12 col-md-6 col-lg-4 mb-3">
        <section class="card">
            <header class="card-header">
                <h2 class="card-title"><i class="fas fa-fire me-1 text-danger"></i> Top Selling Items (Month)</h2>
            </header>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="font-size:11px;">#</th>
                            <th style="font-size:11px;">Product</th>
                            <th class="text-end" style="font-size:11px;">Qty</th>
                            <th class="text-end" style="font-size:11px;">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topSellingItems as $i => $item)
                        <tr>
                            <td>
                                @if($i === 0)<span class="badge bg-warning text-dark">🥇</span>
                                @elseif($i === 1)<span class="badge bg-secondary">🥈</span>
                                @elseif($i === 2)<span class="badge bg-danger">🥉</span>
                                @else<span class="badge bg-light text-dark">{{ $i+1 }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px;">{{ Str::limit($item->name, 20) }}</td>
                            <td class="text-end fw-bold" style="font-size:12px;">{{ number_format($item->total_qty) }}</td>
                            <td class="text-end text-success" style="font-size:12px;">{{ number_format($item->total_revenue) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No sales this month</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Stock Alert --}}
    <div class="col-12 col-md-6 col-lg-3 mb-3">
        <section class="card">
            <header class="card-header" style="border-left: 4px solid #dc3545;">
                <h2 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Stock Alert
                    <span class="badge bg-danger ms-1">{{ $negativeStockItems->count() }}</span>
                </h2>
            </header>
            <div class="card-body p-0" style="max-height:280px;overflow-y:auto;">
                @forelse($negativeStockItems as $item)
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span style="font-size:12px;">{{ Str::limit($item['name'], 22) }}</span>
                    <span class="badge {{ $item['stock'] < 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                        {{ number_format($item['stock']) }}
                    </span>
                </div>
                @empty
                <div class="text-center text-success py-4">
                    <i class="fas fa-check-circle fa-2x mb-2 d-block"></i>
                    <small>All items in stock!</small>
                </div>
                @endforelse
            </div>
        </section>
    </div>

</div>

{{-- ── ROW 6: RECENT INVOICES ───────────────────────────────────── --}}
<div class="row">
    <div class="col-12">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mb-0">
                    <i class="fas fa-receipt me-1 text-info"></i> Recent Invoices
                </h2>
                <a href="{{ route('sale_invoices.index') }}" class="btn btn-sm btn-primary">View All</a>
            </header>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover mb-0">
                        <thead class="thead-dark">
                            <tr>
                                <th style="font-size:11px;">Invoice #</th>
                                <th style="font-size:11px;">Date</th>
                                <th style="font-size:11px;">Customer</th>
                                <th style="font-size:11px;">Type</th>
                                <th class="text-end" style="font-size:11px;">Total</th>
                                <th class="text-end" style="font-size:11px;">Received</th>
                                <th class="text-end" style="font-size:11px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInvoices as $inv)
                            <tr>
                                <td style="font-size:12px;">
                                    <a href="{{ route('sale_invoices.print', $inv->id) }}" target="_blank">
                                        {{ $inv->invoice_no }}
                                    </a>
                                </td>
                                <td style="font-size:12px;">{{ \Carbon\Carbon::parse($inv->date)->format('d-m-Y') }}</td>
                                <td style="font-size:12px;">{{ $inv->account->name ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $inv->type === 'credit' ? 'bg-warning text-dark' : 'bg-success' }}" style="font-size:10px;">
                                        {{ ucfirst($inv->type) }}
                                    </span>
                                </td>
                                <td class="text-end" style="font-size:12px;">{{ number_format($inv->net_total, 2) }}</td>
                                <td class="text-end text-success" style="font-size:12px;">{{ number_format($inv->received, 2) }}</td>
                                <td class="text-end {{ $inv->balance > 0 ? 'text-danger fw-bold' : 'text-success' }}" style="font-size:12px;">
                                    {{ number_format($inv->balance, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No recent invoices found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

@endif {{-- end superadmin --}}

<script>
(function() {
    const now = new Date();
    const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    function suffix(d) {
        if (d >= 11 && d <= 13) return d + 'th';
        return d + (['th','st','nd','rd','th','th','th','th','th','th'][d % 10] ?? 'th');
    }
    document.getElementById('currentDate').textContent =
        days[now.getDay()] + ', ' + suffix(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
})();

@if(auth()->user()->hasRole('superadmin'))
$(document).ready(function () {
    const labels  = {!! json_encode($salesTrend->pluck('month')) !!};
    const amounts = {!! json_encode($salesTrend->pluck('amount')) !!};

    new Chart(document.getElementById('salesTrendChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Net Sales (PKR)',
                data: amounts,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => 'PKR ' + ctx.parsed.y.toLocaleString(undefined, { minimumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => 'PKR ' + (v / 1000).toFixed(0) + 'k',
                        font: { size: 10 }
                    }
                },
                x: { ticks: { font: { size: 10 } } }
            }
        }
    });
});
@endif
</script>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@endsection