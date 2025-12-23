@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.store') }}" method="POST">
    @csrf

    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Create Sale Invoice</h2>
          @if ($errors->any())
            <div class="alert alert-danger">
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
              <input type="text" name="invoice_no" class="form-control" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date</label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-3">
              <label>Customer Name</label>
              <select name="account_id" class="form-control select2-js" required>
                <option value="">Select Customer</option>
                @foreach($accounts as $account)
                  <option value="{{ $account->id }}">{{ $account->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Invoice Type</label>
              <select name="type" class="form-control" required>
                <option value="cash">POS (Cash)</option>
                <option value="credit">Credit (E-commerce)</option>
              </select>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Invoice Items</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr>
                <th width="15%">Item Code</th>
                <th>Product</th>
                <th>Variation</th>
                <th width="12%">Price</th>
                <th width="10%">Discount(%)</th>
                <th width="12%">Qty</th>
                <th width="12%">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
                <td>
                  <select name="items[0][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][variation_id]" class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td><input type="number" name="items[0][sale_price]" class="form-control sale-price" step="any" required></td>
                <td><input type="number" name="items[0][disc_price]" class="form-control disc-price" step="any" value="0"></td>
                <td><input type="number" name="items[0][quantity]" class="form-control quantity" step="any" required></td>
                <td><input type="number" name="items[0][total]" class="form-control row-total" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm" onclick="addRow()">+ Add Item</button>

          <hr>
          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-2">
              <label><strong>Total Discount (PKR)</strong></label>
              <input type="number" name="discount" id="discountInput" class="form-control" step="any" value="0">
            </div>
            <div class="col-md-6 text-end">
              <label style="font-size:14px"><strong>Total Bill</strong></label>
              <h4 class="text-primary mt-0 mb-1">PKR <span id="netAmountText">0.00</span></h4>
              <input type="hidden" name="net_amount" id="netAmountInput">
            </div>
          </div>
        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Save Invoice</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  // Start from however many rows are already in the table (usually 1)
  let rowIndex = $('#itemTable tbody tr').length || 1;

  $(document).ready(function () {
    // Init select2 on existing controls
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    // Delegate: product change -> load variations + set price
    $(document).on('change', '.product-select', function () {
      const row = $(this).closest('tr');
      const productId = $(this).val();

      // Auto-fill price from product option (fallback price)
      const productPrice = $(this).find(':selected').data('price') || 0;
      row.find('.sale-price').val(productPrice);

      // If we set a variation via barcode, we temporarily saved it on the product select
      const preselectVariationId = $(this).data('preselectVariationId') || null;
      $(this).removeData('preselectVariationId'); // clear flag after using

      if (productId) {
        loadVariations(row, productId, preselectVariationId);
      } else {
        const $variationSelect = row.find('.variation-select');
        $variationSelect.html('<option value="">Select Variation</option>').trigger('change');
      }

      calcRowTotal(row);
    });

    // ✅ Barcode scan/blur → auto-fill product + variation + price + qty
    $(document).on('blur', '.product-code', function () {
      const row = $(this).closest('tr');
      const barcode = $(this).val().trim();
      if (!barcode) return;

      $.ajax({
        url: '/get-product-by-code/' + encodeURIComponent(barcode),
        method: 'GET',
        success: function (res) {
          const $productSelect = row.find('.product-select');
          const $variationSelect = row.find('.variation-select');

          if (!res || !res.success) {
            alert(res.message || 'Product not found');
            resetRow(row);
            return;
          }

          // 🔹 CASE 1: Barcode is a variation
          if (res.type === 'variation' && res.variation) {
            const v = res.variation;

            // set product
            $productSelect.val(v.product_id).trigger('change.select2');

            // set variation directly
            $variationSelect.html(`<option value="${v.id}" selected>${v.sku}</option>`)
              .prop('disabled', false)
              .trigger('change');

            // ✅ update price from variation or fallback product option
            if (v.price) {
              row.find('.sale-price').val(v.price);
            } else {
              const fallbackPrice = $productSelect.find(':selected').data('price') || 0;
              row.find('.sale-price').val(fallbackPrice);
            }

            // default qty = 1
            if (!row.find('.quantity').val()) row.find('.quantity').val(1);

            // recalc totals
            calcRowTotal(row);

            // focus qty
            row.find('.quantity').focus();

            // auto-add next row if this is the last one
            if (row.is(':last-child')) {
              addRow();
              $('#itemTable tbody tr:last .product-code').focus();
            }
            return;
          }

          // 🔹 CASE 2: Barcode is a product
          if (res.type === 'product' && res.product) {
            const p = res.product;

            if ($productSelect.find(`option[value="${p.id}"]`).length) {
              $productSelect.val(p.id).trigger('change.select2');

              // update barcode
              row.find('.product-code').val(p.barcode);
              row.find('input[name*="[barcode]"]').val(p.barcode);

              // ✅ set price from response or option
              if (p.selling_price) {
                row.find('.sale-price').val(p.selling_price);
              } else {
                const fallbackPrice = $productSelect.find(':selected').data('price') || 0;
                row.find('.sale-price').val(fallbackPrice);
              }

              // load variations normally
              loadVariations(row, p.id);

              // open variation dropdown for user
              setTimeout(() => $variationSelect.select2('open'), 300);
            } else {
              alert("Product found but not in dropdown list.");
              resetRow(row);
            }
            return;
          }

          // fallback
          alert('Invalid response. Barcode not matched.');
          resetRow(row);
        },
        error: function () {
          alert('Error fetching product/variation.');
          resetRow(row);
        }
      });
    });

    // 🔹 Utility: clear row to safe state
    function resetRow(row) {
      row.find('.product-code').val('').focus();
      row.find('.product-select').val('').trigger('change.select2');
      row.find('.variation-select').html('<option value="">Select Variation</option>')
        .prop('disabled', false)
        .trigger('change');
      row.find('.sale-price, .quantity, .row-total').val('');
    }

    // Delegate: any price/qty/discount change -> recalc this row
    $(document).on('input', '.sale-price, .quantity, .disc-price', function () {
      calcRowTotal($(this).closest('tr'));
    });

    // Initial totals
    calcTotal();

    // Invoice-level discount -> recalc net
    $(document).on('input', '#discountInput', calcTotal);
  });

  // Create and append a new item row
  function addRow() {
    const idx = rowIndex++;
    const rowHtml = `
      <tr>
        <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code"></td>
        <td>
          <select name="items[${idx}][product_id]" class="form-control select2-js product-select" required>
            <option value="">Select Product</option>
            @foreach($products as $product)
              <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="items[${idx}][variation_id]" class="form-control select2-js variation-select">
            <option value="">Select Variation</option>
          </select>
        </td>
        <td><input type="number" name="items[${idx}][sale_price]" class="form-control sale-price" step="any" required></td>
        <td><input type="number" name="items[${idx}][disc_price]" class="form-control disc-price" step="any" value="0"></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control quantity" step="any" required></td>
        <td><input type="number" name="items[${idx}][total]" class="form-control row-total" readonly></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
      </tr>
    `;
    $('#itemTable tbody').append(rowHtml);

    const $newRow = $('#itemTable tbody tr').last();
    $newRow.find('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    // ✅ focus the new barcode input
    $newRow.find('.product-code').focus();
  }

  function removeRow(btn) {
    $(btn).closest('tr').remove();
    calcTotal();
  }

  // Fetch variations, populate the dropdown, then preselect if given
  function loadVariations(row, productId, preselectVariationId = null) {
    const $variationSelect = row.find('.variation-select');
    $variationSelect.html('<option value="">Loading...</option>');

    $.get(`/product/${productId}/variations`, function (data) {
      let options = '<option value="">Select Variation</option>';
      (data.variation || []).forEach(v => {
        options += `<option value="${v.id}">${v.sku}</option>`;
      });
      $variationSelect.html(options);

      if ($variationSelect.hasClass('select2-hidden-accessible')) {
        $variationSelect.select2('destroy');
      }
      $variationSelect.select2({ width: '100%', dropdownAutoWidth: true });

      if (preselectVariationId) {
        $variationSelect.val(String(preselectVariationId)).trigger('change');
      }
    });
  }

  // Row-level total
  function calcRowTotal(row) {
    const price = parseFloat(row.find('.sale-price').val()) || 0;
    const qty = parseFloat(row.find('.quantity').val()) || 0;
    const discPercent = parseFloat(row.find('.disc-price').val()) || 0;

    const discountedPrice = price - (price * discPercent / 100);
    const total = discountedPrice * qty;

    row.find('.row-total').val(total.toFixed(2));
    calcTotal();
  }

  // Invoice total
  function calcTotal() {
    let total = 0;
    $('.row-total').each(function () {
      total += parseFloat($(this).val()) || 0;
    });

    const invoiceDiscount = parseFloat($('#discountInput').val()) || 0;
    const netAmount = total - invoiceDiscount;

    $('#netAmountText').text(netAmount.toFixed(2));
    $('#netAmountInput').val(netAmount.toFixed(2));
  }
</script>

@endsection
