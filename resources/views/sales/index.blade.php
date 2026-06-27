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

          {{-- ── AUDIT BUTTON (superadmin only) ── --}}
          @if(auth()->user()->hasRole('superadmin'))
          <button class="btn btn-danger" id="btnOpenAudit">
            <i class="fas fa-exclamation-triangle"></i> Missing Vouchers
          </button>
          @endif

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
                      return $item->sale_price * $item->quantity;
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

{{-- ===================== AUDIT MISSING VOUCHERS MODAL ===================== --}}
@if(auth()->user()->hasRole('superadmin'))
<div class="modal fade" id="auditModal" tabindex="-1" aria-labelledby="auditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="auditModalLabel">
          <i class="fas fa-exclamation-triangle me-1"></i>
          Invoices with Missing Journal Vouchers
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        {{-- Loading state --}}
        <div id="auditLoading" class="text-center py-5">
          <i class="fas fa-spinner fa-spin fa-2x text-danger"></i>
          <p class="mt-2 text-muted">Scanning all invoices for missing vouchers…</p>
        </div>

        {{-- Summary --}}
        <div id="auditSummary" style="display:none;" class="mb-3"></div>

        {{-- No issues found --}}
        <div id="auditAllGood" style="display:none;" class="text-center py-5">
          <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
          <h5 class="text-success">All invoices have journal vouchers!</h5>
          <p class="text-muted">No missing vouchers detected.</p>
        </div>

        {{-- Toolbar --}}
        <div id="auditToolbar" style="display:none;" class="d-flex align-items-center gap-3 mb-2">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAllAudit">
            <label class="form-check-label fw-bold" for="selectAllAudit">Select All</label>
          </div>
          <span class="text-muted"><span id="auditSelectedCount">0</span> selected</span>
        </div>

        {{-- Invoice list --}}
        <div id="auditTableWrapper" style="display:none;" class="table-responsive">
          <table class="table table-sm table-bordered table-hover mb-0" id="auditTable">
            <thead class="table-danger">
              <tr>
                <th width="5%" class="text-center">
                  <input class="form-check-input" type="checkbox" id="selectAllAudit2">
                </th>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-end">Net Total</th>
                <th class="text-end">Received</th>
                <th class="text-end">Balance</th>
              </tr>
            </thead>
            <tbody id="auditTableBody">
              {{-- Filled via JS --}}
            </tbody>
          </table>
        </div>

        {{-- Result area --}}
        <div id="auditResult" class="mt-3" style="display:none;"></div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="btnFixSelected" class="btn btn-danger" disabled>
          <i class="fas fa-magic"></i> Create Vouchers for Selected
          (<span id="auditBtnCount">0</span>)
        </button>
      </div>

    </div>
  </div>
