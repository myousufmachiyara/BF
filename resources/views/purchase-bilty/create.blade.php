@extends('layouts.app')

@section('title', 'Purchase | New Bilty')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_bilty.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Bilty</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="1">

            <div class="col-md-2 mb-3">
              <label>Date</label>
              <input type="date" name="bilty_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-2 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2 mb-3">
              <label>Ref.</label>
              <input type="text" name="ref_no" class="form-control">
            </div>

            <div class="col-md-2 mb-3">
              <label>Purchase #</labels>
              <div class="input-group">
                <select name="bilty_purchase_id" id="bilty_purchase_id" class="form-control select2-js">
                  <option value="">Select Purchase #</option>
                  @foreach ($purchaseInvoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_no }}</option>
                  @endforeach
                </select>
                <button type="button" class="btn btn-info" onclick="fetchInvoiceProducts()">
                  <i class="fas fa-sync"></i>
                </button>
              </div>
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Est. Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="BiltyItemTable">
                <tr>
                  <td>
                    <select name="items[0][item_id]" id="item_name1" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-unit-id="{{ $product->measurement_unit }}" data-bilty-charges="{{ $product->bilty_charges }}">
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>                

                  <td><input type="number" name="items[0][quantity]" id="bilty_qty1" class="form-control quantity" value="0" step="any" onchange="rowTotal(1)"></td>

                  <td>
                    <select name="items[0][unit]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>

                  <td><input type="number" name="items[0][price]" id="bilty_price1" class="form-control" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td><input type="number" id="amount1" class="form-control" value="0" step="any" disabled></td>
                  <td>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Estimated Amount</label>
              <input type="number" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="number" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>

            <div class="col-md-2">
              <label>Bilty Amount</label>
              <input type="number" name="bilty_amount" id="payable_amount" class="form-control" required>
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Net Amount: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"> <i class="fas fa-save"></i> Save Invoice</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = 2;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
  });

  // 🔹 Keep all your existing functions exactly as they are
  function onItemNameChange(selectElement) {
      const row = selectElement.closest('tr');
      const selectedOption = selectElement.options[selectElement.selectedIndex];

      const itemId = selectedOption.value;
      const unitId = selectedOption.getAttribute('data-unit-id');
      const biltyCharges = selectedOption.getAttribute('data-bilty-charges') || 0;

      // Extract the numeric index from the select's id
      const idMatch = selectElement.id.match(/\d+$/);
      if (!idMatch) return;

      const index = idMatch[0];

      // Set the unit select value
      const unitSelector = $(`#unit${index}`);
      unitSelector.val(String(unitId)).trigger('change.select2');

      // Set the bilty price input value
      const biltyPriceInput = $(`#bilty_price${index}`);
      biltyPriceInput.val(parseFloat(biltyCharges).toFixed(2));

      // Optional: recalculate row total if you have such function
      if (typeof rowTotal === "function") {
          rowTotal(index);
      }
  }

  function removeRow(button) {
    let rows = $('#BiltyItemTable tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
    }
  }

  function addNewRow_btn() {
    addNewRow();
    $('#item_cod' + (index - 1)).focus();
  }

  function addNewRow() {
    let table = $('#BiltyItemTable');
    let rowIndex = index - 1;

    let newRow = `
      <tr>
        <td>
          <select name="items[${rowIndex}][item_id]" id="item_name${index}" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(product => 
              `<option value="${product.id}" data-unit-id="${product.measurement_unit}" data-bilty-charges="${product.bilty_charges}">
                ${product.name}
              </option>`).join('')}
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][quantity]" id="bilty_qty${index}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${index})"></td>

        <td>
          <select name="items[${rowIndex}][unit]" id="unit${index}" class="form-control" required>
            <option value="">-- Select --</option>
            @foreach ($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
            @endforeach
          </select>
        </td>

        <td><input type="number" name="items[${rowIndex}][price]" id="bilty_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
        <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
        <td>
          <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
        </td>
      </tr>
    `;
    table.append(newRow);
    $('#itemCount').val(index);
    $(`#item_name${index}`).select2();
    $(`#unit${index}`).select2();
    index++;
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#bilty_qty' + row).val()) || 0;
    let price = parseFloat($('#bilty_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0, qty = 0;
    $('#BiltyItemTable tr').each(function () {
        total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
        qty += parseFloat($(this).find('input.quantity').val()) || 0; // <-- use class selector
    });
    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));
    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));
    netTotal();
  }

  // Recalculate Net Amount based on Bilty Amount field
  function netTotal() {
      let payable = parseFloat($('#payable_amount').val()) || 0; // use bilty amount
      $('#netTotal').text(formatNumberWithCommas(payable.toFixed(2)));
      $('#net_amount').val(payable.toFixed(2));
  }

  // Trigger recalculation when Bilty Amount changes
  $('#payable_amount').on('input', function() {
      netTotal();
  });

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function fetchInvoiceProducts() {
    let invoiceId = $('#bilty_purchase_id').val();
    if (!invoiceId) {
      alert("Please select a Purchase Invoice first.");
      return;
    }

    if (!confirm("This will clear current items. Continue?")) return;

    // Show loading state
    const btn = event.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    $.ajax({
        url: `/get-purchase-items/${invoiceId}`,
        method: 'GET',
        success: function(items) {
            // 1. Clear the table
            $('#BiltyItemTable').empty();
            index = 1; // Reset global index

            // 2. Loop through items and add rows
            if (items.length === 0) {
                alert("No items found in this invoice.");
                addNewRow(); // Add one empty row
            } else {
                items.forEach((item) => {
                    // Reuse your existing logic to add a row
                    addNewRow(); 
                    let currentRow = index - 1;

                    // 3. Fill the values
                    $(`#item_name${currentRow}`).val(item.item_id).trigger('change');
                    $(`#bilty_qty${currentRow}`).val(item.quantity);
                    
                    // Note: onItemNameChange is triggered by .trigger('change'), 
                    // which sets the unit and price automatically based on product defaults.
                    // If you want to use the specific price from the invoice:
                    // $(`#bilty_price${currentRow}`).val(item.price);
                    
                    rowTotal(currentRow);
                });
            }
        },
        error: function() {
          alert("Error fetching items.");
        },
        complete: function() {
          btn.innerHTML = originalHtml;
        }
  });
}
</script>

@endsection
