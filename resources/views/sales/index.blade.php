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
        <div class="d-flex gap-2">
          <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#regenerateModal">
            <i class="fas fa-sync-alt"></i> Regenerate Vouchers
          </button>
          <a href="{{ route('sale_invoices.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Sale Invoice
          </a>
        </div>
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
                  $subTotal = $invoice->items->sum(function ($item) {
                      $disc = $item->discount ?? 0;
                      $discountedPrice = $item->sale_price - ($item->sale_price * $disc / 100);
                      return $discountedPrice * $item->quantity;
                  });
                  $netTotal = max(0, $subTotal - ($invoice->discount ?? 0));
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

{{-- ===================== REGENERATE MODAL ===================== --}}
<div class="modal fade" id="regenerateModal" tabindex="-1" aria-labelledby="regenerateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header" style="background:#fff3cd;">
        <h5 class="modal-title" id="regenerateModalLabel">
          <i class="fas fa-sync-alt"></i> Regenerate Journal Vouchers
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="alert alert-info">
          <i class="fas fa-info-circle"></i>
          For each selected invoice, <strong>all journal vouchers will be deleted and recreated</strong>
          (Sales Revenue + COGS). Receipt/payment vouchers are <strong>never touched</strong>.
        </div>

        {{-- Toolbar --}}
        <div class="d-flex align-items-center gap-3 mb-2">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAllInvoices">
            <label class="form-check-label fw-bold" for="selectAllInvoices">Select All</label>
          </div>
          <span class="text-muted"><span id="selectedCount">0</span> of {{ $invoices->count() }} selected</span>
        </div>

        {{-- Invoice list --}}
        <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
          <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-dark" style="position:sticky;top:0;z-index:1;">
              <tr>
                <th width="5%" class="text-center">
                  <input class="form-check-input" type="checkbox" id="selectAllInvoices2">
                </th>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Type</th>
                <th class="text-end">Net Total</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($invoices as $invoice)
                @php
                  $sub = $invoice->items->sum(fn($i) =>
                    ($i->sale_price - ($i->sale_price * ($i->discount ?? 0) / 100)) * $i->quantity
                  );
                  $net = max(0, $sub - ($invoice->discount ?? 0));
                @endphp
                <tr class="invoice-row" style="cursor:pointer;">
                  <td class="text-center">
                    <input class="form-check-input invoice-checkbox"
                           type="checkbox" value="{{ $invoice->id }}">
                  </td>
                  <td>{{ $invoice->invoice_no }}</td>
                  <td>{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</td>
                  <td>{{ $invoice->account->name ?? '—' }}</td>
                  <td>
                    <span class="badge {{ $invoice->type === 'credit' ? 'bg-warning text-dark' : 'bg-success' }}">
                      {{ ucfirst($invoice->type) }}
                    </span>
                  </td>
                  <td class="text-end">{{ number_format($net, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Result area --}}
        <div id="regenerateResult" class="mt-3" style="display:none;"></div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="btnRunRegenerate" class="btn btn-danger" disabled>
          <i class="fas fa-sync-alt"></i> Regenerate (<span id="btnCount">0</span> selected)
        </button>
      </div>

    </div>
  </div>
</div>

<script>
$(document).ready(function () {

    // ── DataTable ────────────────────────────────────────────────────────
    $('.datatable').DataTable({
        pageLength: 100,
        order: [[1, 'desc']],
    });

    // ── Select all (both the label checkbox and the thead checkbox) ──────
    function syncSelectAll(checked) {
        $('.invoice-checkbox').prop('checked', checked);
        $('#selectAllInvoices, #selectAllInvoices2').prop('checked', checked).prop('indeterminate', false);
        updateUI();
    }

    $('#selectAllInvoices, #selectAllInvoices2').on('change', function () {
        syncSelectAll(this.checked);
    });

    // Row click toggles checkbox
    $(document).on('click', '.invoice-row', function (e) {
        if ($(e.target).is('input[type="checkbox"]')) return; // let native check handle it
        const cb = $(this).find('.invoice-checkbox');
        cb.prop('checked', !cb.prop('checked'));
        updateUI();
    });

    $(document).on('change', '.invoice-checkbox', function () {
        updateUI();
    });

    function updateUI() {
        const total   = $('.invoice-checkbox').length;
        const checked = $('.invoice-checkbox:checked').length;
        const allChecked = checked === total;
        const someChecked = checked > 0 && !allChecked;

        $('#selectAllInvoices, #selectAllInvoices2')
            .prop('checked', allChecked)
            .prop('indeterminate', someChecked);

        $('#selectedCount').text(checked);
        $('#btnCount').text(checked);
        $('#btnRunRegenerate').prop('disabled', checked === 0);
    }

    // Reset modal state when closed
    $('#regenerateModal').on('hidden.bs.modal', function () {
        $('.invoice-checkbox, #selectAllInvoices, #selectAllInvoices2').prop('checked', false).prop('indeterminate', false);
        $('#regenerateResult').hide().html('').removeClass('alert alert-success alert-warning alert-danger');
        updateUI();
    });

    // ── Run ──────────────────────────────────────────────────────────────
    $('#btnRunRegenerate').on('click', function () {
        const ids = $('.invoice-checkbox:checked').map((_, el) => el.value).get();
        if (!ids.length) return;

        if (!confirm(
            `This will DELETE and RECREATE journal vouchers for ${ids.length} invoice(s).\n\n` +
            `Receipt/payment vouchers will NOT be affected.\n\nContinue?`
        )) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing…');
        $('#regenerateResult').hide().html('');

        $.ajax({
            url:  '{{ route("sale_invoices.bulk_regenerate_vouchers") }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', invoice_ids: ids },
            success: function (res) {
                const cls = res.failed > 0 ? 'alert-warning' : 'alert-success';
                const icon = res.failed > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle';
                let html = `<i class="fas ${icon}"></i> ${res.message}`;
                if (res.errors && res.errors.length) {
                    html += '<ul class="mb-0 mt-2">';
                    res.errors.forEach(e => { html += `<li>${e}</li>`; });
                    html += '</ul>';
                }
                $('#regenerateResult').addClass(`alert ${cls}`).html(html).show();
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Unexpected server error.';
                $('#regenerateResult')
                    .addClass('alert alert-danger')
                    .html(`<i class="fas fa-times-circle"></i> ${msg}`)
                    .show();
            },
            complete: function () {
                btn.prop('disabled', false)
                   .html(`<i class="fas fa-sync-alt"></i> Regenerate (<span id="btnCount">${$('.invoice-checkbox:checked').length}</span> selected)`);
            }
        });
    });

});
</script>
@endsection