@extends('layouts.app')

@section('title', 'Purchase Return | Edit Return')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_return.update', $purchaseReturn->id) }}" method="POST">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Return</h2>
        </header>

        <div class="card-body">
          <div class="row mb-3">
            <div class="col-md-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" {{ $purchaseReturn->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ $purchaseReturn->return_date->toDateString() }}" required>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="returnTable">
              <thead>
                <tr>
                  <th>Item Name</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="ReturnTableBody">
                @foreach($purchaseReturn->items as $idx => $item)
                  <tr>
                    <td>
                      <select name="items[{{ $idx }}][item_id]" class="form-control select2-js product-select" onchange="onReturnItemChange(this)">
                        <option value="">Select Item</option>
                        @foreach($products as $product)
                          <option value="{{ $product->id }}" data-barcode="{{ $product->barcode }}" data-unit="{{ $product->measurement_unit }}"
                            {{ $product->id == $item->item_id ? 'selected' : '' }}>
                            {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>

                    <td>
                      <input type="number" name="items[{{ $idx }}][quantity]" class="form-control quantity" step="any" value="{{ $item->quantity }}" onchange="rowTotal({{ $idx }})">
                    </td>

                    <td>
                      <select name="items[{{ $idx }}][unit]" class="form-control unit-select" required>
                        @foreach($units as $unit)
                          <option value="{{ $unit->id }}" {{ $unit->id == $item->unit_id ? 'selected' : '' }}>
                            {{ $unit->name }} ({{ $unit->shortcode }})
                          </option>
                        @endforeach
                      </select>
                    </td>

                    <td>
                      <input type="number" name="items[{{ $idx }}][price]" class="form-control price" step="any" value="{{ $item->price }}" onchange="rowTotal({{ $idx }})">
                    </td>

                    <td>
                      <input type="number" name="items[{{ $idx }}][amount]" class="form-control amount" step="any" value="{{ $item->price * $item->quantity }}" readonly>
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

            <button type="button" class="btn btn-outline-primary" onclick="addReturnRow()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control">{{ $purchaseReturn->remarks }}</textarea>
            </div>

            <div class="col-md-3">
              <label>Total Amount</label>
              <input type="number" id="total_amount" class="form-control" readonly>
              <input type="hidden" name="total_amount" id="total_amount_hidden">
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
  var index = {{ $purchaseReturn->items->count() }};

  // ----------------- Add new row -----------------
  function addReturnRow() {
    let newRow = `
      <tr>
        <td>
          <select name="items[${index}][item_id]" class="form-control select2-js product-select" onchange="onReturnItemChange(this)">
            <option value="">Select Item</option>
            ${products.map(p => `<option value="${p.id}" data-unit="${p.measurement_unit}">${p.name}</option>`).join('')}
          </select>
        </td>
        <td><input type="number" name="items[${index}][quantity]" class="form-control quantity" step="any" onchange="rowTotal(${index})"></td>
        <td>
          <select name="items[${index}][unit]" class="form-control unit-select" required>
            <option value="">-- Select --</option>
            ${units.map(u => `<option value="${u.id}">${u.name} (${u.shortcode})</option>`).join('')}
          </select>
        </td>
        <td><input type="number" name="items[${index}][price]" class="form-control price" step="any" onchange="rowTotal(${index})"></td>
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

  // ----------------- Product change -----------------
  function onReturnItemChange(select) {
    const row = $(select).closest('tr');
    const productId = $(select).val();
    const unitId = $(select).find(':selected').data('unit');
    row.find('select.unit-select').val(unitId).trigger('change');
  }


// ----------------- Reset row -----------------
function resetRow(row) {
  row.find('.product-select').val('').trigger('change.select2');
}

// ----------------- Row totals -----------------
function rowTotal(idx) {
  const row = $('#ReturnTableBody tr').eq(idx);
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

// ----------------- Document ready -----------------
$(document).ready(function(){
  $('.select2-js').select2({ width: '100%' });
  updateTotal();
});
</script>
@endsection
