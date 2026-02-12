@extends('layouts.app')

@section('title', 'Sales Reports')

@section('content')
<div class="tabs">
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SR'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'SR','from_date'=>$from,'to_date'=>$to]) }}">Sales Register</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='SRET'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'SRET','from_date'=>$from,'to_date'=>$to]) }}">Sales Return</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='CW'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'CW','from_date'=>$from,'to_date'=>$to]) }}">Customer Wise</a>
        </li>
        {{-- Add this check --}}
        @if(auth()->user()->hasRole('superadmin')) 
            <li class="nav-item">
                <a class="nav-link {{ $tab==='PR'?'active':'' }}" href="{{ route('reports.sale', ['tab'=>'PR','from_date'=>$from,'to_date'=>$to]) }}">Profit Report</a>
            </li>
        @endif
    </ul>

    <div class="tab-content mt-3">
        {{-- SALES REGISTER --}}

        <div id="SR" class="tab-pane fade {{ $tab==='SR'?'show active':'' }}">
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3">
                <input type="hidden" name="tab" value="SR">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->invoice }}</td>
                            <td>{{ $row->customer }}</td>
                            {{-- Use 'revenue' here if you applied the controller fix above --}}
                            <td>{{ number_format($row->revenue ?? $row->total, 2) }}</td>
                        </tr>
                    @empty
                       <tr><td colspan="4" class="text-center text-muted">No sales found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- SALES RETURN --}}
        <div id="SRET" class="tab-pane fade {{ $tab==='SRET'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3">
                <input type="hidden" name="tab" value="SRET">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Return No</th>
                        <th>Customer</th>
                        <th>Total Return</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->invoice }}</td>
                            <td>{{ $row->customer }}</td>
                            <td>{{ number_format($row->total,2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No returns found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- CUSTOMER-WISE SALE --}}
        <div id="CW" class="tab-pane fade {{ $tab=='CW'?'show active':'' }}">

            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3">
                <input type="hidden" name="tab" value="CW">

                <div class="col-md-3">
                    <label>Customer</label>
                    <select name="customer_id" class="form-control">
                        <option value="">-- All Customers --</option>
                        @foreach($customers as $cust)
                            <option value="{{ $cust->id }}" {{ request('customer_id')==$cust->id?'selected':'' }}>
                                {{ $cust->name }}
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
                    <button class="btn btn-primary w-100">Filter</button>
                </div>
            </form>

            @php
                $grandQty   = $customerWise->sum('total_qty');
                $grandTotal = $customerWise->sum('total_amount');
            @endphp

            <div class="mb-3 text-end">
                <h5>Total Qty: <span class="text-primary">{{ $grandQty }}</span></h5>
                <h3>Total Sales: <span class="text-success">{{ number_format($grandTotal, 2) }}</span></h3>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Invoice Date</th>
                        <th>Invoice No</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Rate</th>
                        <th>Total</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($customerWise as $custData)

                        <tr class="table-secondary">
                            <td colspan="7"><strong>{{ $custData->customer_name }}</strong></td>
                        </tr>

                        @foreach($custData->items as $item)
                            <tr>
                                <td></td>
                                <td>{{ $item->invoice_date }}</td>
                                <td>{{ $item->invoice_no }}</td>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format($item->rate,2) }}</td>
                                <td>{{ number_format($item->total,2) }}</td>
                            </tr>
                        @endforeach

                        <tr class="fw-bold">
                            <td colspan="4" class="text-end">Customer Total</td>
                            <td>{{ $custData->total_qty }}</td>
                            <td>-</td>
                            <td>{{ number_format($custData->total_amount,2) }}</td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No customer sale data found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if(count($customerWise))
                <tfoot>
                    <tr class="fw-bold">
                        <th colspan="4" class="text-end">Grand Total</th>
                        <th>{{ $grandQty }}</th>
                        <th>-</th>
                        <th>{{ number_format($grandTotal,2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- PROFIT REPORT --}}
        <div id="PR" class="tab-pane fade {{ $tab==='PR'?'show active':'' }}">
            <form method="GET" action="{{ route('reports.sale') }}" class="row g-3 mb-3">
                <input type="hidden" name="tab" value="PR">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" class="form-control" name="from_date" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" class="form-control" name="to_date" value="{{ $to }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Calculate Profit</button>
                </div>
            </form>

            <table class="table table-bordered table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Sale (Net)</th>
                        <th>Landed Cost</th>
                        <th>Profit</th>
                        <th>Margin %</th>
                        <th>Action</th> {{-- Added --}}
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $row)
                        <tr>
                            <td>{{ $row->date }}</td>
                            <td>{{ $row->invoice }}</td>
                            <td>{{ $row->customer }}</td>
                            <td>{{ number_format($row->revenue, 2) }}</td>
                            <td>{{ number_format($row->cost, 2) }}</td>
                            <td class="{{ $row->profit >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                                {{ number_format($row->profit, 2) }}
                            </td>
                            <td>{{ number_format($row->margin, 1) }}%</td>
                            <td>
                                {{-- Match the route name defined in your routes file --}}
                                <a href="{{ route('reports.print-profit', ['id' => $row->id]) }}" 
                                target="_blank" 
                                class="btn btn-sm btn-danger">
                                    <i class="fa fa-file-pdf"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No data found.</td></tr>
                    @endforelse
                </tbody>
                @if(count($sales))
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">Totals:</td>
                        <td>{{ number_format($sales->sum('revenue'), 2) }}</td>
                        <td>{{ number_format($sales->sum('cost'), 2) }}</td>
                        <td class="text-primary">{{ number_format($sales->sum('profit'), 2) }}</td>
                        <td>
                            {{ $sales->sum('revenue') > 0 ? number_format(($sales->sum('profit') / $sales->sum('revenue')) * 100, 1) : 0 }}%
                        </td>
                    </tr>
                </tfoot>
                @endif
                
            </table>
        </div>
    </div>
</div>
@endsection
