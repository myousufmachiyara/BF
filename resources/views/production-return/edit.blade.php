@extends('layouts.app')

@section('title', 'Production Return | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('production_return.update', $return->id) }}" method="POST">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Production Return</h2>
        </header>

        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Production Unit (Vendor)</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Production Unit</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $return->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ \Carbon\Carbon::parse($return->return_date)->toDateString() }}" required>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="returnTable">
              <thead>
                <tr>
                  <th>Barcode</th>
                  <th>Item Name</th>
                  <th>Variation</th>
                  <th>Production Order #</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Rate</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="ReturnTableBody">
                @foreach ($return->items as $i => $item)
                <tr>
                  <td>
                    <input type="text" name="items[{{ $i }}][barcode]" 
                           value="{{ $item->product->barcode }}" 
                           class="form-control product-code">
                  </td>

                  <td>
                    <select name="items[{{ $i }}][item_id]" 
                            class="form-control select2-js product-select"
                            onchange="onReturnItemChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}"
                          data-barcode="{{ $product->barcode }}"
                          data-unit="{{ $product->measurement_unit }}"
                          {{ $item->product_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td>
                    <select name="items[{{ $i }}][variation_id]" 
                            class="form-control select2-js variation-select" 
                            {{ $item->variation_id ? '' : 'disabled' }}>
                      <option value="">Select Variation</option>
                      @if($item->product->variations->count())
                        @foreach($item->product->variations as $var)
                          <option value="{{ $var->id }}"
                            {{ $item->variation_id == $var->id ? 'selected' : '' }}>
                            {{ $var->sku }}
                          </option>
                        @endforeach
                      @endif
                    </select>
                  </td>

                  <td>
                    <select name="items[{{ $i }}][production_id]" 
                            class="form-control production-select">
                      <option value="">Select Production Order</option>
                      <option value="{{ $item->production_id }}">
                        {{ $item->production_id }}
                      </option>
                    </select>
                  </td>

                  <td><input type="number" name="items[{{ $i }}][quantity]" 
                             value="{{ $item->quantity }}" 
                             class="form-control quantity" step="any" onchange="rowTotal(this)"></td>

                  <td>
                    <select name="items[{{ $i }}][unit]" class="form-control unit-select" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ $item->unit_id == $unit->id ? 'selected' : '' }}>
                          {{ $unit->name }} ({{ $unit->shortcode }})
                        </option>
                      @endforeach
                    </select>
                  </td>

                  <td><input type="number" name="items[{{ $i }}][price]" 
                             value="{{ $item->price }}" 
                             class="form-control price" step="any" onchange="rowTotal(this)" required></td>
                  <td><input type="number" name="items[{{ $i }}][amount]" 
                             value="{{ $item->price * $item->quantity }}" 
                             class="form-control amount" step="any" readonly></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>

            <button type="button" class="btn btn-outline-primary" onclick="addReturnRow()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $return->remarks }}</textarea>
            </div>

            <div class="col-md-3">
              <label>Total Amount</label>
              <input type="number" id="total_amount" class="form-control" value="{{ $return->total_amount }}" readonly>
              <input type="hidden" name="total_amount" id="total_amount_hidden" value="{{ $return->total_amount }}">
            </div>

            <div class="col-md-3">
              <label>Net Amount</label>
              <input type="number" id="net_amount" class="form-control" value="{{ $return->net_amount }}" readonly>
              <input type="hidden" name="net_amount_hidden" value="{{ $return->net_amount }}">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var units = @json($units);
  var index = 1;

  function addReturnRow() {
    let newRow = `
      <tr>
        <td><input type="text" name="items[${index}][barcode]" class="form-control product-code"></td>

        <td>
          <select name="items[${index}][item_id]" class="form-control select2-js product-select" onchange="onReturnItemChange(this)">
            <option value="">Select Item</option>
            ${products.map(p => `<option value="${p.id}" data-barcode="${p.barcode}" data-unit="${p.measurement_unit}">${p.name}</option>`).join('')}
          </select>
        </td>

        <td>
          <select name="items[${index}][variation_id]" class="form-control select2-js variation-select" disabled>
            <option value="">Select Variation</option>
          </select>
        </td>

        <td>
          <select name="items[${index}][production_id]" class="form-control production-select">
            <option value="">Select Production Order</option>
          </select>
        </td>

        <td><input type="number" name="items[${index}][quantity]" class="form-control quantity" step="any" onchange="rowTotal(this)"></td>

        <td>
          <select name="items[${index}][unit]" class="form-control unit-select" required>
            <option value="">-- Select --</option>
            ${units.map(u => `<option value="${u.id}">${u.name} (${u.shortcode})</option>`).join('')}
          </select>
        </td>

        <td><input type="number" name="items[${index}][price]" class="form-control price" step="any" onchange="rowTotal(this)" required></td>
        <td><input type="number" name="items[${index}][amount]" class="form-control amount" step="any" readonly></td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
        </td>
      </tr>
    `;
    $('#ReturnTableBody').append(newRow);
    $('.select2-js').select2({ width: '100%' });
    index++;
  }

  function onReturnItemChange(select) {
    const row = $(select).closest('tr');
    const productId = $(select).val();
    const barcode = $(select).find(':selected').data('barcode');
    const unitId = $(select).find(':selected').data('unit');

    row.find('input.product-code').val(barcode);
    row.find('select.unit-select').val(unitId).trigger('change');

    loadVariations(row, productId, function(hasVariations) {
      if (!hasVariations) {
        loadProductions(row, productId);
      }
    });
  }

  function loadVariations(row, productId, callback) {
    const $variationSelect = row.find('.variation-select');
    $variationSelect.html('<option>Loading...</option>').prop('disabled', true);

    $.get(`/product/${productId}/variations`, function(data){
      if (data.length > 0) {
        let options = '<option value="">Select Variation</option>';
        data.forEach(v => {
          options += `<option value="${v.id}">${v.sku}</option>`;
        });
        $variationSelect.html(options).prop('disabled', false).select2({ width: '100%' });
        if (typeof callback === 'function') callback(true);
      } else {
        $variationSelect.html('<option value="">No Variations</option>').prop('disabled', true);
        if (typeof callback === 'function') callback(false);
      }
    }).fail(() => {
      $variationSelect.html('<option value="">Error loading</option>').prop('disabled', true);
      if (typeof callback === 'function') callback(false);
    });
  }

  function loadProductions(row, productId) {
    const $prodSelect = row.find('.production-select');
    const $priceInput = row.find('.price');

    // Get variation id (if dropdown exists and enabled)
    let variationId = row.find('.variation-select').val();
    if (!variationId || variationId === "") {
      variationId = null; // explicitly request null case
    }

    $prodSelect.html('<option>Loading...</option>');

    $.get(`/product/${productId}/productions`, { variation_id: variationId }, function(data){
      let options = '<option value="">Select Production Order</option>';
      data.forEach(p => {
        options += `<option value="${p.id}" data-rate="${p.rate}">#${p.id}</option>`;
      });
      $prodSelect.html(options);
      $priceInput.val('');
    }).fail(() => {
      alert('Failed to load production orders.');
      $prodSelect.html('<option value="">Select Production Order</option>');
      $priceInput.val('');
    });
  }

  $(document).on('change', '.variation-select', function() {
    const row = $(this).closest('tr');
    const productId = row.find('.product-select').val();
    if (productId) {
      loadProductions(row, productId);
    }
  });

  $(document).on('change', '.production-select', function() {
    const $row = $(this).closest('tr');
    const rate = $(this).find(':selected').data('rate') || 0;
    $row.find('.price').val(rate).trigger('change');
  });

  function rowTotal(el) {
    const row = $(el).closest('tr');
    const qty = parseFloat(row.find('input.quantity').val()) || 0;
    const price = parseFloat(row.find('input.price').val()) || 0;
    row.find('input.amount').val((qty * price).toFixed(2));
    updateTotal();
  }

  function updateTotal() {
    let total = 0;
    $('#ReturnTableBody tr').each(function(){
      total += parseFloat($(this).find('input.amount').val()) || 0;
    });
    $('#total_amount, #net_amount').val(total.toFixed(2));
    $('#total_amount_hidden, input[name="net_amount_hidden"]').val(total.toFixed(2));
  }

  function removeRow(button) {
    $(button).closest('tr').remove();
    updateTotal();
  }

  $(document).ready(function(){
    $('.select2-js').select2({ width: '100%' });

    $('select[name="vendor_id"]').on('change', function() {
      $('#ReturnTableBody tr').each(function(){
        const productId = $(this).find('.product-select').val();
        if (productId) loadProductions($(this), productId);
      });
    });
  });
</script>
@endsection
