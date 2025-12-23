@extends('layouts.app')

@section('title', 'Production | Edit Receiving')

@section('content')
  <div class="row">
    <form action="{{ route('production_receiving.update', $receiving->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      @if ($errors->has('error'))
        <strong class="text-danger">{{ $errors->first('error') }}</strong>
      @endif

      <div class="col-12 mb-4">
        <section class="card">
          <header class="card-header">
            <h2 class="card-title">Edit Production Receiving</h2>
          </header>
          <div class="card-body">
            <div class="row mb-4">
              <div class="col-md-2">
                <label>GRN #</label>
                <input type="text" name="grn_no" class="form-control" value="{{ $receiving->grn_no }}" readonly />
              </div>
              <div class="col-md-2">
                <label>Receiving Date</label>
                <input type="date" name="rec_date" class="form-control" value="{{ \Carbon\Carbon::parse($receiving->return_date)->toDateString() }}" required />
              </div>
              <div class="col-md-2">
                <label>Production Order</label>
                <select name="production_id" class="form-control select2-js">
                  <option value="">Select Production</option>
                  @foreach($productions as $prod)
                    <option value="{{ $prod->id }}" {{ $receiving->production_id == $prod->id ? 'selected' : '' }}>
                      {{ $prod->id }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-2">
                <label>Vendor</label>
                <select name="vendor_id" class="form-control select2-js" required>
                    <option value="" disabled>Select Vendor</option>
                    @foreach($accounts as $vendor)
                        <option value="{{ $vendor->id }}" {{ $receiving->vendor_id == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                    @endforeach
                </select>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="col-12 mb-4">
        <section class="card">
          <header class="card-header">
            <h2 class="card-title">Product Details</h2>
          </header>
          <div class="card-body">
            <table class="table table-bordered" id="itemTable">
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Item</th>
                  <th>Variation</th>
                  <th>M. Cost</th>
                  <th>Received</th>
                  <th>Remarks</th>
                  <th>Total</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($receiving->details as $index => $detail)
                <tr>
                  <td>
                    <input type="text" class="form-control product-code" placeholder="Enter Product Code"
                          value="{{ $detail->product->barcode }}" onblur="fetchByCode({{ $index }})">
                  </td>
                  <td>
                    <select name="item_details[{{ $index }}][product_id]" class="form-control select2-js product-select" required>
                      <option value="">Select Item</option>
                      @foreach($products as $item)
                        <option value="{{ $item->id }}"
                          data-mfg-cost="{{ $item->manufacturing_cost }}"
                          data-unit-id="{{ $item->unit_id }}"
                          data-barcode="{{ $item->barcode }}"
                          {{ $item->id == $detail->product_id ? 'selected' : '' }}>
                          {{ $item->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select name="item_details[{{ $index }}][variation_id]" class="form-control select2-js variation-select">
                      <option value="">Select Variation</option>
                      @foreach($detail->product->variations as $variation)
                        <option value="{{ $variation->id }}"
                          {{ $variation->id == $detail->variation_id ? 'selected' : '' }}>
                          {{ $variation->sku }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" class="form-control manufacturing_cost" name="item_details[{{ $index }}][manufacturing_cost]" step="any" value="{{ $detail->manufacturing_cost }}" readonly>
                  </td>
                  <td>
                    <input type="number" class="form-control received-qty" name="item_details[{{ $index }}][received_qty]" step="any" value="{{ $detail->received_qty }}" required>
                  </td>
                  <td>
                    <input type="text" class="form-control" name="item_details[{{ $index }}][remarks]" value="{{ $detail->remarks }}">
                  </td>
                  <td>
                    <input type="number" class="form-control row-total" name="item_details[{{ $index }}][total]" step="any" value="{{ $detail->total }}" readonly>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-success btn-sm mt-2" onclick="addRow()">+ Add Row</button>

            <hr>
            <div class="row">
              <div class="col-md-2">
                <label>Total Pcs</label>
                <input type="text" class="form-control" id="total_pcs" value="{{ $receiving->total_pcs }}" disabled>
                <input type="hidden" name="total_pcs" id="total_pcs_val" value="{{ $receiving->total_pcs }}">
              </div>
              <div class="col-md-2">
                <label>Total Amount</label>
                <input type="text" class="form-control" id="total_amt" value="{{ $receiving->total_amount }}" disabled>
                <input type="hidden" name="total_amt" id="total_amt_val" value="{{ $receiving->total_amount }}">
              </div>
              <div class="col-md-2">
                <label>Conveyance</label>
                <input type="text" class="form-control" name="convance_charges" id="convance_charges" onchange="calcNet()" value="{{ $receiving->convance_charges }}">
              </div>
              <div class="col-md-2">
                <label>Discount</label>
                <input type="text" class="form-control" name="bill_discount" id="bill_discount" onchange="calcNet()" value="{{ $receiving->bill_discount }}">
              </div>
              <div class="col-md-4 text-end">
                <label><strong>Net Amount</strong></label>
                <h4 class="text-primary">PKR <span id="netAmountText">{{ number_format($receiving->net_amount, 2) }}</span></h4>
                <input type="hidden" name="net_amount" id="net_amount" value="{{ $receiving->net_amount }}">
              </div>
            </div>
          </div>
          <footer class="card-footer text-end">
            <a href="{{ route('production_receiving.index') }}" class="btn btn-danger">Discard</a>
            <button type="submit" class="btn btn-primary">Update</button>
          </footer>
        </section>
      </div>
    </form>
  </div>

<script>
  $(document).ready(function () {
  // Init Select2
  $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

  // Bind events to existing rows and load variations for edit mode
  $('#itemTable tbody tr').each(function() {
    bindRowEvents($(this));
  });

  // Delegated: product-code blur -> fetch by code (passes DOM element to function)
  $(document).on('blur', '.product-code', function () {
    fetchByCode(this);
  });

  // Product select change
  $(document).on('change', '.product-select', function () {
    const row = $(this).closest('tr');
    const selectedOption = $(this).find('option:selected');
    const mfgCostInput = row.find('.manufacturing_cost');

    const mfgCost = selectedOption.data('mfg-cost') || 0;
    mfgCostInput.val(parseFloat(mfgCost).toFixed(2));

    if ($(this).val()) {
      const preselect = $(this).data('preselectVariationId') || null;
      $(this).removeData('preselectVariationId');
      loadVariations(row, $(this).val(), preselect);
    } else {
      row.find('.variation-select').html('<option value="">Select Variation</option>');
      if (row.find('.variation-select').hasClass('select2-hidden-accessible')) {
        row.find('.variation-select').select2('destroy');
        row.find('.variation-select').select2({ width: '100%', dropdownAutoWidth: true });
      }
    }

    calculateTotals();
  });

  // Variation change & Qty input & Remove button
  $(document).on('change', '.variation-select', calculateTotals);
  $(document).on('input', '.received-qty', calculateTotals);
  $(document).on('click', '.remove-row-btn', function () {
    if ($('#itemTable tbody tr').length > 1) {
      $(this).closest('tr').remove();
      calculateTotals();
    }
  });

  // Preload variations for rows present in edit form
  $('#itemTable tbody tr').each(function() {
    const row = $(this);
    const productId = row.find('.product-select').val();
    const variationId = row.find('.variation-select').val();
    if (productId) {
      loadVariations(row, productId, variationId);
    }
  });

  // init totals
  calculateTotals();
  });

  /* -----------------------
    Helper functions
    ----------------------- */

  function bindRowEvents(row) {
  row.find('.received-qty').on('input', calculateTotals);
  row.find('.remove-row-btn').on('click', function () {
    if ($('#itemTable tbody tr').length > 1) {
      $(this).closest('tr').remove();
      calculateTotals();
    }
  });
  }

  function addRow() {
  const table = $('#itemTable tbody');
  const newIndex = table.find('tr').length;

  const newRow = $(`
    <tr>
      <td><input type="text" class="form-control product-code" placeholder="Enter Product Code"></td>
      <td>
        <select name="item_details[${newIndex}][product_id]" class="form-control select2-js product-select" required>
          <option value="">Select Item</option>
          @foreach($products as $item)
            <option value="{{ $item->id }}" 
                    data-mfg-cost="{{ $item->manufacturing_cost }}"
                    data-unit-id="{{ $item->unit_id }}"
                    data-barcode="{{ $item->barcode }}">
              {{ $item->name }}
            </option>
          @endforeach
        </select>
      </td>
      <td>
        <select name="item_details[${newIndex}][variation_id]" class="form-control select2-js variation-select">
          <option value="">Select Variation</option>
        </select>
      </td>
      <td><input type="number" name="item_details[${newIndex}][manufacturing_cost]" class="form-control manufacturing_cost" step="any" value="0" readonly></td>
      <td><input type="number" name="item_details[${newIndex}][received_qty]" class="form-control received-qty" step="any" value="0" required></td>
      <td><input type="text" name="item_details[${newIndex}][remarks]" class="form-control"></td>
      <td><input type="number" name="item_details[${newIndex}][total]" class="form-control row-total" step="any" value="0" readonly></td>
      <td><button type="button" class="btn btn-danger btn-sm remove-row-btn"><i class="fas fa-times"></i></button></td>
    </tr>
  `);

  table.append(newRow);

  newRow.find('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

  newRow.find('.product-code').focus();
  bindRowEvents(newRow);
  }

  function loadVariations(row, productId, preselectVariationId = null) {
  const variationSelect = row.find('.variation-select');
  variationSelect.html('<option value="">Loading...</option>').prop('disabled', true);

  $.get(`/product/${productId}/variations`)
    .done(function (data) {
      let options = '<option value="">Select Variation</option>';
      (data.variation || []).forEach(v => {
        options += `<option value="${v.id}">${v.sku}</option>`;
      });

      variationSelect.html(options).prop('disabled', false);

      // (re)init select2 for the variation select
      if (variationSelect.hasClass('select2-hidden-accessible')) {
        variationSelect.select2('destroy');
      }
      variationSelect.select2({ width: '100%', dropdownAutoWidth: true });

      if (preselectVariationId) {
        // ensure value is string to match option values
        variationSelect.val(String(preselectVariationId)).trigger('change');
      }
    })
    .fail(function () {
      variationSelect.html('<option value="">Select Variation</option>').prop('disabled', false);
      if (variationSelect.hasClass('select2-hidden-accessible')) {
        variationSelect.select2('destroy');
        variationSelect.select2({ width: '100%', dropdownAutoWidth: true });
      }
    });
  }

  // Fetch by barcode/code.
  // Accepts either:
  // - numeric row index (server-rendered inline onblur still works), or
  // - DOM element (e.g. this from onblur) — preferred for delegated event handler.
  function fetchByCode(rowIndexOrElement) {
    let row;
    if (typeof rowIndexOrElement === 'number') {
      row = $('#itemTable tbody tr').eq(rowIndexOrElement);
    } else {
      // DOM element (input) or jQuery object
      row = $(rowIndexOrElement).closest('tr');
    }

    const codeInput = row.find('.product-code');
    const enteredCode = codeInput.val().trim();
    if (!enteredCode) return;

    $.get('/get-product-by-code/' + encodeURIComponent(enteredCode))
      .done(function (res) {
        if (!res.success) {
          alert(res.message || 'No product or variation found');
          codeInput.val('').focus();
          return;
        }

        const $productSelect = row.find('.product-select');
        const $variationSelect = row.find('.variation-select');
        const $mCostInput = row.find('.manufacturing_cost');
        const $qtyInput = row.find('.received-qty');

        // Handle variation result
        if (res.type === 'variation') {
          const v = res.variation;
          // set product and ask loadVariations to preselect variation
          $productSelect.data('preselectVariationId', v.id).val(v.product_id).trigger('change');

          // m.cost might come in v or in product; try both keys
          if (v['m.cost'] !== undefined) $mCostInput.val(parseFloat(v['m.cost']).toFixed(2));
          if (v.manufacturing_cost !== undefined) $mCostInput.val(parseFloat(v.manufacturing_cost).toFixed(2));

          // small delay to let loadVariations populate options then focus qty
          setTimeout(function () {
            $qtyInput.focus();
            calculateTotals();
          }, 250);

          return;
        }

        // Handle product-only result
        if (res.type === 'product') {
          const p = res.product;
          $productSelect.val(p.id).trigger('change');

          if (p['m.cost'] !== undefined) $mCostInput.val(parseFloat(p['m.cost']).toFixed(2));
          if (p.manufacturing_cost !== undefined) $mCostInput.val(parseFloat(p.manufacturing_cost).toFixed(2));

          setTimeout(function () {
            if ($variationSelect.find('option').length > 1) {
              $variationSelect.focus();
            } else {
              $qtyInput.focus();
            }
            calculateTotals();
          }, 250);
        }
      })
      .fail(function () {
        alert('Error fetching product details.');
      });
  }

  function calculateTotals() {
  let totalQty = 0, totalAmt = 0;

  $('#itemTable tbody tr').each(function () {
    const qty = parseFloat($(this).find('.received-qty').val()) || 0;
    const cost = parseFloat($(this).find('.manufacturing_cost').val()) || 0;
    const rowTotal = qty * cost;
    $(this).find('.row-total').val(rowTotal.toFixed(2));

    totalQty += qty;
    totalAmt += rowTotal;
  });

  $('#total_pcs').val(totalQty);
  $('#total_pcs_val').val(totalQty);
  $('#total_amt').val(totalAmt.toFixed(2));
  $('#total_amt_val').val(totalAmt.toFixed(2));
  calcNet();
  }

  function calcNet() {
  const total = parseFloat($('#total_amt_val').val()) || 0;
  const conveyance = parseFloat($('#convance_charges').val()) || 0;
  const discount = parseFloat($('#bill_discount').val()) || 0;
  const net = total + conveyance - discount;
  $('#netAmountText').text(net.toFixed(2));
  $('#net_amount').val(net.toFixed(2));
  }
</script>

@endsection
