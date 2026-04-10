@extends('layouts.app')

@section('title', 'Sale Invoices')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">

      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">All Sale Invoices</h2>
        <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> Sale Invoice
        </a>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover datatable">
            <thead class="thead-dark">
              <tr>
                <th>#</th>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Type</th>
                <th class="text-end">Total Amount</th>
                <th class="text-end">Received</th>
                <th class="text-end">Balance</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $invoice)
                @php
                  // Gross items total (item-level discounts applied)
                  $subTotal = $invoice->items->sum(function ($item) {
                      $disc = $item->discount ?? 0;
                      $discountedPrice = $item->sale_price - ($item->sale_price * $disc / 100);
                      return $discountedPrice * $item->quantity;
                  });
                  // Invoice-level discount
                  $netTotal = max(0, $subTotal - ($invoice->discount ?? 0));
                  // Payments received (receipt vouchers)
                  $received = $invoice->receiptVouchers->sum('amount');
                  $balance  = $netTotal - $received;
                @endphp
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{ $invoice->invoice_no }}</td>
                  <td>{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</td>
                  <td>{{ $invoice->account->name ?? 'POS Customer' }}</td>
                  <td>
                    <span class="badge {{ $invoice->type === 'credit' ? 'bg-warning text-dark' : 'bg-success' }}">
                      {{ ucfirst($invoice->type) }}
                    </span>
                  </td>
                  <td class="text-end">{{ number_format($netTotal, 2) }}</td>
                  <td class="text-end text-success">{{ number_format($received, 2) }}</td>
                  <td class="text-end {{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($balance, 2) }}
                  </td>
                  <td>
                    <a href="{{ route('sale_invoices.edit', $invoice->id) }}" class="text-primary me-1">
                      <i class="fas fa-edit"></i>
                    </a>
                    <a href="{{ route('sale_invoices.print', $invoice->id) }}" target="_blank" class="text-success me-1">
                      <i class="fas fa-print"></i>
                    </a>
                    <form action="{{ route('sale_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="text-danger" style="border:none;background:none;"
                              onclick="return confirm('Delete this invoice and all its accounting entries?')">
                        <i class="fas fa-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

    </section>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.datatable').DataTable({
      pageLength: 100,
      order: [[1, 'desc']], // newest invoice first
    });
  });
</script>
@endsection