@extends('layouts.app')

@section('title', 'Product | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form id="productForm" action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">Edit Product</h2>
        </header>
        <div class="card-body">
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif
          @if ($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="row pb-3">
            <div class="col-md-2">
              <label>Product Name *</label>
              <input type="text" name="name" class="form-control" required value="{{ old('name', $product->name) }}">
            </div>

            <div class="col-md-2">
              <label>Category *</label>
              <select name="category_id" class="form-control select2-js" required>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}" {{ $product->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>SKU</label>
              <input type="text" name="sku" id="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
            </div>

            <div class="col-md-2">
              <label>Measurement Unit *</label>
              <select name="measurement_unit" class="form-control" required>
                <option value="">-- Select Unit --</option>
                @foreach($units as $unit)
                  <option value="{{ $unit->id }}" {{ $product->measurement_unit == $unit->id ? 'selected' : '' }}>
                    {{ $unit->name }} ({{ $unit->shortcode }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label>Purchase Price</label>
              <input type="number" step="any" name="purchase_price" class="form-control" value="{{ old('purchase_price', $product->purchase_price) }}">
            </div>

            <div class="col-md-2">
              <label>Selling Price</label>
              <input type="number" step="any" name="selling_price" class="form-control" value="{{ old('selling_price', $product->selling_price) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Bilty Charges</label>
              <input type="number" step="any" name="bilty_charges" class="form-control" value="{{ old('bilty_charges', $product->bilty_charges) }}">
              @error('bilty_charges')<div class="text-danger">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mt-3">
              <label>Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control" value="{{ old('opening_stock', $product->opening_stock) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Min Order Qty</label>
              <input type="number" step="any" name="minimum_order_qty" class="form-control"
                    value="{{ old('minimum_order_qty', $product->minimum_order_qty) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Max Order Qty</label>
              <input type="number" step="any" name="max_stock_level" class="form-control"
                    value="{{ old('max_stock_level', $product->max_stock_level) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Reorder Level</label>
              <input type="number" step="any" name="reorder_level" class="form-control" value="{{ old('reorder_level', $product->reorder_level) }}">
            </div>

            <div class="col-md-2 mt-3">
              <label>Status</label>
              <select name="is_active" class="form-control">
                <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-4 mt-3">
              <label>Description</label>
              <textarea name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
            </div>

            <div class="col-md-6 mt-3">
              <label>Product Images</label>
              <input type="file" id="imageUpload" name="prod_att[]" multiple class="form-control">
              <small class="text-danger">Leave empty if you don't want to update images.</small>

              {{-- Existing images --}}
              <div id="existingImages" class="mt-2 d-flex flex-wrap">
                @foreach($product->images as $img)
                  <div class="existing-image-wrapper position-relative me-2 mb-2">
                    <img src="{{ asset('storage/' . $img->image_path) }}" width="120" height="120" style="object-fit:cover;border-radius:5px;" class="img-thumbnail">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 remove-existing-image" data-id="{{ $img->id }}">&times;</button>
                    <input type="hidden" name="keep_images[]" value="{{ $img->id }}">
                  </div>
                @endforeach
              </div>

              {{-- Preview newly selected images --}}
              <div id="previewContainer" class="mt-2 d-flex flex-wrap"></div>
            </div>
          </div>

          <div class="col-12 mt-4">
            <section class="card">
              <header class="card-header d-flex justify-content-between">
                <h2 class="card-title">Product Parts / Raw Materials</h2>
                <button type="button" class="btn btn-primary py-0" onclick="addPartRow()">
                  <i class="fa fa-plus"></i> Add Part
                </button>
              </header>

              <div class="card-body">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Part</th>
                      <th>Qty</th>
                      <th width="10%">Action</th>
                    </tr>
                  </thead>

                  <tbody id="partsTableBody">
                    @foreach($product->parts as $i => $part)
                    <tr class="part-row">
                      <td>
                        <select name="parts[{{ $i }}][part_id]"
                                class="form-control select2-js part-select"
                                onchange="onPartChange(this)">
                          <option value="">Select Part</option>
                          @foreach($allProducts as $p)
                            <option value="{{ $p->id }}"
                              {{ $p->id == $part->part_id ? 'selected' : '' }}>
                              {{ $p->name }}
                            </option>
                          @endforeach
                        </select>
                      </td>

                      <td>
                        <input type="number" step="any"
                              name="parts[{{ $i }}][quantity]"
                              class="form-control"
                              value="{{ $part->quantity }}">
                      </td>

                      <td>
                        <button type="button" class="btn btn-danger btn-sm"
                                onclick="removePartRow(this)">
                          ✕
                        </button>
                      </td>
                    </tr>
                    @endforeach

                    {{-- If no parts exist --}}
                    @if($product->parts->isEmpty())
                    <tr class="part-row">
                      <td>
                        <select name="parts[0][part_id]"
                                class="form-control select2-js part-select"
                                onchange="onPartChange(this)">
                          <option value="">Select Part</option>
                          @foreach($allProducts as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                          @endforeach
                        </select>
                      </td>
                     
                      <td>
                        <input type="number" step="any"
                              name="parts[0][quantity]"
                              class="form-control" value="1">
                      </td>

                      <td></td>
                    </tr>
                    @endif
                  </tbody>
                </table>
              </div>
            </section>
          </div>

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">Update Product</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>

  let partIndex = document.querySelectorAll('#partsTableBody .part-row').length;
  
  $(document).ready(function () {
    $('.select2-js').select2();

    // --- 1. SKU Auto-generation (Same as Create) ---
    $('input[name="name"]').on('input', function() {
      let name = $(this).val();
      // Replace spaces with hyphens and convert to uppercase
      let sku = name.trim().replace(/\s+/g, '-').toUpperCase();
      $('#sku').val(sku);
    });

    // Optional: Keep SKU field clean even if edited manually
    $('#sku').on('input', function() {
      $(this).val($(this).val().replace(/\s+/g, '-').toUpperCase());
    });

    document.getElementById("imageUpload").addEventListener("change", function(event) {
      const files = event.target.files;
      const previewContainer = document.getElementById("previewContainer");
      previewContainer.innerHTML = ""; // clear old previews

      Array.from(files).forEach((file) => {
          if (file && file.type.startsWith("image/")) {
              const reader = new FileReader();
              reader.onload = function(e) {
                  const wrapper = document.createElement("div");
                  wrapper.classList.add("position-relative", "me-2", "mb-2");

                  const img = document.createElement("img");
                  img.src = e.target.result;
                  img.classList.add("img-thumbnail");
                  img.style.width = "120px";
                  img.style.height = "120px";
                  img.style.objectFit = "cover";

                  const removeBtn = document.createElement("button");
                  removeBtn.type = "button";
                  removeBtn.classList.add("btn", "btn-sm", "btn-danger", "position-absolute", "top-0", "end-0");
                  removeBtn.innerHTML = "&times;";
                  removeBtn.onclick = () => wrapper.remove();

                  wrapper.appendChild(img);
                  wrapper.appendChild(removeBtn);
                  previewContainer.appendChild(wrapper);
              };
              reader.readAsDataURL(file);
          }
      });
    });

    // Handle removing existing images
    document.getElementById("existingImages").addEventListener("click", function(e) {
      if (!e.target.classList.contains("remove-existing-image")) return;

      const btn = e.target;
      const id = btn.dataset.id;
      const wrapper = btn.closest(".existing-image-wrapper");

      wrapper.style.display = "none";

      const hiddenKeep = wrapper.querySelector('input[name="keep_images[]"]');
      if (hiddenKeep) hiddenKeep.remove();

      const productForm = document.getElementById("productForm");
      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "removed_images[]";
      input.value = id;
      productForm.appendChild(input);
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
