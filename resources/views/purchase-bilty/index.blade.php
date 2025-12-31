@extends('layouts.app')

@section('title', 'Purchase Bilty | All Invoices')

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
        <h2 class="card-title">All Purchase Bilty</h2>
        <a href="{{ route('purchase_bilty.create') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> Purchase Bilty
        </a>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped" id="purchaseInvoiceTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Invoice Date</th>
                <th>Vendor</th>
                <th>Ref No</th>
                <th>Remarks</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $index => $invoice)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-Y') }}</td>
                <td>{{ $invoice->vendor->name ?? 'N/A' }}</td>
                <td>{{ $invoice->ref_no }}</td>
                <td>{{ $invoice->remarks }}</td>
                <td>
                  <a href="{{ route('purchase_bilty.edit', $invoice->id) }}" class="text-primary"><i class="fas fa-edit"></i></a>
                  <a href="{{ route('purchase_bilty.print', $invoice->id) }}" target="_blank" class="text-success"><i class="fas fa-print"></i></a>
                  <form action="{{ route('purchase_bilty.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-link p-0 m-0 text-danger" onclick="return confirm('Are you sure?')"><i class="fa fa-trash-alt"></i></button>
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
  $(document).ready(function() {
    $('#purchaseInvoiceTable').DataTable({
      pageLength: 50,
      order: [[0, 'desc']],
    });
  });
</script>
@endsection
