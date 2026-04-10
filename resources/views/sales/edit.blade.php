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
                      @php
                        $isThis      = $item->product_id == $product->id;
                        // Controller already added this invoice's qty back into real_time_stock
                        $displayStock = $product->real_time_stock;
                      @endphp
                      <option value="{{ $product->id }}"
                              data-price="{{ $product->selling_price ?? 0 }}"
                              data-stock="{{ $displayStock }}"
                              {{ $isThis ? 'selected' : '' }}>
                        {{ $product->name }} ({{ $displayStock }})
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

          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
            <div class="col-md-3">
              <label>Discount (PKR)</label>
              <input type="number" name="discount" id="discountInput" class="form-control"
                     step="any" min="0" value="{{ $invoice->discount ?? 0 }}">
              <div class="mt-3 p-2 bg-light border rounded">
                <small class="text-muted d-block">Already Received</small>
                <strong class="text-success">PKR {{ number_format($amountReceived, 2) }}</strong>
                <input type="hidden" id="amountReceivedHidden" value="{{ $amountReceived }}">
              </div>
            </div>
            <div class="col-md-5 text-end">
              <label class="d-block">Net Payable</label>
              <h3 class="text-primary mb-1">PKR <span id="netAmountText">0.00</span></h3>
              <input type="hidden" name="net_amount" id="netAmountInput">
              <label class="d-block mt-2">Remaining Balance</label>
              <h4 class="text-danger mb-0">PKR <span id="balanceAmountText">0.00</span></h4>
            </div>
          </div>

          <hr>

          <div class="row p-3 mb-2 rounded" style="background:#e7f3ff;border:1px solid #b8daff;">
            <div class="col-md-12 mb-2">
              <h5><i class="fas fa-plus-circle"></i> Add New Payment (Optional)</h5>
            </div>
            <div class="col-md-6">
              <label>Receive In (Cash / Bank)</label>
              <select name="payment_account_id" class="form-control select2-js">
                <option value="">— No New Payment —</option>
                @foreach($paymentAccounts as $pa)
                  <option value="{{ $pa->id }}">{{ $pa->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label>New Amount Received Now</label>
              <input type="number" name="amount_received" class="form-control" step="any" min="0" placeholder="0.00">
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
  // Pass adjusted product stock to JS — controller already added back this invoice's quantities
  $productsJson = $products->map(fn($p) => [
      'id'    => $p->id,
      'name'  => $p->name,
      'price' => $p->selling_price ?? 0,
      'stock' => $p->real_time_stock,
  ])->values()->toJson();
@endphp

<script>
const PRODUCTS = {!! $productsJson !!};
let rowIndex = {{ $invoice->items->count() }};

$(document).ready(function () {
    $('select.select2-js').not('#itemTable select').select2({ width: '100%' });

    $('#itemTable tbody tr').each(function () {
        initRow($(this));
        calcRowTotal($(this));
        checkQtyStock($(this));
    });

    $(document).on('change', '.product-select', function () {
        const row = $(this).closest('tr');
        row.find('.sale-price').val($(this).find(':selected').data('price') || 0);
        updateStockBadge(row);
        reinitCustomizationSelect(row);
        checkQtyStock(row);
        calcRowTotal(row);
    });

    $(document).on('input', '.sale-price', function () {
        calcRowTotal($(this).closest('tr'));
    });

    $(document).on('input', '.quantity', function () {
        const row = $(this).closest('tr');
        checkQtyStock(row);
        calcRowTotal(row);
    });

    $(document).on('input', '#discountInput', calcTotal);

    calcTotal();
});

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

    const discount    = parseFloat($('#discountInput').val()) || 0;
    const netAmount   = Math.max(0, subTotal - discount);
    const alreadyPaid = parseFloat($('#amountReceivedHidden').val()) || 0;
    const balance     = netAmount - alreadyPaid;

    $('#netAmountText').text(netAmount.toLocaleString(undefined, { minimumFractionDigits: 2 }));
    $('#netAmountInput').val(netAmount.toFixed(2));
    $('#balanceAmountText').text(balance.toLocaleString(undefined, { minimumFractionDigits: 2 }));
}
</script>
@endsection