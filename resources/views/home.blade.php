@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

{{-- ── DATE HEADING ──────────────────────────────────────────────── --}}
<div class="mb-3">
    <h4 class="text-dark fw-normal" id="currentDate"></h4>
</div>

@if (!auth()->user()->hasRole('superadmin'))
{{-- ════════════════════════ NON-ADMIN VIEW ═══════════════════════ --}}
<div class="alert alert-info">Welcome! Use the menu to navigate.</div>

@else
{{-- ════════════════════════ SUPERADMIN DASHBOARD ═════════════════ --}}

{{-- ── ROW 1: SALES ─────────────────────────────────────────────── --}}
<div class="row g-2 mb-2">
    <div class="col-12">
        <h6 class="text-uppercase text-dark fw-bold mb-1" style="font-size:11px;letter-spacing:1px;">
            <i class="fas fa-chart-line me-1"></i> Sales Overview
        </h6>
    </div>
    @php
        $salesCards = [
            ['label'=>"Today's Sales",   'value'=>$salesToday,  'icon'=>'fa-calendar-day',   'color'=>'success'],
            ['label'=>"This Week",       'value'=>$salesWeek,   'icon'=>'fa-calendar-week',  'color'=>'primary'],
            ['label'=>"This Month",      'value'=>$salesMonth,  'icon'=>'fa-calendar-alt',   'color'=>'info'],
            ['label'=>"Today's Invoices",'value'=>$invoicesToday,'icon'=>'fa-file-invoice',  'color'=>'secondary', 'raw'=>true],
            ['label'=>"Month Invoices",  'value'=>$invoicesMonth,'icon'=>'fa-file-alt',      'color'=>'dark',     'raw'=>true],
        ];
    @endphp
    @foreach($salesCards as $c)
    <div class="col-12 col-md-2 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-dark mb-1" style="font-size:11px;">{{ $c['label'] }}</p>
                        <h5 class="mb-0 fw-bold text-{{ $c['color'] }}">
                            @if(empty($c['raw']))PKR @endif
                            {{ empty($c['raw']) ? number_format($c['value'],2) : number_format($c['value']) }}
                        </h5>
                    </div>
                    <span class="text-{{ $c['color'] }} opacity-50" style="font-size:22px;">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── ROW 2: PROFIT ────────────────────────────────────────────── --}}
<div class="row g-2 mb-2">
    <div class="col-12">
        <h6 class="text-uppercase text-dark fw-bold mb-1" style="font-size:11px;letter-spacing:1px;">
            <i class="fas fa-coins me-1"></i> Profit (Revenue − COGS)
        </h6>
    </div>
    @php
        $profitCards = [
            ['label'=>"Today's Profit",  'value'=>$profitToday,  'icon'=>'fa-sun'],
            ['label'=>"This Week",       'value'=>$profitWeek,   'icon'=>'fa-calendar-week'],
            ['label'=>"This Month",      'value'=>$profitMonth,  'icon'=>'fa-trophy'],
        ];
    @endphp
    @foreach($profitCards as $c)
    <div class="col-12 col-md-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-dark mb-1" style="font-size:11px;">{{ $c['label'] }}</p>
                        <h5 class="mb-0 fw-bold {{ $c['value'] >= 0 ? 'text-success' : 'text-danger' }}">
                            PKR {{ number_format($c['value'], 2) }}
                        </h5>
                    </div>
                    <span class="{{ $c['value'] >= 0 ? 'text-success' : 'text-danger' }} opacity-50" style="font-size:22px;">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── ROW 3: FINANCIAL POSITION ────────────────────────────────── --}}
<div class="row g-2 mb-2">
    <div class="col-12">
        <h6 class="text-uppercase text-dark fw-bold mb-1" style="font-size:11px;letter-spacing:1px;">
            <i class="fas fa-balance-scale me-1"></i> Financial Position
        </h6>
    </div>
    @php
        $finCards = [
            ['label'=>'Total Receivables', 'value'=>$totalReceivables,     'color'=>'danger',  'icon'=>'fa-arrow-circle-down'],
            ['label'=>'Total Payables',    'value'=>$totalPayables,        'color'=>'warning', 'icon'=>'fa-arrow-circle-up'],
            ['label'=>'Cash Balance',      'value'=>$cashBalance,          'color'=>'success', 'icon'=>'fa-money-bill-wave'],
            ['label'=>'Bank Balance',      'value'=>$bankBalance,          'color'=>'primary', 'icon'=>'fa-university'],
            ['label'=>'Sale Returns (Mo)', 'value'=>$saleReturnsMonth,     'color'=>'danger',  'icon'=>'fa-undo'],
            ['label'=>'Pur Returns (Mo)',  'value'=>$purchaseReturnsMonth, 'color'=>'secondary','icon'=>'fa-reply'],
        ];
    @endphp
    @foreach($finCards as $c)
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-dark mb-1" style="font-size:11px;">{{ $c['label'] }}</p>
                        <h6 class="mb-0 fw-bold text-{{ $c['color'] }}">
                            PKR {{ number_format($c['value'], 2) }}
                        </h6>
                    </div>
                    <span class="text-{{ $c['color'] }} opacity-50" style="font-size:20px;">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── ROW 4: PURCHASES ─────────────────────────────────────────── --}}
