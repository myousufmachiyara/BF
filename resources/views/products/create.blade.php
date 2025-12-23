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
              <label>Sub Category</label>
              <select name="subcategory_id" id="subcategory_id" class="form-control">
                <option value="">Select Sub Category</option>
              </select>
              @error('subcategory_id')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label>SKU *</label>
              <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku') }}" required>
              @error('sku')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
              <label>Item Type</label>
              <select name="item_type" class="form-control" required>
                <option value="" disabled selected>Item Type</option>
                <option value="fg">F.G</option>
                <option value="raw">Raw</option>
              </select>
              @error('item_type')<div class="text-danger">{{ $message }}</div>@enderror
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

            <div class="col-md-2 mt-3">
              <label>Consumption</label>
              <input type="number" step="any" name="consumption" class="form-control" value="{{ old('consumption', '0') }}">
              @error('consumption')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>M.Cost</label>
              <input type="number" step="any" name="manufacturing_cost" class="form-control" value="{{ old('manufacturing_cost', '0.00') }}">
              @error('price')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>Selling Price</label>
              <input type="number" step="any" name="selling_price" class="form-control" value="{{ old('selling_price', '0.00') }}">
              @error('selling_price')<div class="text-danger">{{ $message }}</div>@enderror
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

          {{-- Attribute Selection --}}
          <div class="row mt-4">
            <div class="col-md-12">
              <h2 class="card-title">Product Variations</h2>
              <div class="row">
                @foreach($attributes as $attribute)
                  <div class="col-md-6">
                    <label>{{ $attribute->name }}</label>
                    <select name="attributes[{{ $attribute->id }}][]" multiple class="form-control select2-js variation-select" data-attribute="{{ $attribute->id }}">
                      @foreach($attribute->values as $value)
                        <option value="{{ $value->id }}">{{ $value->value }}</option>
                      @endforeach
                    </select>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

          {{-- Generate Button and Table --}}
          <div class="col-md-12 mt-4">
            <button type="button" class="btn btn-success mb-3" id="generateVariationsBtn">
              <i class="fa fa-plus"></i> Generate Variations
            </button>

            <div class="table-responsive">
              <table class="table table-bordered" id="variationsTable">
                <thead>
                  <tr>
                    <th>Variation</th>
                    <th>Stock</th>
                    <th>M.Cost</th>
                    <th>SKU</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {{-- JS will generate rows here --}}
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-12 mt-4">
            <section class="card">
              <header class="card-header">
                <div style="display: flex;justify-content: space-between;">
                  <h2 class="card-title">Product Parts / Raw Materials</h2>
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
                      <th>Variation</th>
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
                        <select name="parts[0][part_variation_id]" class="form-control select2-js part-variation-select">
                          <option value="">Select Variation</option>
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

    $('#generateVariationsBtn').click(function () {
      let attributes = {!! $attributes->toJson() !!};
      let selectedMap = {};

      attributes.forEach(attr => {
        let selected = $(`select[name="attributes[${attr.id}][]"]`).val();
        if (selected && selected.length > 0) {
          selectedMap[attr.name] = selected.map(valId => {
            let text = $(`select[name="attributes[${attr.id}][]"] option[value="${valId}"]`).text();
            return { id: valId, text: text };
          });
        }
      });

      let combos = buildCombinations(Object.entries(selectedMap));
      let tbody = $('#variationsTable tbody');
      tbody.empty();

      let mainSku = $('#sku').val();

      combos.forEach((combo, index) => {
        let label = combo.map(c => c.text).join(' - ');
        let inputs = combo.map((c, i) => `
          <input type="hidden" name="variations[${index}][attributes][${i}][attribute_value_id]" value="${c.id}">
        `).join('');

        tbody.append(`
          <tr>
            <td>${label}${inputs}</td>
            <td><input type="number" name="variations[${index}][stock_quantity]" step="any" class="form-control" value="0" required></td>
            <td><input type="number" name="variations[${index}][manufacturing_cost]" step="any" class="form-control" value="0" required></td>
            <td><input type="text" name="variations[${index}][sku]" class="form-control" value="${mainSku}-${label}"></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-variation">X</button></td>
          </tr>
        `);
      });
    });

    $(document).on('click', '.remove-variation', function () {
      $(this).closest('tr').remove();
    });

    function buildCombinations(arr, index = 0) {
      if (index === arr.length) return [[]];
      let [key, values] = arr[index];
      let rest = buildCombinations(arr, index + 1);
      return values.flatMap(v => rest.map(r => [v, ...r]));
    }

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

    $('select[name="category_id"]').on('change', function () {
      let categoryId = $(this).val();
      let subCategorySelect = $('#subcategory_id');

      subCategorySelect.empty().append('<option value="">Loading...</option>');

      if (categoryId) {
        $.ajax({
          url: "{{ route('products.getSubcategories', ':id') }}".replace(':id', categoryId),
          type: "GET",
          success: function (data) {
            subCategorySelect.empty();
            subCategorySelect.append('<option value="">Select Sub Category</option>');

            if (data.length > 0) {
              $.each(data, function (key, subcat) {
                subCategorySelect.append(
                  `<option value="${subcat.id}">${subcat.name}</option>`
                );
              });
            }
          }
        });
      } else {
        subCategorySelect.empty().append('<option value="">Select Sub Category</option>');
      }
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
        <select name="parts[${partIndex}][part_variation_id]" class="form-control select2-js part-variation-select">
          <option value="">Select Variation</option>
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

  // 🔄 Load variations for selected part
  function onPartChange(select) {
    const productId = select.value;
    const row = select.closest('tr');
    const variationSelect = row.querySelector('.part-variation-select');

    variationSelect.innerHTML = '<option value="">Loading...</option>';
    variationSelect.disabled = true;

    if (!productId) {
        variationSelect.innerHTML = '<option value="">Select variation</option>';
        return;
    }

    fetch(`/product/${productId}/variations`)
      .then(res => res.json())
      .then(data => {
          variationSelect.innerHTML = '<option value="">No variation</option>';

          if (data.success && data.variation.length > 0) {
              data.variation.forEach(v => {
                  variationSelect.innerHTML +=
                      `<option value="${v.id}">${v.sku}</option>`;
              });
              variationSelect.disabled = false;
          }
      })
      .catch(() => {
        variationSelect.innerHTML = '<option value="">Error loading</option>';
      });
  }

</script>
@endsection