</div>
@endif

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

        <div class="d-flex align-items-center gap-3 mb-2">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="selectAllInvoices">
            <label class="form-check-label fw-bold" for="selectAllInvoices">Select All</label>
          </div>
          <span class="text-muted"><span id="selectedCount">0</span> of {{ $invoices->count() }} selected</span>
        </div>

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
                  $sub = $invoice->items->sum(fn($i) => $i->sale_price * $i->quantity);
                  $net = max(0, $sub - ($invoice->discount ?? 0));
                @endphp
                <tr class="invoice-row" style="cursor:pointer;">
                  <td class="text-center">
                    <input class="form-check-input invoice-checkbox" type="checkbox" value="{{ $invoice->id }}">
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

    // ════════════════════════════════════════════════════════════════════
    // AUDIT MODAL
    // ════════════════════════════════════════════════════════════════════

    const AUDIT_URL      = '{{ route("sale_invoices.audit_vouchers") }}';
    const REGEN_URL      = '{{ route("sale_invoices.bulk_regenerate_vouchers") }}';
    const CSRF           = '{{ csrf_token() }}';

    // Open audit modal and load data
    $('#btnOpenAudit').on('click', function () {
        // Reset state
        $('#auditLoading').show();
        $('#auditSummary, #auditAllGood, #auditToolbar, #auditTableWrapper, #auditResult').hide();
        $('#auditTableBody').empty();
        $('#auditResult').removeClass('alert alert-success alert-warning alert-danger').html('');
        $('#btnFixSelected').prop('disabled', true);
        auditUpdateUI();

        const modal = new bootstrap.Modal(document.getElementById('auditModal'));
        modal.show();

        // Fetch missing vouchers
        $.get(AUDIT_URL, function (res) {
            $('#auditLoading').hide();

            // Summary badge
            $('#auditSummary').html(`
                <div class="alert alert-${res.missing_count > 0 ? 'danger' : 'success'} mb-2">
                    <strong>Scan complete.</strong>
                    ${res.total_invoices} invoices checked —
                    <strong>${res.missing_count}</strong> missing journal voucher(s).
                </div>
            `).show();

            if (res.missing_count === 0) {
                $('#auditAllGood').show();
                return;
            }

            // Populate table
            res.invoices.forEach(function (inv) {
                const balance = (inv.net_total - inv.receipt_total).toLocaleString(
                    undefined, { minimumFractionDigits: 2 }
                );
                const netFmt  = parseFloat(inv.net_total).toLocaleString(
                    undefined, { minimumFractionDigits: 2 }
                );
                const recFmt  = parseFloat(inv.receipt_total).toLocaleString(
                    undefined, { minimumFractionDigits: 2 }
                );

                $('#auditTableBody').append(`
                    <tr id="audit-row-${inv.id}">
                        <td class="text-center">
                            <input class="form-check-input audit-checkbox"
                                   type="checkbox" value="${inv.id}" checked>
                        </td>
                        <td><strong>${inv.invoice_no}</strong></td>
                        <td>${inv.date}</td>
                        <td>${inv.customer}</td>
                        <td class="text-end">${netFmt}</td>
                        <td class="text-end text-success">${recFmt}</td>
                        <td class="text-end text-danger fw-bold">${balance}</td>
                    </tr>
                `);
            });

            $('#auditToolbar, #auditTableWrapper').show();
            auditUpdateUI();

        }).fail(function () {
            $('#auditLoading').hide();
            $('#auditSummary').html(
                '<div class="alert alert-danger">Failed to load audit data. Please try again.</div>'
            ).show();
        });
    });

    // Select all (audit)
    function auditSyncSelectAll(checked) {
        $('.audit-checkbox').prop('checked', checked);
        $('#selectAllAudit, #selectAllAudit2').prop('checked', checked).prop('indeterminate', false);
        auditUpdateUI();
    }

    $('#selectAllAudit, #selectAllAudit2').on('change', function () {
        auditSyncSelectAll(this.checked);
    });

    $(document).on('change', '.audit-checkbox', function () {
        auditUpdateUI();
    });

    function auditUpdateUI() {
        const total      = $('.audit-checkbox').length;
        const checked    = $('.audit-checkbox:checked').length;
        const allChecked = checked === total && total > 0;
        const some       = checked > 0 && !allChecked;

        $('#selectAllAudit, #selectAllAudit2')
            .prop('checked', allChecked)
            .prop('indeterminate', some);

        $('#auditSelectedCount').text(checked);
        $('#auditBtnCount').text(checked);
        $('#btnFixSelected').prop('disabled', checked === 0);
    }

    // Fix selected — runs bulk regenerate on selected audit invoices
    $('#btnFixSelected').on('click', function () {
        const ids = $('.audit-checkbox:checked').map((_, el) => el.value).get();
        if (!ids.length) return;

        if (!confirm(
            `Create journal vouchers for ${ids.length} invoice(s)?\n\n` +
            `This will generate Sales Revenue + COGS vouchers for each selected invoice.\n\n` +
            `Continue?`
        )) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing…');
        $('#auditResult').hide().html('').removeClass('alert alert-success alert-warning alert-danger');

        $.ajax({
            url:  REGEN_URL,
            type: 'POST',
            data: { _token: CSRF, invoice_ids: ids },
            success: function (res) {
                const cls  = res.failed > 0 ? 'alert-warning' : 'alert-success';
                const icon = res.failed > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle';
                let html   = `<i class="fas ${icon}"></i> ${res.message}`;

                if (res.errors && res.errors.length) {
                    html += '<ul class="mb-0 mt-2">';
                    res.errors.forEach(e => { html += `<li>${e}</li>`; });
                    html += '</ul>';
                }

                $('#auditResult').addClass(`alert ${cls}`).html(html).show();

                // Remove fixed rows from the table
                if (res.succeeded > 0) {
                    $('.audit-checkbox:checked').each(function () {
                        const invId = $(this).val();
                        $(`#audit-row-${invId}`).fadeOut(400, function () { $(this).remove(); });
                    });

                    setTimeout(function () {
                        const remaining = $('.audit-checkbox').length;
                        if (remaining === 0) {
                            $('#auditToolbar, #auditTableWrapper').hide();
                            $('#auditAllGood').show();
                            $('#auditSummary').html(
                                '<div class="alert alert-success"><strong>All vouchers have been created successfully!</strong></div>'
                            );
                        }
                        auditUpdateUI();
                    }, 500);
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Server error. Please try again.';
                $('#auditResult').addClass('alert alert-danger')
                    .html(`<i class="fas fa-times-circle"></i> ${msg}`).show();
            },
            complete: function () {
                btn.prop('disabled', false)
                   .html(`<i class="fas fa-magic"></i> Create Vouchers for Selected (<span id="auditBtnCount">${$('.audit-checkbox:checked').length}</span>)`);
            }
        });
    });

    // Reset audit modal on close
    $('#auditModal').on('hidden.bs.modal', function () {
        $('#auditLoading').show();
        $('#auditSummary, #auditAllGood, #auditToolbar, #auditTableWrapper, #auditResult').hide();
        $('#auditTableBody').empty();
        $('#btnFixSelected').prop('disabled', true);
    });

    // ════════════════════════════════════════════════════════════════════
    // REGENERATE MODAL (unchanged logic)
    // ════════════════════════════════════════════════════════════════════

    function syncSelectAll(checked) {
        $('.invoice-checkbox').prop('checked', checked);
        $('#selectAllInvoices, #selectAllInvoices2').prop('checked', checked).prop('indeterminate', false);
        updateUI();
    }

    $('#selectAllInvoices, #selectAllInvoices2').on('change', function () {
        syncSelectAll(this.checked);
    });

    $(document).on('click', '.invoice-row', function (e) {
        if ($(e.target).is('input[type="checkbox"]')) return;
        const cb = $(this).find('.invoice-checkbox');
        cb.prop('checked', !cb.prop('checked'));
        updateUI();
    });

    $(document).on('change', '.invoice-checkbox', function () {
        updateUI();
    });

    function updateUI() {
        const total      = $('.invoice-checkbox').length;
        const checked    = $('.invoice-checkbox:checked').length;
        const allChecked = checked === total;
        const some       = checked > 0 && !allChecked;

        $('#selectAllInvoices, #selectAllInvoices2')
            .prop('checked', allChecked)
            .prop('indeterminate', some);

        $('#selectedCount').text(checked);
        $('#btnCount').text(checked);
        $('#btnRunRegenerate').prop('disabled', checked === 0);
    }

    $('#regenerateModal').on('hidden.bs.modal', function () {
        $('.invoice-checkbox, #selectAllInvoices, #selectAllInvoices2')
            .prop('checked', false).prop('indeterminate', false);
        $('#regenerateResult').hide().html('')
            .removeClass('alert alert-success alert-warning alert-danger');
        updateUI();
    });

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
            url:  REGEN_URL,
            type: 'POST',
            data: { _token: CSRF, invoice_ids: ids },
            success: function (res) {
                const cls  = res.failed > 0 ? 'alert-warning' : 'alert-success';
                const icon = res.failed > 0 ? 'fa-exclamation-triangle' : 'fa-check-circle';
                let html   = `<i class="fas ${icon}"></i> ${res.message}`;
                if (res.errors && res.errors.length) {
                    html += '<ul class="mb-0 mt-2">';
                    res.errors.forEach(e => { html += `<li>${e}</li>`; });
                    html += '</ul>';
                }
                $('#regenerateResult').addClass(`alert ${cls}`).html(html).show();
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Unexpected server error.';
                $('#regenerateResult').addClass('alert alert-danger')
                    .html(`<i class="fas fa-times-circle"></i> ${msg}`).show();
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