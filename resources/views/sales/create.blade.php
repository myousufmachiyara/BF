@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.store') }}" onkeydown="return event.key != 'Enter';" method="POST">
    @csrf

    {{-- ================= HEADER CARD ================= --}}
    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Create Sale Invoice</h2>
          @if ($errors->any())
            <div class="alert alert-danger mb-0">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
        </header>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" class="form-control" placeholder="Auto" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-3">
              <label>Customer <span class="text-danger">*</span></label>
              <select name="account_id" class="form-control select2-js" required>
                <option value="">— Select Customer —</option>
                @foreach($customers as $account)
                  <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Type <span class="text-danger">*</span></label>
              <select name="type" id="invoiceType" class="form-control" required>
                <option value="credit">Credit</option>
                <option value="cash">Cash</option>
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    {{-- ================= ITEMS CARD ================= --}}
    <div class="col-12">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Invoice Items</h2>
        </header>
        <div class="card-body">

          <table class="table table-bordered table-sm" id="itemTable">
            <thead class="table-light">
              <tr>
                <th width="22%">Product</th>
                <th width="40%">Customizations</th>
                <th width="12%">Price</th>
                <th width="10%">Qty</th>
                <th width="12%">Total</th>
                <th width="4%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name="items[0][product_id]" class="form-control product-select" required>
                    <option value="">— Select —</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}"
                              data-price="{{ $product->selling_price ?? 0 }}"
                              data-stock="{{ $product->real_time_stock }}">
                        {{ $product->name }} ({{ $product->real_time_stock }})
                      </option>
                    @endforeach
                  </select>
                  <div class="stock-badge mt-1"></div>
                </td>
                <td>
                  <select name="items[0][customizations][]" multiple class="form-control customization-select">
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" data-stock="{{ $product->real_time_stock }}">
                        {{ $product->name }} ({{ $product->real_time_stock }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" name="items[0][sale_price]" class="form-control sale-price" step="any" min="0" required></td>
                <td>
                  <input type="number" name="items[0][quantity]" class="form-control quantity" step="any" min="0.01" required>
                  <div class="qty-warning text-danger" style="font-size:11px;"></div>
                </td>
                <td><input type="number" name="items[0][total]" class="form-control row-total" readonly></td>
                <td>
                  <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                    <i class="fas fa-times"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <button type="button" class="btn btn-success btn-sm mb-3" onclick="addRow()">
            <i class="fas fa-plus"></i> Add Item
          </button>

          <hr>

          {{-- Remarks + Discount + Totals --}}
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-2">
              <label>Discount (PKR)</label>
              <input type="number" name="discount" id="discountInput" class="form-control" step="any" min="0" value="0">
            </div>
            <div class="col-md-6 text-end">
              <label class="d-block">Net Payable</label>
              <h3 class="text-primary mb-0">PKR <span id="netAmountText">0.00</span></h3>
              <input type="hidden" name="net_amount" id="netAmountInput" value="0">
            </div>
          </div>

          <hr>

          {{-- Payment --}}
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Receive Payment To</label>
              <select name="payment_account_id" class="form-control select2-js">
                <option value="">— No Payment (Credit) —</option>
                @foreach($paymentAccounts as $pAc)
                  <option value="{{ $pAc->id }}">{{ $pAc->name }}</option>
                @endforeach
              </select>
              <small class="text-muted">Select Cash/Bank if payment received now.</small>
            </div>
            <div class="col-md-3">
              <label>Amount Received</label>
              <input type="number" name="amount_received" id="amountReceived" class="form-control" step="any" min="0" value="0">
            </div>
            <div class="col-md-5 text-end">
              <label class="d-block">Remaining Balance</label>
              <h4 class="text-danger mb-0">PKR <span id="balanceAmountText">0.00</span></h4>
            </div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary me-2">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Save Invoice
          </button>
        </footer>
      </section>
    </div>

  </form>
</div>

@php
  // Encode products once for JS — avoids re-rendering inside addRow()
  $productsJson = $products->map(fn($p) => [
      'id'    => $p->id,
      'name'  => $p->name,
      'price' => $p->selling_price ?? 0,
      'stock' => $p->real_time_stock,
  ])->values()->toJson();
@endphp

<script>
const PRODUCTS = {!! $productsJson !!};
let rowIndex = 1;

$(document).ready(function () {
    // Non-table Select2s
    $('select.select2-js').not('#itemTable select').select2({ width: '100%' });

    // Init the first row
    $('#itemTable tbody tr').each(function () {
        initRow($(this));
        calcRowTotal($(this));
    });

    // Product change
    $(document).on('change', '.product-select', function () {
        const row = $(this).closest('tr');
        const opt = $(this).find(':selected');
        row.find('.sale-price').val(opt.data('price') || 0);
        updateStockBadge(row);
        reinitCustomizationSelect(row);
        calcRowTotal(row);
    });

    // Price / qty input
    $(document).on('input', '.sale-price, .quantity', function () {
        const row = $(this).closest('tr');
        if ($(this).hasClass('quantity')) checkQtyStock(row);
        calcRowTotal(row);
    });

    // Discount / received
    $(document).on('input', '#discountInput, #amountReceived', calcTotal);

    // Type toggle: cash → auto-fill received
    $(document).on('change', '#invoiceType', function () {
        if ($(this).val() === 'cash') {
            $('#amountReceived').val($('#netAmountInput').val());
        } else {
            $('#amountReceived').val(0);
        }
        calcTotal();
    });

    calcTotal();
});

function initRow(row) {
    row.find('.product-select').select2({ width: '100%' });
    reinitCustomizationSelect(row);
    updateStockBadge(row);
}

function reinitCustomizationSelect(row) {
    const custSel  = row.find('.customization-select');
    const mainId   = row.find('.product-select').val();

    custSel.find('option').prop('disabled', false);
    if (mainId) {
        custSel.find(`option[value="${mainId}"]`).prop('disabled', true);
    }

    if (custSel.hasClass('select2-hidden-accessible')) custSel.select2('destroy');
    custSel.select2({ width: '100%', placeholder: 'Customizations…', closeOnSelect: false });
}

function updateStockBadge(row) {
    const opt   = row.find('.product-select :selected');
    const stock = parseFloat(opt.data('stock')) || 0;
    const badge = row.find('.stock-badge');

    if (!row.find('.product-select').val()) { badge.html(''); return; }

    let color = stock > 5 ? 'success' : (stock > 0 ? 'warning' : 'danger');
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

    // Build options from PRODUCTS array — no Blade loop inside JS
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

function calcTotal() {
    let subTotal = 0;
    $('.row-total').each(function () { subTotal += parseFloat($(this).val()) || 0; });

    const discount  = parseFloat($('#discountInput').val()) || 0;
    const netAmount = Math.max(0, subTotal - discount);
    const received  = parseFloat($('#amountReceived').val()) || 0;
    const balance   = netAmount - received;

    $('#netAmountText').text(netAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#netAmountInput').val(netAmount.toFixed(2));
    $('#balanceAmountText').text(balance.toLocaleString(undefined, { minimumFractionDigits: 2 }));
}
</script>
@endsection