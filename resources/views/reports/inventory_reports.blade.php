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

        {{-- ================= ITEM LEDGER ================= --}}
        <div id="IL" class="tab-pane fade {{ $tab=='IL'?'show active':'' }}">

            <form method="GET" class="mb-3">
                <input type="hidden" name="tab" value="IL">

                <div class="row">
                    <div class="col-md-3">
                        <label>Product</label>
                        <select name="item_id" class="form-control select2-js" required>
                            <option value="">-- Select Product --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="{{ $from }}" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="{{ $to }}" class="form-control">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $totalIn  = $itemLedger->sum('qty_in');
                $totalOut = $itemLedger->sum('qty_out');
                $closing  = $openingQty + $totalIn - $totalOut;
            @endphp

            <div class="mb-3 text-end">
                <h5>Closing Balance: <span class="text-danger">{{ $closing }}</span></h5>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Balance</th> </tr>
                </thead>
                <tbody>
                    <tr class="table-warning">
                        <td colspan="3"><strong>Opening Balance</strong></td>
                        <td class="text-success">{{ $openingQty }}</td>
                        <td class="text-danger">0</td>
                        <td><strong>{{ $openingQty }}</strong></td>
                    </tr>

                    @php $runningBal = $openingQty; @endphp
                    @forelse($itemLedger as $row)
                        @php $runningBal += ($row->qty_in - $row->qty_out); @endphp
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->type }}</td>
                            <td>{{ $row->description }}</td>
                            <td class="text-success">{{ $row->qty_in }}</td>
                            <td class="text-danger">{{ $row->qty_out }}</td>
                            <td><strong>{{ $runningBal }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">No records found</td></tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Totals</th>
                        <th>{{ $totalIn }}</th>
                        <th>{{ $totalOut }}</th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Closing Balance</th>
                        <th colspan="2">{{ $closing }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- ================= STOCK IN HAND ================= --}}
        <div id="SR" class="tab-pane fade {{ $tab=='SR'?'show active':'' }}">

            <form method="GET" class="mb-3">
                <input type="hidden" name="tab" value="SR">

                <div class="row">
                    <div class="col-md-3">
                        <label>Product</label>
                        <select name="item_id" class="form-control select2-js">
                            <option value="">-- All Products --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                    {{ request('item_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Costing Method</label>
                        <select name="costing_method" class="form-control">
                            <option value="avg">Average</option>
                            <option value="max">Max</option>
                            <option value="min">Min</option>
                            <option value="latest">Latest</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $grandQty   = $stockInHand->sum('quantity');
                $grandTotal = $stockInHand->sum('total');
            @endphp

            @if(auth()->user()->hasRole('superadmin'))
            <div class="mb-3 text-end">
                <h4>Total Stock Value: {{ number_format($grandTotal,2) }}</h4>
            </div>
            @endif
            
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        @if(auth()->user()->hasRole('superadmin'))
                            <th>Rate</th>
                            <th>Total</th>
                        @endif                        
                    </tr>
                </thead>

                <tbody>
                    @forelse($stockInHand as $row)
                        <tr>
                            <td>{{ $row['product'] }}</td>
                            <td>{{ $row['quantity'] }}</td>
                            @if(auth()->user()->hasRole('superadmin'))
                                <td>{{ number_format($row['price'],2) }}</td>
                                <td>{{ number_format($row['total'],2) }}</td>
                            @endif
                        </tr>
                    @empty
                        @if(auth()->user()->hasRole('superadmin'))
                        <tr>
                            <td colspan="4" class="text-center">No stock found</td>
                        </tr>
                        @else
                        <tr>
                            <td colspan="2" class="text-center">No stock found</td>
                        </tr>
                        @endif
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <th class="text-end">Grand Total</th>
                        <th>{{ $grandQty }}</th>
                        
                        @if(auth()->user()->hasRole('superadmin'))
                            {{-- Shows the dash under the Rate column --}}
                            <th>-</th>
                            {{-- Shows the actual Grand Total amount --}}
                            <th>{{ number_format($grandTotal, 2) }}</th>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
</div>
<script>
    $(document).ready(function () {
        $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    });
</script>
@endsection
