@extends('layouts.app')

@section('title', 'Edit Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.update', $invoice->id) }}" onkeydown="return event.key != 'Enter';" method="POST">
    @csrf
    @method('PUT')

    {{-- ================= HEADER CARD ================= --}}
    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Sale Invoice: #{{ $invoice->invoice_no }}</h2>
          @if ($errors->any())
            <div class="alert alert-danger mb-0">
              <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
          @endif
        </header>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" class="form-control" value="{{ $invoice->invoice_no }}" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="date" class="form-control" value="{{ $invoice->date }}" required />
            </div>
            <div class="col-md-3">
              <label>Customer <span class="text-danger">*</span></label>
              <select name="account_id" class="form-control select2-js" required>
                @foreach($customers as $acc)
                  <option value="{{ $acc->id }}" {{ $invoice->account_id == $acc->id ? 'selected' : '' }}>
                    {{ $acc->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Type <span class="text-danger">*</span></label>
              <select name="type" class="form-control" required>
                <option value="cash"   {{ $invoice->type === 'cash'   ? 'selected' : '' }}>Cash</option>
                <option value="credit" {{ $invoice->type === 'credit' ? 'selected' : '' }}>Credit</option>
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    {{-- ================= ITEMS CARD ================= --}}
    <div class="col-12">
      <section class="card">
        <header class="card-header"><h2 class="card-title">Invoice Items</h2></header>
        <div class="card-body">

          <table class="table table-bordered table-sm" id="itemTable">
            <thead class="table-light">
              <tr>
                <th width="22%">Product</th>
                <th width="38%">Customizations</th>
                <th width="12%">Price</th>
                <th width="10%">Qty</th>
                <th width="12%">Total</th>
                <th width="6%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($invoice->items as $i => $item)
              <tr>
                <td>
                  <select name="items[{{ $i }}][product_id]" class="form-control product-select" required>
                    <option value="">— Select —</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}"
                              data-price="{{ $product->selling_price ?? 0 }}"
                              data-stock="{{ $product->real_time_stock }}"
                              {{ $item->product_id == $product->id ? 'selected' : '' }}>
                        {{ $product->name }} ({{ $product->real_time_stock }})
                      </option>
                    @endforeach
                  </select>
                  <div class="stock-badge mt-1"></div>
                </td>
                <td>
                  <select name="items[{{ $i }}][customizations][]" multiple class="form-control customization-select">
                    @foreach($products as $product)
                      <option value="{{ $product->id }}"
                              data-stock="{{ $product->real_time_stock }}"
                              {{ $item->customizations->pluck('item_id')->contains($product->id) ? 'selected' : '' }}>
                        {{ $product->name }} ({{ $product->real_time_stock }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <input type="number" name="items[{{ $i }}][sale_price]" class="form-control sale-price"
                         step="any" min="0" value="{{ $item->sale_price }}" required>
                </td>
                <td>
                  <input type="number" name="items[{{ $i }}][quantity]" class="form-control quantity"
                         step="any" min="0.01" value="{{ $item->quantity }}" required>
                  <div class="qty-warning text-danger" style="font-size:11px;"></div>
                </td>
                <td>
                  <input type="number" class="form-control row-total"
                         value="{{ $item->sale_price * $item->quantity }}" readonly>
                </td>
                <td>
                  <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>

          <button type="button" class="btn btn-success btn-sm mb-3" onclick="addRow()">
            <i class="fas fa-plus"></i> Add Item
          </button>

          <hr>

          {{-- ================= TOTALS ROW ================= --}}
          <div class="row mb-3">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
            <div class="col-md-3">
              <label>Discount (PKR)</label>
              <input type="number" name="discount" id="discountInput" class="form-control"
                     step="any" min="0" value="{{ $invoice->discount ?? 0 }}">
            </div>
            <div class="col-md-5 text-end">
              <label class="d-block">Net Payable</label>
              <h3 class="text-primary mb-1">PKR <span id="netAmountText">0.00</span></h3>
              <input type="hidden" name="net_amount" id="netAmountInput">
              <label class="d-block mt-2">Total Received</label>
              <h5 class="text-success mb-1">PKR <span id="totalReceivedText">{{ number_format($amountReceived, 2) }}</span></h5>
              <label class="d-block">Remaining Balance</label>
              <h4 class="text-danger mb-0">PKR <span id="balanceAmountText">0.00</span></h4>
            </div>
          </div>

          <hr>

          {{-- ================= PAYMENT HISTORY (EDITABLE) ================= --}}
          @if($invoice->receiptVouchers->count())
          <div id="receiptsSection" class="mb-3">
            <h5 class="mb-2">
              <i class="fas fa-history me-1 text-warning"></i> Payment History
              <small class="text-muted fw-normal">(edit or delete existing payments)</small>
            </h5>
            <div class="table-responsive">
              <table class="table table-sm table-bordered" id="receiptsTable">
                <thead class="table-warning">
                  <tr>
                    <th style="font-size:11px;">Date</th>
                    <th style="font-size:11px;">Account (Cash / Bank)</th>
                    <th class="text-end" style="font-size:11px;">Amount</th>
                    <th style="font-size:11px;">Remarks</th>
                    <th width="5%" style="font-size:11px;"></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($invoice->receiptVouchers as $rv)
                  <tr id="receipt-row-{{ $rv->id }}">
                    <td>
                      <input type="date"
                             name="existing_receipts[{{ $rv->id }}][date]"
                             class="form-control form-control-sm"
                             value="{{ $rv->date }}">
                    </td>
                    <td>
                      <select name="existing_receipts[{{ $rv->id }}][payment_account_id]"
                              class="form-control form-control-sm receipt-account-select">
                        @foreach($paymentAccounts as $pa)
                          <option value="{{ $pa->id }}"
                                  {{ $rv->ac_dr_sid == $pa->id ? 'selected' : '' }}>
                            {{ $pa->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <input type="number"
                             name="existing_receipts[{{ $rv->id }}][amount]"
                             class="form-control form-control-sm text-end receipt-amount"
                             step="any" min="0"
                             value="{{ $rv->amount }}">
                    </td>
                    <td>
                      <input type="text"
                             name="existing_receipts[{{ $rv->id }}][remarks]"
                             class="form-control form-control-sm"
                             value="{{ $rv->remarks ?? '' }}">
                    </td>
                    <td class="text-center">
                      <button type="button"
                              class="btn btn-danger btn-sm delete-receipt-btn"
                              data-voucher-id="{{ $rv->id }}"
                              title="Delete this receipt">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="2" class="text-end fw-bold py-2">Total Received:</td>
                    <td class="text-end fw-bold text-success py-2" id="receiptTableTotal">
                      PKR {{ number_format($amountReceived, 2) }}
                    </td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          @endif

          {{-- ================= ADD NEW PAYMENT ================= --}}
          <div class="p-3 mb-2 rounded" style="background:#e7f3ff;border:1px solid #b8daff;">
            <h5 class="mb-3"><i class="fas fa-plus-circle me-1"></i> Add New Payment <small class="text-muted fw-normal">(optional)</small></h5>
            <div class="row">
              <div class="col-md-3">
                <label class="small fw-bold">Date</label>
                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}">
              </div>
              <div class="col-md-4">
                <label class="small fw-bold">Receive In (Cash / Bank)</label>
                <select name="payment_account_id" class="form-control select2-js">
                  <option value="">— No New Payment —</option>
                  @foreach($paymentAccounts as $pa)
                    <option value="{{ $pa->id }}">{{ $pa->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="small fw-bold">Amount</label>
                <input type="number" name="amount_received" class="form-control"
                       step="any" min="0" placeholder="0.00">
              </div>
              <div class="col-md-2">
                <label class="small fw-bold">Remarks</label>
                <input type="text" name="payment_remarks" class="form-control" placeholder="Optional">
              </div>
            </div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary me-2">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Update Invoice
          </button>
        </footer>
      </section>
    </div>

  </form>
</div>

@php
  $productsJson = $products->map(fn($p) => [
      'id'    => $p->id,
      'name'  => $p->name,
      'price' => $p->selling_price ?? 0,
      'stock' => $p->real_time_stock,
  ])->values()->toJson();
@endphp

<script>
const PRODUCTS     = {!! $productsJson !!};
const DELETE_URL   = '{{ route("sale_invoices.delete_receipt", ":id") }}';
const CSRF_TOKEN   = '{{ csrf_token() }}';
let rowIndex       = {{ $invoice->items->count() }};

$(document).ready(function () {

    // ── Select2 init (non-table) ───────────────────────────────────
    $('select.select2-js').not('#itemTable select').select2({ width: '100%' });

    // ── Select2 init for receipt account dropdowns ─────────────────
    $('.receipt-account-select').select2({ width: '100%' });

    // ── Init existing item rows ────────────────────────────────────
    $('#itemTable tbody tr').each(function () {
        initRow($(this));
        calcRowTotal($(this));
        checkQtyStock($(this));
    });

    // ── Product change ─────────────────────────────────────────────
    $(document).on('change', '.product-select', function () {
        const row = $(this).closest('tr');
        row.find('.sale-price').val($(this).find(':selected').data('price') || 0);
        updateStockBadge(row);
        reinitCustomizationSelect(row);
        checkQtyStock(row);
        calcRowTotal(row);
    });

    // ── Price / qty input ──────────────────────────────────────────
    $(document).on('input', '.sale-price', function () {
        calcRowTotal($(this).closest('tr'));
    });

    $(document).on('input', '.quantity', function () {
        const row = $(this).closest('tr');
        checkQtyStock(row);
        calcRowTotal(row);
    });

    // ── Discount change ────────────────────────────────────────────
    $(document).on('input', '#discountInput', calcTotal);

    // ── Receipt amount live update ─────────────────────────────────
    $(document).on('input', '.receipt-amount', function () {
        recalcReceipts();
    });

    // ── Delete receipt (AJAX) ──────────────────────────────────────
    $(document).on('click', '.delete-receipt-btn', function () {
        const btn       = $(this);
        const voucherId = btn.data('voucher-id');

        if (!confirm('Delete this payment receipt?\n\nThis will permanently remove it from accounting records.')) return;

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url:  DELETE_URL.replace(':id', voucherId),
            type: 'DELETE',
            data: { _token: CSRF_TOKEN },
            success: function (res) {
                if (res.success) {
                    $('#receipt-row-' + voucherId).fadeOut(300, function () {
                        $(this).remove();
                        recalcReceipts();

                        // Hide the whole section if no receipts left
                        if ($('#receiptsTable tbody tr').length === 0) {
                            $('#receiptsSection').fadeOut();
                        }
                    });
                } else {
                    alert('Error: ' + res.message);
                    btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message ?? 'Server error. Could not delete receipt.';
                alert(msg);
                btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
            }
        });
    });

    // ── Initial calculation ────────────────────────────────────────
    calcTotal();
});

/* ── Receipt total recalculation ─────────────────────────────────── */
function recalcReceipts() {
    let total = 0;
    $('.receipt-amount').each(function () {
        total += parseFloat($(this).val()) || 0;
    });

    const fmt = total.toLocaleString(undefined, { minimumFractionDigits: 2 });
    $('#receiptTableTotal').text('PKR ' + fmt);
    $('#totalReceivedText').text(fmt);

    // Recalc balance
    const net     = parseFloat($('#netAmountInput').val()) || 0;
    const balance = net - total;
    $('#balanceAmountText').text(balance.toLocaleString(undefined, { minimumFractionDigits: 2 }));
}

/* ── Main total calculation ──────────────────────────────────────── */
function calcTotal() {
    let subTotal = 0;
    $('.row-total').each(function () { subTotal += parseFloat($(this).val()) || 0; });

    const discount  = parseFloat($('#discountInput').val()) || 0;
    const netAmount = Math.max(0, subTotal - discount);

    $('#netAmountText').text(netAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#netAmountInput').val(netAmount.toFixed(2));

    recalcReceipts(); // handles balance using receipt amounts
}

/* ── Row helpers ─────────────────────────────────────────────────── */
function initRow(row) {
    row.find('.product-select').select2({ width: '100%' });
    reinitCustomizationSelect(row);
    updateStockBadge(row);
}

function reinitCustomizationSelect(row) {
    const custSel = row.find('.customization-select');
    const mainId  = row.find('.product-select').val();

    custSel.find('option').prop('disabled', false);
    if (mainId) custSel.find(`option[value="${mainId}"]`).prop('disabled', true);

    if (custSel.hasClass('select2-hidden-accessible')) custSel.select2('destroy');
    custSel.select2({ width: '100%', placeholder: 'Customizations…', closeOnSelect: false });
}

function updateStockBadge(row) {
    const opt   = row.find('.product-select :selected');
    const stock = parseFloat(opt.data('stock')) || 0;
    const badge = row.find('.stock-badge');

    if (!row.find('.product-select').val()) { badge.html(''); return; }

    const color = stock > 5 ? 'success' : (stock > 0 ? 'warning' : 'danger');
    badge.html(`<span class="badge bg-${color}">Stock: ${stock}</span>`);
}

function checkQtyStock(row) {
    const opt   = row.find('.product-select :selected');
    const stock = parseFloat(opt.data('stock')) || 0;
    const qty   = parseFloat(row.find('.quantity').val()) || 0;
    const warn  = row.find('.qty-warning');
    const input = row.find('.quantity');

    if (stock <= 0 && row.find('.product-select').val()) {
        input.css('border-color', 'red');
        warn.text('⚠ Out of stock');
    } else if (qty > stock) {
        input.css('border-color', 'orange');
        warn.text(`⚠ Only ${stock} available`);
    } else {
        input.css('border-color', '');
        warn.text('');
    }
}

function addRow() {
    const idx = rowIndex++;
    let productOpts = '<option value="">— Select —</option>';
    let customOpts  = '';
    PRODUCTS.forEach(p => {
        productOpts += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}">${p.name} (${p.stock})</option>`;
        customOpts  += `<option value="${p.id}" data-stock="${p.stock}">${p.name} (${p.stock})</option>`;
    });

    const rowHtml = `
    <tr>
      <td>
        <select name="items[${idx}][product_id]" class="form-control product-select" required>
          ${productOpts}
        </select>
        <div class="stock-badge mt-1"></div>
      </td>
      <td>
        <select name="items[${idx}][customizations][]" multiple class="form-control customization-select">
          ${customOpts}
        </select>
      </td>
      <td><input type="number" name="items[${idx}][sale_price]" class="form-control sale-price" step="any" min="0" required></td>
      <td>
        <input type="number" name="items[${idx}][quantity]" class="form-control quantity" step="any" min="0.01" required>
        <div class="qty-warning text-danger" style="font-size:11px;"></div>
      </td>
      <td><input type="number" name="items[${idx}][total]" class="form-control row-total" readonly></td>
      <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
    </tr>`;

    $('#itemTable tbody').append(rowHtml);
    initRow($('#itemTable tbody tr').last());
}

function removeRow(btn) {
    if ($('#itemTable tbody tr').length > 1) {
        $(btn).closest('tr').remove();
        calcTotal();
    }
}

function calcRowTotal(row) {
    const price = parseFloat(row.find('.sale-price').val()) || 0;
    const qty   = parseFloat(row.find('.quantity').val())   || 0;
    row.find('.row-total').val((price * qty).toFixed(2));
    calcTotal();
}
</script>
@endsection