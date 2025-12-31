@extends('layouts.app')

@section('title', 'Purchase | Edit Bilty')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_bilty.update', $bilty->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Bilty</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" value="{{ count($bilty->details) }}">

            {{-- Bilty Date --}}
            <div class="col-md-2 mb-3">
              <label>Bilty Date</label>
              <input type="date" name="bilty_date" class="form-control"
                     value="{{ $bilty->bilty_date }}" required>
            </div>

            {{-- Vendor --}}
            <div class="col-md-3 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}"
                    {{ $bilty->vendor_id == $vendor->id ? 'selected' : '' }}>
                    {{ $vendor->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Reference --}}
            <div class="col-md-2 mb-3">
              <label>Ref #</label>
              <input type="text" name="ref_no" class="form-control"
                     value="{{ $bilty->ref_no }}">
            </div>

            <div class="col-md-3 mb-3">
              <label>Purchase Invoice</label>
              <select name="purchase_id" class="form-control select2-js">
                <option value="">Select Invoice</option>
                @foreach ($purchaseInvoices as $inv)
                  <option value="{{ $inv->id }}"
                    {{ $bilty->purchase_id == $inv->id ? 'selected' : '' }}>
                    {{ $inv->invoice_no }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Remarks --}}
            <div class="col-md-3 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="3">{{ $bilty->remarks }}</textarea>
            </div>
          </div>

          {{-- ITEMS TABLE --}}
          <div class="table-responsive mb-3">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Est. Amount</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody id="BiltyItemTable">
                @foreach ($bilty->details as $i => $detail)
                  <tr>
                    {{-- Item --}}
                    <td>
                      <select name="items[{{ $i }}][item_id]"
                              id="item_name{{ $i + 1 }}"
                              class="form-control select2-js product-select"
                              onchange="onItemNameChange(this)">
                        <option value="">Select Item</option>
                        @foreach ($products as $product)
                          <option value="{{ $product->id }}"
                                  data-unit-id="{{ $product->measurement_unit }}"
                                  data-bilty-charges="{{ $product->bilty_charges }}"
                                  {{ $detail->item_id == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>

                    {{-- Quantity --}}
                    <td>
                      <input type="number"
                            name="items[{{ $i }}][quantity]"
                            id="bilty_qty{{ $i + 1 }}"
                            class="form-control quantity"
                            value="{{ $detail->quantity }}"
                            step="any"
                            onchange="rowTotal({{ $i + 1 }})">
                    </td>

                    {{-- Unit --}}
                    <td>
                      <select name="items[{{ $i }}][unit]"
                              id="unit{{ $i + 1 }}"
                              class="form-control select2-js" required>
                        <option value="">-- Select --</option>
                        @foreach ($units as $unit)
                          <option value="{{ $unit->id }}"
                            {{ $detail->unit == $unit->id ? 'selected' : '' }}>
                            {{ $unit->name }} ({{ $unit->shortcode }})
                          </option>
                        @endforeach
                      </select>
                    </td>

                    {{-- Est Amount (Price) --}}
                    <td>
                      <input type="number"
                            name="items[{{ $i }}][price]"
                            id="bilty_price{{ $i + 1 }}"
                            class="form-control"
                            value="{{ $detail->price ?? 0 }}"
                            step="any"
                            onchange="rowTotal({{ $i + 1 }})">
                    </td>

                    {{-- Amount --}}
                    <td>
                      <input type="number"
                            id="amount{{ $i + 1 }}"
                            class="form-control"
                            value="{{ number_format(($detail->quantity * ($detail->price ?? 0)), 2) }}"
                            disabled>
                    </td>

                    {{-- Action --}}
                    <td>
                      <button type="button"
                              class="btn btn-danger btn-sm"
                              onclick="removeRow(this)">
                        <i class="fas fa-times"></i>
                      </button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            <button type="button"
                    class="btn btn-outline-primary"
                    onclick="addNewRow_btn()">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mb-3">
            <div class="col-md-2">
              <label>Total Quantity</label>
              <input type="text" id="total_quantity" class="form-control" disabled>
              <input type="hidden" name="total_quantity" id="total_quantity_show">
            </div>

            <div class="col-md-2">
              <label>Total Amount</label>
              <input type="text" id="totalAmount" class="form-control" disabled>
              <input type="hidden" name="total_amount" id="total_amount_show">
            </div>

            {{-- Bilty Amount --}}
            <div class="col-md-2">
              <label>Bilty Amount</label>
              <input type="number" name="bilty_amount" class="form-control"
                     value="{{ $bilty->bilty_amount }}" step="any" required>
            </div>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>
                Net Amount:
                <strong class="text-danger">
                  PKR <span id="netTotal">0.00</span>
                </strong>
              </h4>
              <input type="hidden" name="net_amount" id="net_amount">
            </div>
          </div>

        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Update Bilty
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var units = @json($units);
  var index = {{ count($bilty->details) + 1 }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
    tableTotal(); // ✅ calculate totals on load
  });

  function addNewRow_btn() {
    let rowIndex = index - 1;

    let row = `
      <tr>
        <td>
          <select name="items[${rowIndex}][item_id]"
                  id="item_name${index}"
                  class="form-control select2-js"
                  onchange="onItemNameChange(this)">
            <option value="">Select Item</option>
            ${products.map(p =>
              `<option value="${p.id}" data-unit-id="${p.measurement_unit}">
                ${p.name}
              </option>`).join('')}
          </select>
        </td>

        <td>
          <input type="number"
                 name="items[${rowIndex}][quantity]"
                 class="form-control quantity"
                 value="0" step="any">
        </td>

        <td>
          <select name="items[${rowIndex}][unit]"
                  id="unit${index}"
                  class="form-control select2-js" required>
            ${units.map(u =>
              `<option value="${u.id}">
                ${u.name} (${u.shortcode})
              </option>`).join('')}
          </select>
        </td>

        <td>
          <input type="text"
                 name="items[${rowIndex}][remarks]"
                 class="form-control">
        </td>

        <td>
          <button type="button"
                  class="btn btn-danger btn-sm"
                  onclick="removeRow(this)">
            <i class="fas fa-times"></i>
          </button>
        </td>
      </tr>
    `;

    $('#Purchase1Table').append(row);
    $(`#item_name${index}, #unit${index}`).select2();
    index++;
  }

  function removeRow(btn) {
    if ($('#Purchase1Table tr').length > 1) {
      $(btn).closest('tr').remove();
    }
  }

  function onItemNameChange(el) {
    const unitId = el.options[el.selectedIndex].dataset.unitId;
    const row = el.id.match(/\d+/)[0];
    $('#unit' + row).val(unitId).trigger('change');
  }

  function rowTotal(row) {
    let qty = parseFloat($('#bilty_qty' + row).val()) || 0;
    let price = parseFloat($('#bilty_price' + row).val()) || 0;

    $('#amount' + row).val((qty * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0;
    let qty = 0;

    $('#BiltyItemTable tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
      qty += parseFloat($(this).find('input.quantity').val()) || 0;
    });

    $('#totalAmount').val(total.toFixed(2));
    $('#total_amount_show').val(total.toFixed(2));

    $('#total_quantity').val(qty.toFixed(2));
    $('#total_quantity_show').val(qty.toFixed(2));

    netTotal();
  }

  function netTotal() {
    let total = parseFloat($('#totalAmount').val()) || 0;
    $('#netTotal').text(formatNumberWithCommas(total.toFixed(2)));
    $('#net_amount').val(total.toFixed(2));
  }

  function formatNumberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

</script>

@endsection
