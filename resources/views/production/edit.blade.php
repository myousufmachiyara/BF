@extends('layouts.app')

@section('title', 'Production | Edit Order')

@section('content')
<div class="row">
  <form id="productionForm" action="{{ route('production.update', $production->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="row">
      <!-- Master Details -->
      <div class="col-12 col-md-12 mb-3">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Edit Production #{{ $production->id }}</h2>
          </header>
          <div class="card-body">
            <div class="row">
              <div class="col-12 col-md-2 mb-3">
                <label>Production #</label>
                <input type="text" class="form-control" value="{{ $production->id }}" disabled />
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Category</label>
                <select class="form-control" name="category_id">
                  <option value="">Select Category</option>
                  @foreach($categories as $item)  
                    <option value="{{ $item->id }}" {{ $production->category_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Vendor</label>
                <select class="form-control select2-js" name="vendor_id" id="vendor_name" required>
                  <option value="" disabled>Select Vendor</option>
                  @foreach($vendors as $item)  
                    <option value="{{ $item->id }}" {{ $production->vendor_id == $item->id ? 'selected' : '' }}>{{ $item->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Production Type</label>
                <select class="form-control" name="production_type" id="production_type" required>
                  <option value="" disabled>Select Type</option>
                  <option value="cmt" {{ $production->production_type == 'cmt' ? 'selected' : '' }}>CMT</option>
                  <option value="sale_leather" {{ $production->production_type == 'sale_leather' ? 'selected' : '' }}>Sale Leather</option>
                </select>
                <input type="hidden" name="challan_generated" value="{{ $production->challan_no ? 1 : 0 }}">
              </div>

              <div class="col-12 col-md-2 mb-3">
                <label>Order Date</label>
                <input type="date" name="order_date" class="form-control" id="order_date" value="{{ $production->order_date }}" required/>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Raw Details -->
      <div class="col-12 col-md-12 mb-3">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Raw Material Details</h2>
          </header>
          <div class="card-body">
            <table class="table table-bordered" id="myTable">
              <thead>
                <tr>
                  <th>Raw</th>
                  <th>Variation</th>
                  <th>Invoice #</th>
                  <th>Rate</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Total</th>
                  <th width="8%"></th>
                </tr>
              </thead>
              <tbody id="PurPOTbleBody">
                @foreach($production->details as $index => $detail)
                  <tr class="item-row">
                    <td>
                      <select name="item_details[{{ $index }}][item_id]" id="productSelect{{ $index }}" class="form-control select2-js" onchange="onItemChange(this)" required>
                        <option value="" disabled>Select Product</option>
                        @foreach($allProducts as $product)
                          <option value="{{ $product->id }}" data-unit="{{ $product->unit }}" {{ $detail->product_id == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                          </option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <select name="item_details[{{ $index }}][variation_id]" id="variationSelect{{ $index }}" class="form-control select2-js">
                        <option value="" disabled>Select Variation</option>
                        @php
                          $productVariations = $allProducts->firstWhere('id', $detail->product_id)?->variations ?? [];
                        @endphp
                        @foreach($productVariations as $var)
                          <option value="{{ $var->id }}" 
                            {{ $detail->variation_id == $var->id ? 'selected' : '' }}>
                            {{ $var->sku }}
                          </option>
                        @endforeach
                      </select>
                    </td>

                    <td>
                      <select name="item_details[{{ $index }}][invoice]" id="invoiceSelect{{ $index }}" class="form-control" onchange="onInvoiceChange(this)">
                        <option value="" disabled>Select Invoice</option>
                        @if($detail->invoice_id)
                          <option value="{{ $detail->invoice_id }}" selected>{{ $detail->invoice_id }}</option>
                        @endif
                      </select>
                    </td>
                    <td><input type="number" name="item_details[{{ $index }}][rate]" id="item_rate_{{ $index }}" step="any" value="{{ $detail->rate }}" onchange="rowTotal({{ $index }})" class="form-control" required/></td>
                    <td><input type="number" name="item_details[{{ $index }}][qty]" id="item_qty_{{ $index }}" step="any" value="{{ $detail->qty }}" onchange="rowTotal({{ $index }})" class="form-control" required/></td>
                    <td>
                      <select id="item_unit_{{ $index }}" class="form-control" name="item_details[{{ $index }}][item_unit]" required>
                        <option value="" disabled>Select Unit</option>
                        @foreach($units as $unit)
                          <option value="{{ $unit->id }}" {{ $detail->unit == $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                        @endforeach          
                      </select>
                    </td>
                    <td><input type="number" id="item_total_{{ $index }}" class="form-control" value="{{ $detail->qty * $detail->rate }}" disabled/></td>
                    <td>
                      <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                      <button type="button" class="btn btn-primary btn-xs" onclick="addNewRow()"><i class="fa fa-plus"></i></button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <!-- Challan -->
      <div class="col-12 col-md-5 mb-3">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Voucher (Challan #)</h2>
            <div>
              <a class="btn btn-danger text-end" onclick="generateVoucher()">Generate Challan</a>
            </div>
          </header>
          <div class="card-body">
            <div class="row pb-4">
              <div class="col-12 mt-3" id="voucher-container">
                @if($production->challan_no)
                  <div class="border p-3">
                    <h3 class="text-center text-dark">Production Challan #{{ $production->challan_no }}</h3>
                    <p><strong>Vendor:</strong> {{ $production->vendor->name ?? '-' }}</p>
                    <p><strong>Date:</strong> {{ $production->order_date }}</p>
                  </div>
                @endif
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- Summary -->
      <div class="col-12 col-md-7">
        <section class="card">
          <header class="card-header d-flex justify-content-between">
            <h2 class="card-title">Summary</h2>
          </header>
          <div class="card-body">
            <div class="row pb-4">
              <div class="col-12 col-md-3">
                <label>Total Raw Quantity</label>
                <input type="number" class="form-control" id="total_fab" value="{{ $production->details->sum('qty') }}" disabled/>
              </div>

              <div class="col-12 col-md-3">
                <label>Total Raw Amount</label>
                <input type="number" class="form-control" id="total_fab_amt" value="{{ $production->details->sum(fn($d) => $d->qty * $d->rate) }}" disabled/>
              </div>
              
              <div class="col-12 col-md-5">
                <label>Attachment</label>
                <input type="file" class="form-control" name="attachments[]" multiple accept="image/png, image/jpeg, image/jpg, image/webp">
              </div>

              <div class="col-12 text-end">
                <h3 class="font-weight-bold mb-0 text-5 text-primary">Net Amount</h3>
                <span><strong class="text-4 text-primary">PKR <span id="netTotal" class="text-4 text-danger">{{ number_format($production->details->sum(fn($d) => $d->qty * $d->rate),0) }}</span></strong></span>
                <input type="hidden" name="total_amount" id="net_amount" value="{{ $production->details->sum(fn($d) => $d->qty * $d->rate) }}">
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <a class="btn btn-danger" href="{{ route('production.index') }}">Cancel</a>
            <button type="submit" class="btn btn-primary">Update</button>
          </footer>
        </section>
      </div>
    </div>
  </form>
</div>

<script>
  var index = {{ $production->details->count() }};
  const allProducts = @json($allProducts);

  document.addEventListener("DOMContentLoaded", function () {

      // Initialize Select2 for all existing selects
      $('.select2-js').select2({ width: '100%' });

      // Prefill variation and invoice selects for existing rows
      @foreach($production->details as $index => $detail)
          (function(i) {
              const row = document.querySelector('#PurPOTbleBody tr:nth-child(' + (i+1) + ')');
              if (!row) return;

              const productSelect = row.querySelector('#productSelect' + i);
              const variationSelect = row.querySelector('#variationSelect' + i);
              const invoiceSelect = row.querySelector('#invoiceSelect' + i);

              if (variationSelect) {
                  variationSelect.innerHTML = `<option value="{{ $detail->variation_id }}" selected>{{ $detail->variation->sku ?? 'Variation' }}</option>`;
                  $(variationSelect).select2({ width: '100%' });
              }

              if (invoiceSelect) {
                  invoiceSelect.innerHTML = `<option value="{{ $detail->invoice_id }}" selected>{{ $detail->invoice_id }}</option>`;
                  $(invoiceSelect).select2({ width: '100%' });
              }

          })({{ $index }});
      @endforeach

      // Auto-generate challan on edit load if production_type is sale_leather
      const productionType = document.getElementById('production_type').value;
      if (productionType === 'sale_leather') {
          generateVoucher();
      }

      // Update challan live whenever qty, rate, product, or unit changes
      $('#PurPOTbleBody').on('change', 'select, input', function () {
          const productionType = document.getElementById('production_type').value;
          if (productionType === 'sale_leather') {
              generateVoucher();
          }
      });

      // Prevent form submit if challan not generated for sale_leather
      const form = document.getElementById('productionForm');
      form.addEventListener('submit', function (e) {
          const challanGenerated = $('#productionForm input[name="challan_generated"]').val();
          const productionType = document.getElementById('production_type').value;

          if (productionType === 'sale_leather' && challanGenerated !== '1') {
              e.preventDefault();
              alert("Please generate the challan before submitting the form.");
              return false;
          }
      });
  });

  // ------------------ ROW FUNCTIONS ------------------

  function removeRow(button) {
      const tableRows = $("#PurPOTbleBody tr").length;
      if (tableRows > 1) {
          const row = button.closest('tr');
          row.remove();
          index--;
          tableTotal();
          regenerateChallanIfNeeded();
      }
  }

  function addNewRow() {
      const table = document.getElementById('myTable').getElementsByTagName('tbody')[0];
      const newRow = table.insertRow();
      newRow.classList.add('item-row');

      const options = allProducts.map(p =>
        `<option value="${p.id}" data-unit="${p.unit ?? ''}">${p.name}</option>`
      ).join('');

      newRow.innerHTML = `
          <td>
              <select data-plugin-selecttwo name="item_details[${index}][item_id]" required id="productSelect${index}" class="form-control select2-js" onchange="onItemChange(this)">
                  <option value="" disabled selected>Select Product</option>
                  ${options}
              </select>
          </td>
          <td>
              <select name="item_details[${index}][variation_id]" id="variationSelect${index}" class="form-control select2-js">
                  <option value="" selected disabled>Select Variation</option>
              </select>
          </td>
          <td>
              <select name="item_details[${index}][invoice]" id="invoiceSelect${index}" class="form-control" onchange="onInvoiceChange(this)">
                  <option value="" disabled selected>Select Invoice</option>
              </select>
          </td>
          <td><input type="number" name="item_details[${index}][rate]" id="item_rate_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
          <td><input type="number" name="item_details[${index}][qty]" id="item_qty_${index}" step="any" value="0" onchange="rowTotal(${index})" class="form-control" required/></td>
          <td>
              <select id="item_unit_${index}" class="form-control" name="item_details[${index}][item_unit]" required>
                  <option value="" disabled selected>Select Unit</option>
                  @foreach($units as $unit)
                      <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                  @endforeach
              </select>
          </td>
          <td><input type="number" id="item_total_${index}" class="form-control" placeholder="Total" disabled/></td>
          <td>
              <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
              <button type="button" onclick="addNewRow()" class="btn btn-primary btn-xs"><i class="fa fa-plus"></i></button>
          </td>
      `;

      index++;
      $('#myTable select[data-plugin-selecttwo]').select2({ width: '100%' });
  }

  // ------------------ TOTAL & CHALLAN ------------------

  function rowTotal(i) {
      const rate = parseFloat($(`#item_rate_${i}`).val()) || 0;
      const qty = parseFloat($(`#item_qty_${i}`).val()) || 0;
      const total = rate * qty;

      $(`#item_total_${i}`).val(total.toFixed(2));
      tableTotal();
      regenerateChallanIfNeeded();
  }

  function tableTotal() {
      let totalQty = 0;
      let totalAmt = 0;

      $('#PurPOTbleBody tr').each(function () {
          const rate = parseFloat($(this).find('input[id^="item_rate_"]').val()) || 0;
          const qty = parseFloat($(this).find('input[id^="item_qty_"]').val()) || 0;
          totalQty += qty;
          totalAmt += rate * qty;
      });

      $('#total_fab').val(totalQty);
      $('#total_fab_amt').val(totalAmt.toFixed(2));
      updateNetTotal(totalAmt);
  }

  function updateNetTotal(total) {
      const net = parseFloat(total) || 0;
      $('#netTotal').text(formatNumberWithCommas(net.toFixed(0)));
      $('#net_amount').val(total);
  }

  function formatNumberWithCommas(x) {
      return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function regenerateChallanIfNeeded() {
      const productionType = document.getElementById('production_type').value;
      if (productionType === 'sale_leather') {
          generateVoucher();
      }
  }

  // ------------------ PRODUCT / VARIATION / INVOICE ------------------

    // 🔹 When product changes
    function onItemChange(select) {
        const row = select.closest('tr');
        const itemId = select.value;
        if (!row || !itemId) return;

        // --- Reset variation dropdown ---
        const variationSelect = row.querySelector(`select[id^="variationSelect"]`);
        variationSelect.innerHTML = `<option value="" disabled selected>Loading...</option>`;

        // --- Reset invoice dropdown ---
        const invoiceSelect = row.querySelector(`select[id^="invoiceSelect"]`);
        invoiceSelect.innerHTML = `<option value="" disabled selected>Select Invoice</option>`;

        // --- Reset qty, rate, total ---
        row.querySelector(`input[id^="item_qty_"]`).value = '';
        row.querySelector(`input[id^="item_rate_"]`).value = '';
        row.querySelector(`input[id^="item_total_"]`).value = '';

        // --- Auto-fill unit dropdown ---
        const unitSelect = row.querySelector(`select[id^="item_unit_"]`);
        if (unitSelect) {
            const selectedOption = select.options[select.selectedIndex];
            const unitId = selectedOption.getAttribute("data-unit");

            if (unitId) {
                unitSelect.value = unitId;
                $(unitSelect).select2({ width: '100%' });
            }
        }

        // --- Fetch variations for this product ---
        fetch(`/product/${itemId}/variations`)
            .then(res => res.json())
            .then(data => {
                variationSelect.innerHTML = `<option value="" disabled selected>Select Variation</option>`;
                if (data.success && data.variation.length) {
                    data.variation.forEach(v => {
                        variationSelect.innerHTML += `<option value="${v.id}" data-product-id="${itemId}">${v.sku}</option>`;
                    });
                } else {
                    variationSelect.innerHTML = `<option value="">No Variations</option>`;
                }

                $(variationSelect).select2({ width: '100%' });
            })
            .catch(() => {
                variationSelect.innerHTML = `<option value="">Error loading variations</option>`;
            });

        // --- Fetch invoices for this product ---
        fetchInvoices(itemId, row);
    }

  // 🔹 Fetch invoices
  function fetchInvoices(id, row, isVariation = false) {
    const invoiceSelect = row.querySelector(`select[id^="invoiceSelect"]`);
    invoiceSelect.innerHTML = `<option value="" disabled selected>Loading...</option>`;

    fetch(`/product/${id}/invoices`)
      .then(res => res.json())
      .then(data => {
        invoiceSelect.innerHTML = `<option value="" disabled selected>Select Invoice</option>`;
        if (Array.isArray(data) && data.length > 0) {
          data.forEach(inv => {
            invoiceSelect.innerHTML += `<option value="${inv.id}" data-rate="${inv.rate}">${inv.id}</option>`;
          });
        } else {
          invoiceSelect.innerHTML = `<option value="">No Invoices Found</option>`;
        }

        $(invoiceSelect).select2({ width: '100%' });
      })
      .catch(() => {
        invoiceSelect.innerHTML = `<option value="">Error loading invoices</option>`;
      });
  }

  // 🔹 When invoice changes
  function onInvoiceChange(select) {
    const row = select.closest('tr');
    const option = select.selectedOptions[0];
    if (!row || !option) return;

    const rate = option.getAttribute('data-rate') || 0;

    const rateInput = row.querySelector(`input[id^="item_rate_"]`);
    const qtyInput = row.querySelector(`input[id^="item_qty_"]`);
    const totalInput = row.querySelector(`input[id^="item_total_"]`);

    if (rateInput) rateInput.value = rate;
    if (qtyInput && totalInput) {
      totalInput.value = ((parseFloat(qtyInput.value) || 0) * (parseFloat(rate) || 0)).toFixed(2);
    }

    tableTotal();
  }

  // ------------------ CHALLAN ------------------

  function generateVoucher() {
      const voucherContainer = $("#voucher-container");
      voucherContainer.html('');

      const vendorName = $("#vendor_name option:selected").text() || "-";
      const orderDate = $('#order_date').val();

      let itemsHTML = "";
      let grandTotal = 0;

      $(".item-row").each(function () {
          const productName = $(this).find('select[name*="[item_id]"] option:selected').text() || "-";
          const qty = parseFloat($(this).find('input[name*="[qty]"]').val() || 0);
          const unit = $(this).find('select[name*="[item_unit]"] option:selected').text() || "-";
          const rate = parseFloat($(this).find('input[name*="[rate]"]').val() || 0);
          const total = qty * rate;
          grandTotal += total;

          itemsHTML += `
              <tr>
                  <td>${productName}</td>
                  <td>${qty} ${unit}</td>
                  <td>${rate.toFixed(2)}</td>
                  <td>${total.toFixed(2)}</td>
              </tr>
          `;
      });

      const html = `
          <div class="border p-3 mt-3">
              <h3 class="text-center text-dark">Production Challan</h3>
              <hr>
              <div class="d-flex justify-content-between text-dark">
                  <p><strong>Vendor:</strong> ${vendorName}</p>
                  <p><strong>Date:</strong> ${orderDate}</p>
              </div>
              <table class="table table-bordered mt-3">
                  <thead class="bg-light">
                      <tr>
                          <th>Product</th>
                          <th>Qty</th>
                          <th>Rate</th>
                          <th>Total</th>
                      </tr>
                  </thead>
                  <tbody>
                      ${itemsHTML}
                  </tbody>
                  <tfoot>
                      <tr>
                          <th colspan="3" class="text-end">Grand Total</th>
                          <th>${grandTotal.toFixed(2)}</th>
                      </tr>
                  </tfoot>
              </table>
              <div class="d-flex justify-content-between mt-4">
                  <div>
                      <p class="text-dark"><strong>Authorized By:</strong></p>
                      <p>________________________</p>
                  </div>
              </div>
          </div>
      `;

      voucherContainer.html(html);

      // Update hidden inputs in the form
      $('#productionForm input[name="challan_generated"]').val(1);
      if ($('#productionForm input[name="voucher_amount"]').length === 0) {
          $('#productionForm').append(`<input type="hidden" name="voucher_amount" value="${grandTotal.toFixed(2)}">`);
      } else {
          $('#productionForm input[name="voucher_amount"]').val(grandTotal.toFixed(2));
      }
  }

</script>

@endsection
