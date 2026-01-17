@extends('layouts.app')

@section('title', 'Create Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.store') }}" onkeydown="return event.key != 'Enter';" method="POST">
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
                <option value="cash">Cash</option>
                <option value="credit">Credit</option>
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
                <th>Item</th>
                <th width="50%">Customize Item</th>
                <th width="10%">Price</th>
                <th width="8%">Qty</th>
                <th width="10%">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name="items[0][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][customizations][]" multiple class="form-control select2-js ">
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
                    @endforeach
                  </select>
                </td>
                <td><input type="number" name="items[0][sale_price]" class="form-control sale-price" step="any" required></td>
                <td><input type="number" name="items[0][quantity]" class="form-control quantity" step="any" required></td>
                <td><input type="number" name="items[0][total]" class="form-control row-total" readonly></td>
                <td>
                  <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button>
                </td>
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
  let rowIndex = $('#itemTable tbody tr').length || 1;

    $(document).ready(function () {
      $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });

      // Initialize existing rows
      $('#itemTable tbody tr').each(function () {
          const row = $(this);
          initRowSelect2(row);
          calcRowTotal(row);
      });

      // Delegate: product change
      $(document).on('change', '.product-select', function () {
          const row = $(this).closest('tr');

          // 1️⃣ Auto-fill price
          const productPrice = $(this).find(':selected').data('price') || 0;
          row.find('.sale-price').val(productPrice);

          // 2️⃣ Sync customization options
          initRowSelect2(row);

          // 3️⃣ Recalculate row
          calcRowTotal(row);
      });

      // Delegate: any price/qty change
      $(document).on('input', '.sale-price, .quantity', function () {
          calcRowTotal($(this).closest('tr'));
      });

      // Invoice-level discount
      $(document).on('input', '#discountInput', calcTotal);
  });

  /**
   * Initialize Select2 for a row and handle disabling the main product in multi-select
   */
  function initRowSelect2(row) {
      const customizationSelect = row.find('select[name*="[customizations]"]');
      const mainProductId = row.find('.product-select').val();

      // Destroy existing Select2
      if (customizationSelect.hasClass("select2-hidden-accessible")) {
          customizationSelect.select2('destroy');
      }

      // Get pre-selected options
      const selectedVals = customizationSelect.find('option[selected]').map(function () {
          return $(this).val();
      }).get();

      // Disable main product in multi-select if not selected
      customizationSelect.find('option').each(function () {
          if ($(this).val() == mainProductId && !selectedVals.includes(mainProductId)) {
              $(this).prop('disabled', true);
          } else {
              $(this).prop('disabled', false);
          }
      });

      // Initialize Select2 with preselected values
      customizationSelect.val(selectedVals).select2({
          width: '100%',
          dropdownAutoWidth: true
      });
  }

  // Add new row
  function addRow() {
      const idx = rowIndex++;
      const rowHtml = `
        <tr>
          <td>
            <select name="items[${idx}][product_id]" class="form-control product-select" required>
              <option value="">Select Product</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}">{{ $product->name }}</option>
              @endforeach
            </select>
          </td>
          <td>
            <select name="items[${idx}][customizations][]" multiple class="form-control">
              @foreach($products as $product)
                <option value="{{ $product->id }}">{{ $product->name }}</option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="items[${idx}][sale_price]" class="form-control sale-price" step="any" required></td>
          <td><input type="number" name="items[${idx}][quantity]" class="form-control quantity" step="any" required></td>
          <td><input type="number" name="items[${idx}][total]" class="form-control row-total" readonly></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>
      `;

      $('#itemTable tbody').append(rowHtml);

      const $newRow = $('#itemTable tbody tr').last();
      initRowSelect2($newRow);
      $newRow.find('.product-select').focus();
  }

  function removeRow(btn) {
      $(btn).closest('tr').remove();
      calcTotal();
  }

  // Calculate row total
  function calcRowTotal(row) {
      const price = parseFloat(row.find('.sale-price').val()) || 0;
      const qty = parseFloat(row.find('.quantity').val()) || 0;
      const total = price * qty;

      row.find('.row-total').val(total.toFixed(2));
      calcTotal();
  }

  // Calculate invoice total
  function calcTotal() {
      let total = 0;
      $('.row-total').each(function () {
          total += parseFloat($(this).val()) || 0;
      });

      const invoiceDiscount = parseFloat($('#discountInput').val()) || 0;
      const netAmount = Math.max(0, total - invoiceDiscount);

      $('#netAmountText').text(netAmount.toFixed(2));
      $('#netAmountInput').val(netAmount.toFixed(2));
  }
</script>


@endsection
