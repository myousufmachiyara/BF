@extends('layouts.app')

@section('title', 'Purchases | All Invoices')

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
                <h2 class="card-title">
                    {{ request('view_deleted') ? 'Deleted' : 'All' }} Purchase Invoices
                </h2>
                <div>
                    @if(request('view_deleted'))
                        <a href="{{ route('purchase_invoices.index') }}" class="btn btn-default mr-2">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('purchase_invoices.index', ['view_deleted' => 1]) }}" class="btn btn-danger mr-2">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif
                    <a href="{{ route('purchase_invoices.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Purchase Invoice
                    </a>
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="purchaseInvoiceTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Invoice Date</th>
                                <th>Invoice #</th>
                                <th>Vendor</th>
                                <th>Attachments</th>
                                <th width="8%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $index => $invoice)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">PUR-{{ $invoice->invoice_no ?? 'N/A' }}</td>
                                <td>{{ $invoice->vendor->name ?? 'N/A' }}</td>
                                <td>
                                    @if($invoice->attachments && count($invoice->attachments))
                                        @foreach ($invoice->attachments as $file)
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="mr-2">
                                                <i class="fas fa-file"></i>
                                            </a>
                                        @endforeach
                                    @else
                                        <span class="text-muted">No files</span>
                                    @endif
                                </td>
                                <td>
                                    @if($invoice->trashed())
                                        {{-- Restore Logic --}}
                                        <form action="{{ route('purchase_invoices.restore', $invoice->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-link p-0 text-success" title="Restore">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </form>
                                    @else
                                        {{-- Normal Actions --}}
                                        <a href="{{ route('purchase_invoices.edit', $invoice->id) }}" class="text-primary mr-2" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('purchase_invoices.print', $invoice->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <form action="{{ route('purchase_invoices.destroy', $invoice->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Move to trash?')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
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
