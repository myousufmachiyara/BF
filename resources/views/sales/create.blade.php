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
                @foreach($customers as $account)
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
                <th width="18%">Item</th>
                <th width="60%">Customize Item</th>
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
                        <option value="{{ $product->id }}" 
                                data-price="{{ $product->selling_price }}" 
                                data-stock="{{ $product->real_time_stock }}">
                            {{ $product->name }} (Stock: {{ $product->real_time_stock }})
                        </option>
                      @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[0][customizations][]" multiple class="form-control select2-js customization-select">
                      @foreach($products as $product)
                        <option value="{{ $product->id }}" data-stock="{{ $product->real_time_stock }}">
                            {{ $product->name }} (Stock: {{ $product->real_time_stock }})
                        </option>
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
          <hr>
          <div class="row mb-2">
              <div class="col-md-4">
                  <label><strong>Receive Payment To:</strong></label>
                  <select name="payment_account_id" class="form-control select2-js">
                      <option value="">No Payment (Credit Sale)</option>
                      @foreach($paymentAccounts as $pAc)
                          <option value="{{ $pAc->id }}">{{ $pAc->name }}</option>
                      @endforeach
                  </select>
                  <small class="text-muted">Select Cash/Bank account if payment received.</small>
              </div>
              <div class="col-md-3">
                  <label>Amount Received</label>
                  <input type="number" name="amount_received" id="amountReceived" class="form-control" step="any" value="0">
              </div>
              <div class="col-md-5 text-end">
                  <label>Remaining Balance</label>
                  <h4 class="text-danger mt-0">PKR <span id="balanceAmountText">0.00</span></h4>
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
      // Initialize non-table Select2s
      $('.select2-js').not('#itemTable select').select2({ width: '100%' });

      // Initialize existing rows
      const rows = $('#itemTable tbody tr');
      rows.each(function () {
          const row = $(this);
          initProductSelect(row);
          initCustomizationSelect(row);
          calcRowTotal(row);
      });

      // Handle Product Change
      $(document).on('change', '.product-select', function () {
          const row = $(this).closest('tr');
          const price = $(this).find(':selected').data('price') || 0;
          row.find('.sale-price').val(price);
          
          initCustomizationSelect(row);
          calcRowTotal(row);
      });

      $(document).on('input', '.sale-price, .quantity', function () {
          calcRowTotal($(this).closest('tr'));
      });

      $(document).on('input', '#amountReceived, #discountInput', calcTotal);

      $(document).on('change', 'select[name="type"]', function() {
        if($(this).val() === 'cash') {
          const netTotal = $('#netAmountInput').val();
          $('#amountReceived').val(netTotal);
          calcTotal(); // Update the balance text
        } else {
          $('#amountReceived').val(0);
          calcTotal();
        }
      });

      // Check if Quantity entered exceeds available stock
      $(document).on('input', '.quantity', function () {
          const row = $(this).closest('tr');
          const stock = parseFloat(row.find('.product-select :selected').data('stock')) || 0;
          const qty = parseFloat($(this).val()) || 0;

          if (qty > stock) {
              $(this).css('border-color', 'red');
              // Optional: You can show a small warning text below the input
          } else {
              $(this).css('border-color', '');
          }
      });
  });

  function initProductSelect(row) {
      row.find('.product-select').select2({ width: '100%' });
  }

  function initCustomizationSelect(row) {
      const custSelect = row.find('.customization-select');
      const mainId = row.find('.product-select').val();

      custSelect.find('option').each(function() {
          $(this).prop('disabled', $(this).val() == mainId && mainId !== "");
      });

      if (custSelect.hasClass("select2-hidden-accessible")) {
          custSelect.select2('destroy');
      }
      
      custSelect.select2({
          width: '100%',
          placeholder: "Select customizations...",
          closeOnSelect: false
      });
  }

  function addRow() {
      const idx = rowIndex++;
      const rowHtml = `
        <tr>
          <td>
            <select name="items[${idx}][product_id]" class="form-control product-select" required>
              <option value="">Select Product</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}" data-price="{{ $product->selling_price }}" data-stock="{{ $product->real_time_stock }}">
                    {{ $product->name }} (Stock: {{ $product->real_time_stock }})
                </option>
              @endforeach
            </select>
          </td>
          <td>
            <select name="items[${idx}][customizations][]" multiple class="form-control customization-select">
              @foreach($products as $product)
                <option value="{{ $product->id }}" data-stock="{{ $product->real_time_stock }}">
                    {{ $product->name }} (Stock: {{ $product->real_time_stock }})
                </option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="items[${idx}][sale_price]" class="form-control sale-price" step="any" required></td>
          <td><input type="number" name="items[${idx}][quantity]" class="form-control quantity" step="any" required></td>
          <td><input type="number" name="items[${idx}][total]" class="form-control row-total" readonly></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>`;

      const $tbody = $('#itemTable tbody');
      $tbody.append(rowHtml);
      const $newRow = $tbody.find('tr').last();
      
      initProductSelect($newRow);
      initCustomizationSelect($newRow);
  }

  function removeRow(btn) {
      if ($('#itemTable tbody tr').length > 1) {
          $(btn).closest('tr').remove();
          calcTotal();
      }
  }

  function calcRowTotal(row) {
      const price = parseFloat(row.find('.sale-price').val()) || 0;
      const qty = parseFloat(row.find('.quantity').val()) || 0;
      row.find('.row-total').val((price * qty).toFixed(2));
      calcTotal();
  }

  function calcTotal() {
      let total = 0;
      $('.row-total').each(function () {
          total += parseFloat($(this).val()) || 0;
      });
      const discount = parseFloat($('#discountInput').val()) || 0;
      const netAmount = Math.max(0, total - discount);
      
      $('#netAmountText').text(netAmount.toFixed(2));
      $('#netAmountInput').val(netAmount.toFixed(2));

      // Calculate Balance
      const received = parseFloat($('#amountReceived').val()) || 0;
      const balance = netAmount - received;
      $('#balanceAmountText').text(balance.toFixed(2));
  }
</script>

@endsection