@extends('layouts.app')

@section('title', 'Purchase | Edit Invoice')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_invoices.update', $invoice->id) }}" onkeydown="return event.key != 'Enter';" method="POST" enctype="multipart/form-data">
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
                  <th>S.No</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                @foreach ($invoice->items as $index => $item)                
                <tr>
                  <td class="serial-no">{{ $index + 1 }}</td>
                  <td>
                    <select name="items[{{ $index }}][item_id]" id="item_name{{ $index }}" 
                            class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-unit-id="{{ $product->measurement_unit }}"
                          {{ $item->item_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[{{ $index }}][quantity]" id="pur_qty{{ $index }}" 
                          class="form-control quantity" value="{{ $item->quantity }}" step="any" onchange="rowTotal({{ $index }})">
                  </td>
                  <td>
                    <select name="items[{{ $index }}][unit]" id="unit{{ $index }}" class="form-control select2-js" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" {{ $item->unit == $unit->id ? 'selected' : '' }}>
                          {{ $unit->name }} ({{ $unit->shortcode }})
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="items[{{ $index }}][price]" id="pur_price{{ $index }}" 
                          class="form-control" value="{{ $item->price }}" step="any" onchange="rowTotal({{ $index }})">
                  </td>
                  <td>
                    <input type="number" id="amount{{ $index }}" class="form-control" value="{{ $item->quantity * $item->price }}" step="any" disabled>
                  </td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
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
  var index = {{ $invoice->items->count() }};

  function updateSerialNumbers() {
    $('#Purchase1Table tr').each(function (index) {
      $(this).find('.serial-no').text(index + 1);
    });
  }


  $(document).ready(function () {
    // Initialize select2 for existing rows
    $('#Purchase1Table .select2-js').select2({ width: '100%', dropdownAutoWidth: true });

    tableTotal(); // calculate totals for pre-filled data

    // 🔹 Manual Product selection flow
    $(document).on('change', '.product-select', function () {
      const row = $(this).closest('tr');
      const productId = $(this).val();
    });

    updateSerialNumbers();

  });

  // 🔹 Item name change handler with Duplicate Check
  function onItemNameChange(selectElement) {
      const itemId = selectElement.value;
      if (!itemId) return; // Ignore if "Select Item" is chosen

      // 1. Check for Duplicates
      let isDuplicate = false;
      $('.product-select').not(selectElement).each(function() {
          if ($(this).val() == itemId) {
              isDuplicate = true;
              return false; // Break loop
          }
      });

      if (isDuplicate) {
          alert("This item is already added to the list. Please increase the quantity of the existing row instead.");
          
          // Reset the selection
          $(selectElement).val('').trigger('change.select2');
          return;
      }

      // 2. Original logic to set the unit
      const row = selectElement.closest('tr');
      const selectedOption = selectElement.options[selectElement.selectedIndex];
      const unitId = selectedOption.getAttribute('data-unit-id');

      // Extracting the ID/Key from the element ID (works for both 0, 1, 2 and 171...)
      const idMatch = selectElement.id.match(/\d+$/);
      if (!idMatch) return;
      const currentRowIndex = idMatch[0];

      const unitSelector = $(`#unit${currentRowIndex}`);
      unitSelector.val(String(unitId)).trigger('change.select2');
  }

  // 🔹 Remove row
  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
      updateSerialNumbers();
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
      // Calculate a unique key based on current time to avoid any ID clashing
      let newKey = Date.now(); 
      
      let newRow = `
        <tr>
          <td class="serial-no"></td>
          <td>
            <select name="items[${newKey}][item_id]" id="item_name${newKey}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
              <option value="">Select Item</option>
              ${products.map(product => 
                `<option value="${product.id}" data-unit-id="${product.measurement_unit}">
                  ${product.name}
                </option>`).join('')}
            </select>
          </td>
          <td><input type="number" name="items[${newKey}][quantity]" id="pur_qty${newKey}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${newKey})"></td>
          <td>
            <select name="items[${newKey}][unit]" id="unit${newKey}" class="form-control select2-js" required>
              <option value="">-- Select --</option>
              @foreach ($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="items[${newKey}][price]" id="pur_price${newKey}" class="form-control" value="0" step="any" onchange="rowTotal(${newKey})"></td>
          <td><input type="number" id="amount${newKey}" class="form-control" value="0" step="any" disabled></td>
          <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
          </td>
        </tr>
      `;
      table.append(newRow);
      $(`#item_name${newKey}, #unit${newKey}`).select2({ width: '100%' });
      updateSerialNumbers();
      index++; // Increment for the next row  
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
    let total = 0;
    let qty = 0;

    $('#Purchase1Table tr').each(function () {

      // ✅ Amount
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;

      // ✅ Quantity (FIXED)
      qty += parseFloat($(this).find('input.quantity').val()) || 0;

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
    let net = (total).toFixed(2);
    $('#netTotal').text(formatNumberWithCommas(net));
    $('#net_amount').val(net);
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

</script>



@endsection