<div class="row g-2 mb-3">
    <div class="col-12">
        <h6 class="text-uppercase text-dark fw-bold mb-1" style="font-size:11px;letter-spacing:1px;">
            <i class="fas fa-shopping-cart me-1"></i> Purchases
        </h6>
    </div>
    @php
        $purCards = [
            ['label'=>"Today's Purchases", 'value'=>$purchaseToday],
            ['label'=>"This Week",         'value'=>$purchaseWeek],
            ['label'=>"This Month",        'value'=>$purchaseMonth],
        ];
    @endphp
    @foreach($purCards as $c)
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-dark mb-1" style="font-size:11px;">{{ $c['label'] }}</p>
                    <h6 class="mb-0 fw-bold text-warning">PKR {{ number_format($c['value'],2) }}</h6>
                </div>
                <i class="fas fa-truck text-warning opacity-50" style="font-size:22px;"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── ROW 5: CHARTS + TOP ITEMS + NEGATIVE STOCK ──────────────── --}}
<div class="row g-3 mb-3">

    {{-- Sales Trend Chart --}}
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-bar me-1 text-primary"></i> 6-Month Sales Trend</h6>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" height="200"></canvas>
            </div>
        </div>
    </div>

    {{-- Top Selling Items --}}
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-fire me-1 text-danger"></i> Top Selling Items (Month)</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
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
                                @elseif($i === 2)<span class="badge bg-danger" style="background:#cd7f32!important">🥉</span>
                                @else <span class="badge bg-light text-dark">{{ $i+1 }}</span>
                                @endif
                            </td>
                            <td style="font-size:12px;">{{ Str::limit($item->name, 20) }}</td>
                            <td class="text-end fw-bold" style="font-size:12px;">{{ number_format($item->total_qty) }}</td>
                            <td class="text-end text-success" style="font-size:12px;">{{ number_format($item->total_revenue) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-dark py-3">No sales this month</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Negative / Zero Stock --}}
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100 border-danger" style="border-left:4px solid #dc3545!important;">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="fw-bold mb-0 text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Stock Alert
                    <span class="badge bg-danger ms-1">{{ $negativeStockItems->count() }}</span>
                </h6>
            </div>
            <div class="card-body p-0" style="max-height:260px;overflow-y:auto;">
                @forelse($negativeStockItems as $item)
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span style="font-size:12px;">{{ Str::limit($item['name'], 22) }}</span>
                    <span class="badge {{ $item['stock'] < 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                        {{ number_format($item['stock']) }}
                    </span>
                </div>
                @empty
                <div class="text-center text-success py-4">
                    <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                    <small>All items in stock!</small>
                </div>
                @endforelse
            </div>
        </div>
    </div>

</div>

{{-- ── ROW 6: RECENT INVOICES ───────────────────────────────────── --}}
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-receipt me-1 text-info"></i> Recent Invoices</h6>
                <a href="{{ route('sale_invoices.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
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
                            <tr><td colspan="7" class="text-center text-dark py-3">No recent invoices</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endif {{-- end superadmin --}}

<script>
// ── Date heading ─────────────────────────────────────────────────────────
(function() {
    const now = new Date();
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    function suffix(d) {
        if (d >= 11 && d <= 13) return d + 'th';
        return d + (['th','st','nd','rd','th','th','th','th','th','th'][d % 10] ?? 'th');
    }
    document.getElementById('currentDate').textContent =
        days[now.getDay()] + ', ' + suffix(now.getDate()) + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
})();

@if(auth()->user()->hasRole('superadmin'))
// ── Sales Trend Bar Chart ─────────────────────────────────────────────────
$(document).ready(function () {
    const labels  = {!! json_encode($salesTrend->pluck('month')) !!};
    const amounts = {!! json_encode($salesTrend->pluck('amount')) !!};

    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(ctx, {
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
                        label: ctx => 'PKR ' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => 'PKR ' + (v/1000).toFixed(0) + 'k',
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

{{-- Chart.js CDN (only if not already in layout) --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@endsection