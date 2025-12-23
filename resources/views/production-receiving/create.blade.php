@extends('layouts.app')

@section('title', 'Production | Order Receiving')

@section('content')
<div class="row">
  <form action="{{ route('production_receiving.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if ($errors->has('error'))
      <strong class="text-danger">{{ $errors->first('error') }}</strong>
    @endif

    <div class="col-12 mb-4">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Order Receiving</h2>
        </header>
        <div class="card-body">
          <div class="row mb-4">
            <div class="col-md-2">
              <label>GRN #</label>
              <input type="text" name="grn_no" class="form-control" readonly />
            </div>
            <div class="col-md-2">
              <label>Receiving Date</label>
              <input type="date" name="rec_date" class="form-control" value="{{ date('Y-m-d') }}" required />
            </div>
            <div class="col-md-2">
              <label>Production Order</label>
              <select name="production_id" class="form-control select2-js">
                  <option value="" disabled {{ empty($selectedProductionId) ? 'selected' : '' }}>Select Production</option>
                  @foreach($productions as $prod)
                      <option value="{{ $prod->id }}"
                          {{ $selectedProductionId == $prod->id ? 'selected' : '' }}>
                          {{ $prod->id }}
                      </option>
                  @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                  <option value="">Select Vendor</option>
                  @foreach($accounts as $vendor)
                    <option value="{{ $vendor->id }}"> {{ $vendor->name }}</option>
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
                <th width="15%">Item Code</th>
                <th>Item</th>
                <th>Variation</th>
                <th width="10%">M. Cost</th>
                <th width="10%">Received</th>
                <th>Remarks</th>
                <th width="10%">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Enter Product Code"></td>
                <td>
                  <select name="item_details[0][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Item</option>
                    @foreach($products as $item)
                      <option value="{{ $item->id }}" 
                              data-mfg-cost="{{ $item->manufacturing_cost }}"
                              data-barcode="{{ $item->barcode }}">
                        {{ $item->name }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="item_details[0][variation_id]" class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                  </select>
                </td>
                <td><input type="number" class="form-control manufacturing_cost" name="item_details[0][manufacturing_cost]" step="any" value="0"></td>
                <td><input type="number" class="form-control received-qty" name="item_details[0][received_qty]" step="any" value="0" required></td>
                <td><input type="text" class="form-control" name="item_details[0][remarks]"></td>
                <td><input type="number" class="form-control row-total" name="item_details[0][total]" step="any" value="0" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row-btn"><i class="fas fa-times"></i></button></td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm mt-2" id="addRowBtn">+ Add Row</button>

          <hr>
          <div class="row">
            <div class="col-md-2">
              <label>Total Pcs</label>
              <input type="text" class="form-control" id="total_pcs" disabled>
              <input type="hidden" id="total_pcs_val" name="total_pcs">
            </div>
            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" class="form-control" id="total_amt" disabled>
            </div>
            <div class="col-md-2">
              <label>Conveyance</label>
              <input type="text" class="form-control" name="convance_charges" id="convance_charges" value="0">
            </div>
            <div class="col-md-2">
              <label>Discount</label>
              <input type="text" class="form-control" name="bill_discount" id="bill_discount" value="0">
            </div>
            <div class="col-md-4 text-end">
              <label><strong>Net Amount</strong></label>
              <h4 class="text-primary">PKR <span id="netAmountText">0.00</span></h4>
            </div>
          </div>
        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('production_receiving.index') }}" class="btn btn-danger">Discard</a>
          <button type="submit" class="btn btn-primary">Receive</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  $(document).ready(function () {

    // Initialize Select2
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    // 🔹 Manual Product selection
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

    // 🔹 Barcode scanning
    $(document).on('blur', '.product-code', function () {
        const row = $(this).closest('tr');
        const barcode = $(this).val().trim();
        if (!barcode) return;

        $.ajax({
            url: '/get-product-by-code/' + encodeURIComponent(barcode),
            method: 'GET',
            success: function (res) {

                if (!res.success) {
                    alert(res.message || 'No product or variation found');
                    row.find('.product-code').val('').focus();
                    return;
                }

                const $productSelect = row.find('.product-select');
                const $variationSelect = row.find('.variation-select');
                const $mCostInput = row.find('.manufacturing_cost');
                const $qtyInput = row.find('.received-qty');

                if (res.type === 'variation') {
                    const v = res.variation;

                    // Set product
                    $productSelect.val(v.product_id).trigger('change');

                    // Load variations and preselect
                    loadVariations(row, v.product_id, v.id);

                    // Set manufacturing cost
                    if(v['m.cost'] !== undefined) $mCostInput.val(parseFloat(v['m.cost']).toFixed(2));

                    // Focus qty
                    setTimeout(() => $qtyInput.focus(), 200);

                    recalcRow(row);
                    recalcSummary();
                    return;
                }

                if (res.type === 'product') {
                    const p = res.product;

                    // Set product
                    $productSelect.val(p.id).trigger('change');

                    // Load variations (if any)
                    loadVariations(row, p.id);

                    // Set manufacturing cost
                    if(p['m.cost'] !== undefined) $mCostInput.val(parseFloat(p['m.cost']).toFixed(2));

                    // Focus variation if exists else qty
                    setTimeout(() => {
                        if ($variationSelect.find('option').length > 1) {
                            $variationSelect.focus();
                        } else {
                            $qtyInput.focus();
                        }
                    }, 200);

                    recalcRow(row);
                    recalcSummary();
                }
            },
            error: function () {
                alert('Error fetching product details.');
            }
        });
    });

    // 🔹 Recalc row on quantity input
    $(document).on('input', '.received-qty', function () {
        const row = $(this).closest('tr');
        recalcRow(row);
        recalcSummary();
    });

    // 🔹 Auto-add row on Enter key
    $(document).on('keypress', '.received-qty', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            const qty = $(this).val().trim();
            if (qty !== '') {
                addRow();
                $('#itemTable tbody tr').last().find('.product-code').focus();
            } else {
                alert('Enter quantity first');
                $(this).focus();
            }
        }
    });

    // 🔹 Remove row button
    $(document).on('click', '.remove-row-btn', function () {
        $(this).closest('tr').remove();
        recalcSummary();
    });

    // 🔹 Add row button
    $('#addRowBtn').on('click', addRow);
});

