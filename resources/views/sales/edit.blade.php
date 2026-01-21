@extends('layouts.app')

@section('title', 'Edit Sale Invoice')

@section('content')
<div class="row">
  <form action="{{ route('sale_invoices.update', $invoice->id) }}" onkeydown="return event.key != 'Enter';" method="POST">
    @csrf
    @method('PUT')

    <div class="col-12 mb-2">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Sale Invoice: #{{ $invoice->invoice_no }}</h2>
        </header>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-2">
              <label>Invoice #</label>
              <input type="text" class="form-control" value="{{ $invoice->invoice_no }}" readonly/>
            </div>
            <div class="col-md-2">
              <label>Date</label>
              <input type="date" name="date" class="form-control" value="{{ $invoice->date }}" required />
            </div>
            <div class="col-md-3">
              <label>Customer Name</label>
              <select name="account_id" class="form-control select2-js" required>
                @foreach($customers as $acc)
                  <option value="{{ $acc->id }}" {{ $invoice->account_id == $acc->id ? 'selected' : '' }}>
                    {{ $acc->name }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-2">
              <label>Invoice Type</label>
              <select name="type" class="form-control" required>
                <option value="cash" {{ $invoice->type == 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="credit" {{ $invoice->type == 'credit' ? 'selected' : '' }}>Credit</option>
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
              <tr class="bg-light">
                <th>Item</th>
                <th width="35%">Customize Item</th>
                <th width="12%">Price</th>
                <th width="10%">Qty</th>
                <th width="12%">Total</th>
                <th width="5%"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($invoice->items as $i => $item)
              <tr>
                <td>
                  <select name="items[{{ $i }}][product_id]" class="form-control select2-js product-select" required>
                    <option value="">Select Product</option>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" 
                        data-price="{{ $product->selling_price }}"
                        data-stock="{{ $product->real_time_stock }}"
                        {{ $item->product_id == $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                      </option>
                    @endforeach
                  </select>
                  <div class="stock-label" style="font-size: 11px; font-weight: bold; margin-top: 2px;"></div>
                </td>
                <td>
                  <select name="items[{{ $i }}][customizations][]" class="form-control select2-js customization-select" multiple>
                    @foreach($products as $product)
                      <option value="{{ $product->id }}" 
                        data-stock="{{ $product->real_time_stock }}"
                        {{ $item->customizations->pluck('item_id')->contains($product->id) ? 'selected' : '' }}>
                        {{ $product->name }} (Stock: {{ $product->real_time_stock }})
                      </option>
                    @endforeach
                  </select>
                </td>
                </tr>
              @endforeach
            </tbody>
          </table>
          <button type="button" class="btn btn-success btn-sm" onclick="addRow()">+ Add Item</button>

          <hr>

          <div class="row mb-2">
            <div class="col-md-4">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $invoice->remarks }}</textarea>
            </div>
            <div class="col-md-3">
                <label><strong>Total Discount (PKR)</strong></label>
                <input type="number" name="discount" id="discountInput" class="form-control" step="any" value="{{ $invoice->discount }}">
                
                <div class="mt-3 p-2 bg-light border rounded">
                  <small class="text-muted d-block">Already Received:</small>
                  <strong class="text-success">PKR {{ number_format($amountReceived, 2) }}</strong>
                  <input type="hidden" id="amountReceivedHidden" value="{{ $amountReceived }}">
                </div>
            </div>
            <div class="col-md-5 text-end">
              <label><strong>Net Payable</strong></label>
              <h3 class="text-primary mt-0 mb-1">PKR <span id="netAmountText">0.00</span></h3>
              <input type="hidden" name="net_amount" id="netAmountInput" value="{{ $invoice->net_amount }}">
              
              <label class="text-danger mt-2"><strong>Remaining Balance</strong></label>
              <h4 class="text-danger mt-0">PKR <span id="balanceAmountText">0.00</span></h4>
            </div>
          </div>

          <hr>

          <div class="row p-3 mb-2" style="background-color: #e7f3ff; border-radius: 5px; border: 1px solid #b8daff;">
              <div class="col-md-12">
                  <h5><i class="fas fa-plus-circle"></i> Add New Payment (Optional)</h5>
              </div>
              <div class="col-md-6">
                  <label>Receive In (Cash/Bank)</label>
                  <select name="payment_account_id" class="form-control select2-js">
                      <option value="">-- No New Payment --</option>
                      @foreach($paymentAccounts as $pa)
                          <option value="{{ $pa->id }}">{{ $pa->name }}</option>
                      @endforeach
                  </select>
              </div>
              <div class="col-md-6">
                  <label>New Amount Received Now</label>
                  <input type="number" name="amount_received" class="form-control" step="any" placeholder="0.00">
              </div>
          </div>

        </div>
        <footer class="card-footer text-end">
          <a href="{{ route('sale_invoices.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary btn-lg">Update Invoice</button>
        </footer>
      </section>
    </div>
  </form>
</div>

<script>
  let rowIndex = {{ $invoice->items->count() }};

  $(document).ready(function () {
      // Initialize Select2 for standard selects outside the table
      $('.select2-js').not('#itemTable select').select2({ width: '100%' });

      // 1. Initialize existing rows
    $('#itemTable tbody tr').each(function () {
        const row = $(this);
        initRowSelect2(row);
        calcRowTotal(row);
        // Trigger stock label update for existing items
        updateStockLabel(row.find('.product-select'));
    });

    // 2. Focus Fix for Select2
    $(document).on('select2:open', function(e) {
        setTimeout(() => {
            const searchField = document.querySelector('.select2-container--open .select2-search__field');
            if (searchField) { searchField.focus(); }
        }, 50); 
    });

    // 3. Clear search on multiple select
    $(document).on('select2:select', '.customization-select', function (e) {
        $(this).parent().find('.select2-search__field').val('').trigger('input');
    });

    // 4. Product change handler (Stock + Price)
    $(document).on('change', '.product-select', function () {
        const row = $(this).closest('tr');
        const option = $(this).find(':selected');
        
        // Update Price
        const productPrice = option.data('price') || 0;
        row.find('.sale-price').val(productPrice);
        
        // Update Stock Label
        updateStockLabel($(this));
        
        calcRowTotal(row);
    });

    function updateStockLabel(selectElement) {
        const row = selectElement.closest('tr');
        const option = selectElement.find(':selected');
        const stock = parseFloat(option.data('stock'));
        const label = row.find('.stock-label');

        if (!selectElement.val()) {
            label.text('');
            return;
        }

        label.text('Stock: ' + stock);
        label.css('color', stock <= 0 ? 'red' : 'green');
    }

      // Price / Qty / Discount change
      $(document).on('input', '.sale-price, .quantity, #discountInput', function () {
          if($(this).hasClass('sale-price') || $(this).hasClass('quantity')){
            calcRowTotal($(this).closest('tr'));
          } else {
            calcTotal();
          }
      });

      calcTotal();
  });

  /**
   * Initialize Select2 for a row
   */
  function initRowSelect2(row) {
      row.find('.product-select').select2({ width: '100%' });
      row.find('.customization-select').select2({
          width: '100%',
          placeholder: "Select customizations...",
          closeOnSelect: false
      });
  }

  /**
   * Add New Row
   */
  function addRow() {
      const idx = rowIndex++;
      const rowHtml = `
        <tr>
          <td>
            <select name="items[${idx}][product_id]" class="form-control product-select" required>
              <option value="">Select Product</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}" 
                        data-price="{{ $product->selling_price }}" 
                        data-stock="{{ $product->real_time_stock }}">
                    {{ $product->name }}
                </option>
              @endforeach
            </select>
            <div class="stock-label" style="font-size: 11px; font-weight: bold; margin-top: 2px;"></div>
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
          <td><input type="number" class="form-control row-total" readonly></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>`;

      $('#itemTable tbody').append(rowHtml);
      const $newRow = $('#itemTable tbody tr').last();
      initRowSelect2($newRow);
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
      const alreadyPaid = parseFloat($('#amountReceivedHidden').val()) || 0;

      $('#netAmountText').text(netAmount.toLocaleString(undefined, {minimumFractionDigits: 2}));
      $('#netAmountInput').val(netAmount.toFixed(2));
      
      const balance = netAmount - alreadyPaid;
      $('#balanceAmountText').text(balance.toLocaleString(undefined, {minimumFractionDigits: 2}));
  }
</script>
@endsection