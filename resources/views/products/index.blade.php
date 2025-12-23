@extends('layouts.app')

@section('title', 'Product | All Product')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header">
        <div style="display: flex;justify-content: space-between;">
          <h2 class="card-title">All Products</h2>
          <div>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUploadModal"><i class="fas fa-upload"></i> Bulk Upload</button>
            <a href="{{ route('products.barcode.selection') }}" class="btn btn-danger">Print Barcodes</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Products</a>
          </div>
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Image</th>
                <th>Item Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $index => $product)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                  @if($product->images->first())
                    <img src="{{ asset('storage/' . $product->images->first()->image_path) }}" width="60" height="60" style="object-fit:cover;border-radius:5px;">
                  @else
                    <span class="text-muted">No Image</span>
                  @endif
                </td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->sku }}</td>
                <td>
                  @if($product->category || $product->subcategory)
                    {{ $product->category->name ?? '' }}
                    @if(!empty($product->subcategory))
                      - {{ $product->subcategory->name }}
                    @endif
                  @else
                    -
                  @endif
                </td>
                <td>
                  <a href="{{ route('products.edit', $product->id) }}" class="text-primary"><i class="fa fa-edit"></i></a>
                  <form method="POST" action="{{ route('products.destroy', $product->id) }}" style="display:inline-block">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-link p-0 m-0 text-danger" onclick="return confirm('Delete this product?')" title="Delete"><i class="fa fa-trash-alt"></i></button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form action="{{ route('products.bulk-upload.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
              <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Products</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="file" class="form-label">Choose File</label>
                <input type="file" name="file" id="file" class="form-control" accept=".csv,.xlsx" required>
                <small class="text-danger">Allowed formats: CSV, XLSX</small>
              </div>
              <div class="mt-2">
                <a href="{{ route('products.bulk-upload.template') }}" class="btn btn-outline-primary btn-sm">
                  <i class="fas fa-download"></i> Download Template
                </a>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">
                <i class="fas fa-upload"></i> Upload
              </button>
            </div>
          </form>
        </div>
      </div>

    </section>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('#cust-datatable-default').DataTable({
      "pageLength": 100
    });
  });

</script>
@endsection