// 🔹 Add new row
function addRow() {
    const rowCount = $('#itemTable tbody tr').length;
    const protoOptions = $('#itemTable tbody tr:first .product-select').html() || '<option value="">Select Product</option>';

    const $newRow = $(`
        <tr>
            <td><input type="text" class="form-control product-code" placeholder="Enter Product Code"></td>
            <td>
                <select name="item_details[${rowCount}][product_id]" class="form-control select2-js product-select" required>
                    ${protoOptions}
                </select>
            </td>
            <td>
                <select name="item_details[${rowCount}][variation_id]" class="form-control select2-js variation-select">
                    <option value="">Select Variation</option>
                </select>
            </td>
            <td><input type="number" class="form-control manufacturing_cost" name="item_details[${rowCount}][manufacturing_cost]" step="any" value="0"></td>
            <td><input type="number" class="form-control received-qty" name="item_details[${rowCount}][received_qty]" step="any" value="0" required></td>
            <td><input type="text" class="form-control" name="item_details[${rowCount}][remarks]"></td>
            <td><input type="number" class="form-control row-total" name="item_details[${rowCount}][total]" step="any" value="0" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row-btn"><i class="fas fa-times"></i></button></td>
        </tr>
    `);

    $('#itemTable tbody').append($newRow);
    $newRow.find('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

}

// 🔹 Load variations for a product + always set product manufacturing cost
function loadVariations(row, productId, preselectVariationId = null) {
    const $variationSelect = row.find('.variation-select');
    const $mCostInput = row.find('.manufacturing_cost');

    $variationSelect.html('<option value="">Loading...</option>').prop('disabled', false);

    $.get(`/product/${productId}/variations`, function (data) {
        let options = '<option value="">Select Variation</option>';

        (data.variation || []).forEach(v => {
            options += `<option value="${v.id}">${v.sku}</option>`;
        });

        $variationSelect.html(options).prop('disabled', false);

        if ($variationSelect.hasClass('select2-hidden-accessible')) {
            $variationSelect.select2('destroy');
        }
        $variationSelect.select2({ width: '100%', dropdownAutoWidth: true });

        // 🔹 Always use product's manufacturing cost
        if (data.product && data.product.manufacturing_cost !== undefined) {
            $mCostInput.val(parseFloat(data.product.manufacturing_cost).toFixed(2));
        }

        // 🔹 Preselect variation if provided
        if (preselectVariationId) {
            $variationSelect.val(String(preselectVariationId)).trigger('change');
        }

        recalcRow(row);
        recalcSummary();
    });
}

// 🔹 Recalculate row total
function recalcRow(row) {
    const qty = parseFloat(row.find('.received-qty').val()) || 0;
    const cost = parseFloat(row.find('.manufacturing_cost').val()) || 0;
    row.find('.row-total').val((qty * cost).toFixed(2));
}

// 🔹 Recalculate summary totals
function recalcSummary() {
    let totalPcs = 0, totalAmt = 0;
    $('#itemTable tbody tr').each(function () {
        const qty = parseFloat($(this).find('.received-qty').val()) || 0;
        const total = parseFloat($(this).find('.row-total').val()) || 0;
        totalPcs += qty;
        totalAmt += total;
    });

    $('#total_pcs').val(totalPcs);
    $('#total_pcs_val').val(totalPcs);
    $('#total_amt').val(totalAmt.toFixed(2));

    const conv = parseFloat($('#convance_charges').val()) || 0;
    const disc = parseFloat($('#bill_discount').val()) || 0;
    const net = totalAmt + conv - disc;
    $('#netAmountText').text(net.toFixed(2));

}

</script>

@endsection
