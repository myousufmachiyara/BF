@extends('layouts.app')
@section('title', 'Production Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab=='RMI'?'active':'' }}" data-bs-toggle="tab" href="#RMI">Production Order</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='PR'?'active':'' }}" data-bs-toggle="tab" href="#PR">Production Receiving</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='CR'?'active':'' }}" data-bs-toggle="tab" href="#CR">Product Costing</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Production Return <span class="badge badge-danger">New</span></a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- PRODUCTION ORDER / RAW ISSUED --}}
        <div id="RMI" class="tab-pane fade {{ $tab=='RMI'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="RMI">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($rawIssued as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->item_name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ number_format($row->rate,2) }}</td>
                            <td>{{ number_format($row->total,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No raw material issued found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PRODUCTION RECEIVING / FG RECEIVED --}}
        <div id="PR" class="tab-pane fade {{ $tab=='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="PR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Production</th><th>Item</th><th>Qty</th><th>M. Cost</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($produced as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->production }}</td>
                            <td>{{ $row->item_name }}</td>
                            <td>{{ $row->qty }}</td>
                            <td>{{ number_format($row->m_cost,2) }}</td>
                            <td>{{ number_format($row->total,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No production received found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PRODUCT COSTING --}}
        <div id="CR" class="tab-pane fade {{ $tab=='CR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.production') }}">
                <input type="hidden" name="tab" value="CR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label>From</label><input type="date" name="from_date" class="form-control" value="{{ $from }}"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="to_date" class="form-control" value="{{ $to }}"></div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary">Filter</button></div>
                </div>
            </form>
            <table class="table table-bordered table-striped">
                <thead><tr><th>Product</th><th>Total Qty</th><th>Average Cost</th><th>Total Cost</th></tr></thead>
                <tbody>
                    @forelse($costings as $row)
                        <tr>
                            <td>{{ $row->product_name }}</td>
                            <td>{{ $row->total_qty }}</td>
                            <td>{{ number_format($row->avg_cost,2) }}</td>
                            <td>{{ number_format($row->total_cost,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No costing data found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
</div>
@endsection
