@extends('layouts.app')

@section('title', 'Edit Stock Transfer')

@section('content')
<div class="row">
  <form action="{{ route('stock_transfer.update', $transfer->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Stock Transfer</h2>
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
            <div class="col-md-3">
              <label>Transfer Date</label>
              <input type="date" name="date" class="form-control" value="{{ date('Y-m-d', strtotime($transfer->date)) }}" required />
            </div>
            <div class="col-md-3">
              <label>From Location</label>
              <select name="from_location_id" class="form-control select2-js" required>
                <option value="">Select From Location</option>
                @foreach($locations as $loc)
                  <option value="{{ $loc->id }}" {{ $transfer->from_location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label>To Location</label>
              <select name="to_location_id" class="form-control select2-js" required>
                <option value="">Select To Location</option>
                @foreach($locations as $loc)
                  <option value="{{ $loc->id }}" {{ $transfer->to_location_id == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label>Remarks</label>
              <input type="text" name="remarks" class="form-control" value="{{ $transfer->remarks }}">
            </div>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Transfer Items</h2>
        </header>
        <div class="card-body">
          <table class="table table-bordered" id="itemTable">
            <thead>
              <tr>
                <th width="15%">Item Code</th>
                <th>Product</th>
                <th>Variation</th>
                <th width="12%">Qty</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($transfer->details as $idx => $item)
              <tr>
                <td><input type="text" class="form-control product-code" placeholder="Scan/Enter Code" value="{{ $item->variation->barcode ?? '' }}"></td>
                <td>
                  <select name="items[{{ $idx }}][product_id]" class="form-control select2-js product-select" required data-preselect-variation-id="{{ $item->variation_id }}">
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" {{ $product->id == $item->product_id ? 'selected' : '' }}>{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[{{ $idx }}][variation_id]" class="form-control select2-js variation-select">
                    <option value="{{ $item->variation_id }}" selected>{{ $item->variation->sku ?? 'Select Variation' }}</option>
                  </select>
                </td>
                <td><input type="number" name="items[{{ $idx }}][quantity]" class="form-control quantity" step="any" value="{{ $item->quantity }}" required></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
              </tr>
              @endforeach
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm" onclick="addRow()">+ Add Item</button>
        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('stock_transfer.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Update Transfer</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  let rowIndex = $('#itemTable tbody tr').length || 1;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

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
          if (!res.success) {
            alert(res.message || 'Not found');
            row.find('.product-code').val('').focus();
            return;
          }

          const $productSelect = row.find('.product-select');
          const $variationSelect = row.find('.variation-select');

          if (res.type === 'variation') {
            const variation = res.variation;

            // ✅ Set product
            $productSelect.val(variation.product_id).trigger('change.select2');

            // ✅ Directly set variation
            $variationSelect.html(`<option value="${variation.id}" selected>${variation.sku}</option>`)
                            .prop('disabled', false)
                            .trigger('change');

            // ✅ Focus Qty field
            row.find('.quantity').focus();
          }

          if (res.type === 'product') {
            const product = res.product;

            // ✅ Set product
            $productSelect.val(product.id).trigger('change.select2');

            // ✅ Load variations normally
            loadVariations(row, product.id);

            // focus on variation after loading
            setTimeout(() => {
              $variationSelect.focus();
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
          // Add new row
          addRow();

          // Focus on new row's barcode
          const $newRow = $('#itemTable tbody tr').last();
          $newRow.find('.product-code').focus();
        } else {
          alert("Please enter quantity first.");
          $(this).focus();
        }
      }
    });

  });

  // 🔹 Add Row
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
        <td><input type="number" name="items[${idx}][quantity]" class="form-control quantity" step="any" required></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
      </tr>
    `;
    $('#itemTable tbody').append(rowHtml);
    const $newRow = $('#itemTable tbody tr').last();
    $newRow.find('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
  }

  // 🔹 Remove Row
  function removeRow(btn) {
    $(btn).closest('tr').remove();
  }

  // 🔹 Load Variations
  function loadVariations(row, productId, preselectVariationId = null) {
    const $variationSelect = row.find('.variation-select');
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

      if (preselectVariationId) {
        $variationSelect.val(String(preselectVariationId)).trigger('change');
      }
    });
  }
</script>

@endsection
