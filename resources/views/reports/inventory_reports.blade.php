@extends('layouts.app')
@section('title', 'Inventory Reports')

@section('content')
<div class="tabs">

    {{-- NAV TABS --}}
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab=='IL'?'active':'' }}" data-bs-toggle="tab" href="#IL">
                Item Ledger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab=='SR'?'active':'' }}" data-bs-toggle="tab" href="#SR">
                Stock In Hand
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">

    {{-- ================= ITEM LEDGER TAB ================= --}}
    <div id="IL" class="tab-pane fade {{ $tab=='IL'?'show active':'' }}">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Qty In</th>
                    <th>Qty Out</th>
                    <th class="table-dark">Running Balance</th> 
                </tr>
            </thead>
            <tbody>
                {{-- OPENING BALANCE ROW --}}
                <tr class="table-warning">
                    <td colspan="3"><strong>Opening Balance (Before {{ $from }})</strong></td>
                    <td class="text-success">{{ number_format($openingQty, 2) }}</td>
                    <td class="text-danger">0.00</td>
                    <td><strong>{{ number_format($openingQty, 2) }}</strong></td>
                </tr>

                @php $runningBal = $openingQty; @endphp
                @forelse($itemLedger as $row)
                    @php $runningBal += ($row->qty_in - $row->qty_out); @endphp
                    <tr>
                        <td>{{ $row->date }}</td>
                        <td><span class="badge {{ $row->type == 'Purchase' ? 'bg-success' : 'bg-info' }}">{{ $row->type }}</span></td>
                        <td>{{ $row->description }}</td>
                        <td class="text-success">{{ $row->qty_in > 0 ? number_format($row->qty_in, 2) : '-' }}</td>
                        <td class="text-danger">{{ $row->qty_out > 0 ? number_format($row->qty_out, 2) : '-' }}</td>
                        <td class="table-secondary"><strong>{{ number_format($runningBal, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No transactions found for this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= STOCK IN HAND TAB ================= --}}
    <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">
        <form method="GET" class="mb-3">
            <input type="hidden" name="tab" value="SR">
            <div class="row">
                <div class="col-md-3">
                    <label>Product</label>
                    <select name="item_id" class="form-control select2-js">
                        <option value="">-- All Products --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- ADDED TO DATE FILTER HERE TO MATCH LEDGER LOGIC --}}
                <div class="col-md-3">
                    <label>As Of Date</label>
                    <input type="date" name="to_date" value="{{ $to }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label>Costing Method</label>
                    <select name="costing_method" class="form-control">
                        <option value="avg" {{ request('costing_method') == 'avg' ? 'selected' : '' }}>Average Cost</option>
                        <option value="latest" {{ request('costing_method') == 'latest' ? 'selected' : '' }}>Latest Purchase Price</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Refresh Stock</button>
                </div>
            </div>
        </form>
        </div>

    </div>
</div>
<script>
    $(document).ready(function () {
        $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    });
</script>
@endsection
