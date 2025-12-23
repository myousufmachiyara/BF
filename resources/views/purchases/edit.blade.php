@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Invoice</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="{{ count($invoice->items) }}">

            <div class="col-md-2 mb-3">
              <label>Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="{{ $invoice->invoice_date }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $invoice->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" class="form-control" value="{{ $invoice->payment_terms }}">
            </div>

            <div class="col-md-1 mb-3">
              <label>Bill #</label>
              <input type="text" name="bill_no" class="form-control" value="{{ $invoice->bill_no }}">
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control" value="{{ $invoice->ref_no }}">
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="purchaseTable">
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Variation</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                @foreach ($invoice->items as $key => $item)
                <tr>
                  <td>
                    <input type="text" name="items[{{ $key }}][item_code]" id="item_cod{{ $key+1 }}" 
                           class="form-control product-code" value="{{ $item->barcode }}">
                  </td>

                  <td>
                    <select name="items[{{ $key }}][item_id]" id="item_name{{ $key+1 }}" 
                            class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}" 
                                data-unit-id="{{ $product->measurement_unit }}"
                                {{ $item->item_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td>
                    <select name="items[{{ $key }}][variation_id]" class="form-control select2-js variation-select">
                      <option value="">Select Variation</option>
                      @foreach ($item->product->variations as $variation)
                        <option value="{{ $variation->id }}" {{ $item->variation_id == $variation->id ? 'selected' : '' }}>
                          {{ $variation->sku }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td>
                    <input type="number" name="items[{{ $key }}][quantity]" id="pur_qty{{ $key+1 }}" 
                           class="form-control quantity" value="{{ $item->quantity }}" step="any" onchange="rowTotal({{ $key+1 }})">
                  </td>

                  <td>
                    <select name="items[{{ $key }}][unit]" id="unit{{ $key+1 }}" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ $item->unit == $unit->id ? 'selected' : '' }}>
                          {{ $unit->name }} ({{ $unit->shortcode }})
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td>
                    <input type="number" name="items[{{ $key }}][price]" id="pur_price{{ $key+1 }}" 
                           class="form-control" value="{{ $item->price }}" step="any" onchange="rowTotal({{ $key+1 }})">
                  </td>

                  <td>
                    <input type="number" id="amount{{ $key+1 }}" class="form-control" value="{{ $item->quantity * $item->price }}" step="any" disabled>
                  </td>

                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                    <input type="hidden" name="items[{{ $key }}][barcode]" id="barcode{{ $key+1 }}" value="{{ $item->barcode }}">
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" value="{{ $invoice->total_amount }}" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show" value="{{ $invoice->total_amount }}">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" value="{{ $invoice->total_quantity }}" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show" value="{{ $invoice->total_quantity }}">
            </div>
            <div class="col-md-2">
              <label>Convance Charges</label>
              <input type="number" name="convance_charges" id="convance_charges" class="form-control" value="{{ $invoice->convance_charges }}" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Labour Charges</label>
              <input type="number" name="labour_charges" id="labour_charges" class="form-control" value="{{ $invoice->labour_charges }}" onchange="netTotal()">
            </div>
            <div class="col-md-2">
              <label>Bill Discount</label>
              <input type="number" name="bill_discount" id="bill_discount" class="form-control" value="{{ $invoice->bill_discount }}" onchange="netTotal()">
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">{{ number_format($invoice->net_amount,2) }}</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount" value="{{ $invoice->net_amount }}">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Update Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>
<script>
  var products = @json($products);

  // 🔹 Set index to start after existing rows
  var index = $('#Purchase1Table tr').length + 1;

  $(document).ready(function () {
    // Initialize select2 for existing rows
    $('#Purchase1Table .select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    // Pre-fill variations for existing rows
    $('#Purchase1Table tr').each(function () {
        const row = $(this);
        const productId = row.find('.product-select').val();
        const variationId = row.find('.variation-select').val();
        if (productId) {
            loadVariations(row, productId, variationId);
        }
    });

    tableTotal(); // calculate totals for pre-filled data

    // 🔹 Manual Product selection flow
    $(document).on('change', '.product-select', function () {
      const row = $(this).closest('tr');
      const productId = $(this).val();
      const preselectVariationId = $(this).data('preselectVariationId') || null;
      $(this).removeData('preselectVariationId');

      if (productId) {
        loadVariations(row, productId, preselectVariationId);
      } else {
        row.find('.variation-select')
          .html('<option value="">Select Variation</option>')
          .prop('disabled', false)
          .trigger('change');
      }
    });

    // 🔹 Barcode scanning flow
    $(document).on('blur', '.product-code', function () {
      const row = $(this).closest('tr');
      const barcode = $(this).val().trim();
      if (!barcode) return;

      $.ajax({
        url: '/get-product-by-code/' + encodeURIComponent(barcode),
        method: 'GET',
        success: function (res) {
          if (!res || !res.success) {
            alert(res.message || 'Product not found');
            row.find('.product-code').val('').focus();
            row.find('.product-select').val('').trigger('change.select2');
            row.find('.variation-select').html('<option value="">Select Variation</option>').prop('disabled', false).trigger('change');
            return;
          }

          const $productSelect = row.find('.product-select');
          const $variationSelect = row.find('.variation-select');

          if (res.type === 'variation') {
            const variation = res.variation;
            $productSelect.val(variation.product_id).trigger('change.select2');
            $variationSelect.html(`<option value="${variation.id}" selected>${variation.sku}</option>`)
                            .prop('disabled', false)
                            .trigger('change');
            row.find('.quantity').focus();
          } else if (res.type === 'product') {
            const product = res.product;
            $productSelect.val(product.id).trigger('change.select2');
            loadVariations(row, product.id);
            setTimeout(() => {
              $variationSelect.select2('open');
            }, 300);
          }
        },
        error: function () {
          alert('Error fetching product details.');
        }
      });
    });

    // 🔹 POS: Auto-add row when user presses Enter on Qty
    $(document).on('keypress', '.quantity', function (e) {
      if (e.which === 13) { // Enter key
        e.preventDefault();
        const row = $(this).closest('tr');
        const qty = $(this).val().trim();

        if (qty !== '') {
          addNewRow(); // Add new row
          const $newRow = $('#Purchase1Table tbody tr').last();
          $newRow.find('.product-code').focus();
        } else {
          alert("Please enter quantity first.");
          $(this).focus();
        }
      }
    });
  });

  // 🔹 Item name change handler
  function onItemNameChange(selectElement) {
    const row = selectElement.closest('tr');
    const selectedOption = selectElement.options[selectElement.selectedIndex];

    const unitId = selectedOption.getAttribute('data-unit-id');
    const barcode = selectedOption.getAttribute('data-barcode');

    const idMatch = selectElement.id.match(/\d+$/);
    if (!idMatch) return;
    const index = idMatch[0];

    document.getElementById(`item_cod${index}`).value = barcode;
    document.getElementById(`barcode${index}`).value = barcode;

    const unitSelector = $(`#unit${index}`);
    unitSelector.val(String(unitId)).trigger('change.select2');
  }

  // 🔹 Remove row
  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
    }
  }

  // 🔹 Add new row button
  function addNewRow_btn() {
    addNewRow();
    $('#item_cod' + (index - 1)).focus();
  }

  // 🔹 Add new row
  function addNewRow() {
    let table = $('#Purchase1Table');
    let rowIndex = index - 1;

    let newRow = `
      <tr>
        <td><input type="text" name="items[${rowIndex}][item_code]" id="item_cod${index}" class="form-control product-code"></td>

        <td>
          <select name="items[${rowIndex}][item_id]" id="item_name${index}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(product => 
              `<option value="${product.id}" data-barcode="${product.barcode}" data-unit-id="${product.measurement_unit}">
                ${product.name}
              </option>`).join('')}
          </select>
        </td>

        <td>
          <select name="items[${rowIndex}][variation_id]" class="form-control select2-js variation-select">
            <option value="">Select Variation</option>
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][quantity]" id="pur_qty${index}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${index})"></td>

        <td>
          <select name="items[${rowIndex}][unit]" id="unit${index}" class="form-control" required>
            <option value="">-- Select --</option>
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
            @endforeach
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][price]" id="pur_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
        <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
          <input type="hidden" name="items[${rowIndex}][barcode]" id="barcode${index}">
        </td>
      </tr>
    `;
    table.append(newRow);
    $('#itemCount').val(index);
    $(`#item_name${index}`).select2();
    $(`#unit${index}`).select2();
    index++;
  }

  // 🔹 Row total
  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty' + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  // 🔹 Table total
  function tableTotal() {
    let total = 0, qty = 0;
    $('#Purchase1Table tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      qty += parseFloat($(this).find('input[name="quantity[]"]').val()) || 0;
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    netTotal();
  }

  // 🔹 Net total
  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    let conv = parseFloat($('#convance_charges').val()) || 0;
    let labour = parseFloat($('#labour_charges').val()) || 0;
    let discount = parseFloat($('#bill_discount').val()) || 0;
    let net = (total + conv + labour - discount).toFixed(2);
    $('#netTotal').text(formatNumberWithCommas(net));
    $('#net_amount').val(net);
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // 🔹 Load Variations
  function loadVariations(row, productId, preselectVariationId = null) {
    const $variationSelect = row.find('.variation-select');
    $variationSelect.html('<option>Loading...</option>').prop('disabled', true);

    $.get(`/product/${productId}/variations`, function (data) {
      let options = '<option value="" disabled selected>Select Variation</option>';

      if ((data.variation || []).length > 0) {
        data.variation.forEach(v => {
          options += `<option value="${v.id}">${v.sku}</option>`;
        });
        $variationSelect.prop('disabled', false);
      } else {
        // ✅ Only placeholder, stays disabled
        options = '<option value="" disabled selected>No Variations Available</option>';
        $variationSelect.prop('disabled', true);
      }

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

</script>



@endsection
