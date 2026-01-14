@extends('layouts.app')
@section('title', 'Purchase Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link {{ $tab=='PUR'?'active':'' }}" data-bs-toggle="tab" href="#PUR">Purchase Register</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='PR'?'active':'' }}" data-bs-toggle="tab" href="#PR">Purchase Returns</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='VWP'?'active':'' }}" data-bs-toggle="tab" href="#VWP">Vendor-wise Purchases</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab=='PB'?'active':'' }}" data-bs-toggle="tab" href="#PB">Purchase Bilty</a></li>
    </ul>

    <div class="tab-content mt-3">
        {{-- PURCHASE REGISTER --}}
        <div id="PUR" class="tab-pane fade {{ $tab=='PUR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="PUR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $grandTotal = $purchaseRegister->sum('total');
                $grandQty   = $purchaseRegister->sum('quantity');
            @endphp

            <div class="mb-3 text-end">
                <h5>Total Qty: <span class="text-primary">{{ $grandQty }}</span></h5>
                <h3>Total Purchase: <span class="text-danger">{{ number_format($grandTotal, 2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th><th>Invoice No</th><th>Vendor</th><th>Item</th>
                        <th>Qty</th><th>Rate</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseRegister as $pur)
                        <tr>
                            <td>{{ $pur->date }}</td>
                            <td>
                                <a href="{{ route('purchase_invoices.print', $pur->invoice_id) }}" target="_blank" class="text-primary fw-bold text-decoration-underline">
                                    {{ $pur->invoice_no }}
                                </a>
                            </td>
                            <td>{{ $pur->vendor_name }}</td>
                            <td>{{ $pur->item_name }}</td>
                            <td>{{ $pur->quantity }}</td>
                            <td>{{ number_format($pur->rate, 2) }}</td>
                            <td>{{ number_format($pur->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No purchase records found.</td></tr>
                    @endforelse
                </tbody>
                @if(count($purchaseRegister))
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Grand Total</th>
                        <th>{{ $grandQty }}</th>
                        <th>-</th>
                        <th>{{ number_format($grandTotal, 2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- PURCHASE RETURNS --}}
        <div id="PR" class="tab-pane fade {{ $tab=='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="PR">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $returnTotal = $purchaseReturns->sum('total');
                $returnQty   = $purchaseReturns->sum('quantity');
            @endphp

            <div class="mb-3 text-end">
                <h5>Total Qty Returned: <span class="text-warning">{{ $returnQty }}</span></h5>
                <h3>Total Returns: <span class="text-danger">{{ number_format($returnTotal, 2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th><th>Return No</th><th>Vendor</th><th>Item</th>
                        <th>Qty</th><th>Rate</th><th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseReturns as $pr)
                        <tr>
                            <td>{{ $pr->date }}</td>
                            <td>
                                <a href="{{ route('purchase_return.print', $pr->return_id) }}" target="_blank" class="text-danger fw-bold text-decoration-underline">
                                    {{ $pr->return_no }}
                                </a>
                            </td>
                            <td>{{ $pr->vendor_name }}</td>
                            <td>{{ $pr->item_name }}</td>
                            <td>{{ $pr->quantity }}</td>
                            <td>{{ number_format($pr->rate, 2) }}</td>
                            <td>{{ number_format($pr->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No purchase return records found.</td></tr>
                    @endforelse
                </tbody>
                @if(count($purchaseReturns))
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Grand Total</th>
                        <th>{{ $returnQty }}</th>
                        <th>-</th>
                        <th>{{ number_format($returnTotal, 2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- VENDOR-WISE PURCHASE --}}
        <div id="VWP" class="tab-pane fade {{ $tab=='VWP'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="VWP">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>{{ $vendor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ request('from_date', $from) }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ request('to_date', $to) }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $vendorGrandAmount = $vendorWisePurchase->sum('total_amount');
                $vendorGrandQty    = $vendorWisePurchase->sum('total_qty');
            @endphp

            <div class="mb-3 text-end">
                <h5>Total Qty: <span class="text-primary">{{ $vendorGrandQty }}</span></h5>
                <h3>Total Purchases: <span class="text-success">{{ number_format($vendorGrandAmount, 2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Invoice Date</th>
                        <th>Invoice No</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorWisePurchase as $vendorData)
                        <tr class="table-secondary">
                            <td colspan="7"><strong>{{ $vendorData->vendor_name }}</strong></td>
                        </tr>
                        @foreach($vendorData->items as $item)
                            <tr>
                                <td></td>
                                <td>{{ $item->invoice_date }}</td>
                                <td>{{ $item->invoice_no }}</td>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format($item->rate, 2) }}</td>
                                <td>{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Vendor Total</td>
                            <td>{{ $vendorData->total_qty }}</td>
                            <td>-</td>
                            <td>{{ number_format($vendorData->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No vendor purchase data found.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($vendorWisePurchase))
                <tfoot>
                    <tr class="fw-bold">
                        <th colspan="4" class="text-end">Grand Total</th>
                        <th>{{ $vendorWisePurchase->sum('total_qty') }}</th>
                        <th>-</th>
                        <th>{{ number_format($vendorWisePurchase->sum('total_amount'), 2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- PURCHASE BILTY REPORT --}}
        <div id="PB" class="tab-pane fade {{ $tab=='PB'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.purchase') }}">
                <input type="hidden" name="tab" value="PB">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label>Vendor</label>
                        <select name="vendor_id" class="form-control">
                            <option value="">-- All Vendors --</option>
                            @foreach($vendors as $vendor)
                                <option value="{{ $vendor->id }}" {{ request('vendor_id')==$vendor->id?'selected':'' }}>
                                    {{ $vendor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>From Date</label>
                        <input type="date" name="from_date" class="form-control" value="{{ $from }}">
                    </div>
                    <div class="col-md-3">
                        <label>To Date</label>
                        <input type="date" name="to_date" class="form-control" value="{{ $to }}">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </div>
            </form>

            @php
                $grandQty = $vendorWiseBilty->sum('total_qty');
                $grandAmount = $vendorWiseBilty->sum('total_amount');
            @endphp

            <div class="mb-3 text-end">
                <h5>Total Qty: <span class="text-primary">{{ $grandQty }}</span></h5>
                <h3>Total Bilty Amount: <span class="text-success">{{ number_format($grandAmount,2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Bilty Date</th>
                        <th>Ref No</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorWiseBilty as $vendorData)
                        <tr class="table-secondary">
                            <td colspan="7"><strong>{{ $vendorData->vendor_name }}</strong></td>
                        </tr>
                        @foreach($vendorData->items as $item)
                            <tr>
                                <td></td>
                                <td>{{ $item->bilty_date }}</td>
                                <td>{{ $item->ref_no }}</td>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->quantity .' '. $item->unit }}</td>
                                <td>{{ number_format($item->rate,2) }}</td>
                                <td>{{ number_format($item->total,2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Vendor Total</td>
                            <td>{{ $vendorData->total_qty }}</td>
                            <td>-</td>
                            <td>{{ number_format($vendorData->total_amount,2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No bilty data found.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($vendorWiseBilty))
                <tfoot>
                    <tr class="fw-bold">
                        <th colspan="4" class="text-end">Grand Total</th>
                        <th>{{ $vendorWiseBilty->sum('total_qty') }}</th>
                        <th>-</th>
                        <th>{{ number_format($vendorWiseBilty->sum('total_amount'),2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

    </div>
</div>
@endsection
