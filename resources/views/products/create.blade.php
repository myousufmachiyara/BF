@extends('layouts.app')

@section('title', 'Products | Create')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data"  onkeydown="return event.key != 'Enter';">
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
        <header class="card-header">
          <h2 class="card-title">New Product</h2>
        </header>

        <div class="card-body">
          <div class="row pb-3">
            <div class="col-md-2">
              <label>Product Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
              @error('name')<div class="text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
              <label>Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-control" required>
                <option value="" disabled selected>Select Category</option>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}" data-code="{{ $cat->shortcode }}">{{ $cat->name }}</option>
                @endforeach
              </select>
              @error('category_id')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label>SKU *</label>
              <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku') }}" required>
              @error('sku')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label for="unit_id">Measurement Unit</label>
              <select name="measurement_unit" id="unit_id" class="form-control" required>
                <option value="" disabled selected>-- Select Unit --</option>
                @foreach($units as $unit)
                  <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>Purchase Price</label>
              <input type="number" step="any" name="purchase_price" class="form-control" value="{{ old('purchase_price', '0.00') }}">
              @error('purchase_price')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label>Selling Price</label>
              <input type="number" step="any" name="selling_price" class="form-control" value="{{ old('selling_price', '0.00') }}">
              @error('selling_price')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>Bilty Charges</label>
              <input type="number" step="any" name="bilty_charges" class="form-control" value="{{ old('bilty_charges', '0.00') }}">
              @error('bilty_charges')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control" value="{{ old('opening_stock', '0') }}">
              @error('opening_stock')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>Reorder Level</label>
              <input type="number" step="any" name="reorder_level" class="form-control" value="{{ old('reorder_level', '0') }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Max Stock Level</label>
              <input type="number" step="any" name="max_stock_level" class="form-control" value="{{ old('max_stock_level', '0') }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Minimum Order Qty</label>
              <input type="number" step="any" name="minimum_order_qty" class="form-control" value="{{ old('minimum_order_qty', '0') }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Status</label>
              <select name="is_active" class="form-control">
                <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-4 mt-3">
              <label>Description</label>
              <textarea name="description" class="form-control">{{ old('description') }}</textarea>
              @error('description')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6 mt-3">
              <label>Product Images</label>
              <input type="file" name="prod_att[]" multiple class="form-control" id="imageUpload">
              @error('prod_att')<div class="text-danger">{{ $message }}</div>@enderror

              <!-- 👇 Place preview container right under input -->
              <div id="previewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>
            </div>
          </div>

          <div class="col-12 mt-4">
            <section class="card">
              <header class="card-header">
                <div style="display: flex;justify-content: space-between;">
                  <h2 class="card-title">Parts</h2>
                  <div>
                    <button type="button" class="btn btn-primary btn-sm mt-1" onclick="addPartRow()">
                      <i class="fa fa-plus"></i> Add Part
                    </button>
                  </div>
                </div>
              </header>

              <div class="card-body">
                <table class="table table-bordered" id="partsTable">
                  <thead>
                    <tr>
                      <th>Part</th>
                      <th>Qty</th>
                      <th width="10%">Action</th>
                    </tr>
                  </thead>

                  <tbody id="partsTableBody">
                    <tr class="part-row">
                      <td>
                        <select name="parts[0][part_id]" class="form-control select2-js part-select" onchange="onPartChange(this)">
                          <option value="" disabled selected>Select Part</option>
                          @foreach($allProducts as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                          @endforeach
                        </select>
                      </td>

                      <td>
                        <input type="number" step="any" name="parts[0][quantity]" class="form-control" value="0">
                      </td>

                      <td class="part-actions">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removePartRow(this)">
                          <i class="fas fa-times"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </div>
        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">Create Product</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let partIndex = 1;

  
  $(document).ready(function () {
    $('.select2-js').select2();

    // --- SKU Auto-generation Logic ---
    $('input[name="name"]').on('input', function() {
      let name = $(this).val();
      // Replace spaces with hyphens and convert to uppercase
      let sku = name.trim().replace(/\s+/g, '-').toUpperCase();
      $('#sku').val(sku);
    });

    document.getElementById("imageUpload").addEventListener("change", function(event) {
      const files = event.target.files;
      const previewContainer = document.getElementById("previewContainer");

      Array.from(files).forEach((file, index) => {
          if (file && file.type.startsWith("image/")) {
              const reader = new FileReader();
              reader.onload = function(e) {
                  // wrapper div
                  const wrapper = document.createElement("div");
                  wrapper.style.position = "relative";
                  wrapper.style.display = "inline-block";

                  // image element
                  const img = document.createElement("img");
                  img.src = e.target.result;
                  img.style.maxWidth = "150px";
                  img.style.maxHeight = "150px";
                  img.style.border = "1px solid #ddd";
                  img.style.borderRadius = "5px";
                  img.style.padding = "5px";

                  // remove button
                  const removeBtn = document.createElement("span");
                  removeBtn.innerHTML = "&times;";
                  removeBtn.style.position = "absolute";
                  removeBtn.style.top = "2px";
                  removeBtn.style.right = "6px";
                  removeBtn.style.cursor = "pointer";
                  removeBtn.style.color = "red";
                  removeBtn.style.fontSize = "20px";
                  removeBtn.style.fontWeight = "bold";
                  removeBtn.title = "Remove";

                  // remove handler
                  removeBtn.addEventListener("click", function() {
                      wrapper.remove();

                      // 👇 clear the input if all images removed
                      if (previewContainer.children.length === 0) {
                          document.getElementById("imageUpload").value = "";
                      }
                  });

                  // append
                  wrapper.appendChild(img);
                  wrapper.appendChild(removeBtn);
                  previewContainer.appendChild(wrapper);
              };
              reader.readAsDataURL(file);
          }
      });
    });

  });

  // ➕ Add new part row
  function addPartRow() {
    const tbody = document.getElementById('partsTableBody');

    const row = document.createElement('tr');
    row.classList.add('part-row');

    row.innerHTML = `
      <td>
        <select name="parts[${partIndex}][part_id]" class="form-control select2-js part-select" onchange="onPartChange(this)">
          <option value="" disabled selected>Select Part</option>
          @foreach($allProducts as $product)
            <option value="{{ $product->id }}">{{ $product->name }}</option>
          @endforeach
        </select>
      </td>

      <td>
        <input type="number" step="any" name="parts[${partIndex}][quantity]" class="form-control" value="1" required>
      </td>

      <td class="part-actions">
        <button type="button" class="btn btn-danger btn-sm" onclick="removePartRow(this)">
          <i class="fas fa-times"></i>
        </button>
      </td>
    `;

    tbody.appendChild(row);
    partIndex++;

    $('.select2-js').select2();
  }

  // ❌ Remove part row
  function removePartRow(btn) {
    const rows = document.querySelectorAll('#partsTableBody tr');
    if (rows.length > 1) {
      btn.closest('tr').remove();
    }
  }

</script>
@endsection
