@extends('layouts.app')

@section('title', 'Market Rates')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">Market Rates</h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="rates-datatable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Category</th>
                <th>Subcategory</th>
                <th>Shape</th>
                <th>Size</th>
                <th>Rate / Unit</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rates as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td>{{ $row->category->name ?? '-' }}</td>
                  <td>{{ $row->subcategory->name ?? '-' }}</td>
                  <td>{{ $row->shape->value ?? '-' }}</td>
                  <td>{{ $row->size->value ?? '-' }}</td>
                  <td><strong>{{ number_format($row->rate, 2) }}</strong></td>
                  <td class="actions">
                    <a class="text-primary modal-with-form" onclick="getRateDetails({{ $row->id }})" href="#updateModal"><i class="fas fa-edit"></i></a>
                    <a class="btn btn-link p-0 m-0 text-danger" onclick="setDeleteId({{ $row->id }})" href="#deleteModal"><i class="fas fa-trash-alt"></i></a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Add Rate Modal -->
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('market_rates.store') }}" onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">Add Market Rate</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Category <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="category_id" id="add_category_id" required>
                  <option value="" disabled selected>Select Category</option>
                  @foreach($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Subcategory</label>
                <select class="form-control select2-js" name="subcategory_id" id="add_subcategory_id">
                  <option value="">Select Subcategory</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Shape</label>
                <select class="form-control select2-js" name="shape_id" id="add_shape_id">
                  <option value="">Select Shape</option>
                  @foreach($shapes as $s)
                    <option value="{{ $s->id }}">{{ $s->value }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Size</label>
                <select class="form-control select2-js" name="size_id" id="add_size_id">
                  <option value="">Select Size</option>
                  @foreach($sizes as $sz)
                    <option value="{{ $sz->id }}">{{ $sz->value }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Rate / Unit <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="rate" step="any" required>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Add Rate</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Update Rate Modal -->
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="updateForm" onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')

          <header class="card-header">
            <h2 class="card-title">Update Market Rate</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Rate ID</label>
                <input type="text" class="form-control" id="update_id" disabled>
                <input type="hidden" name="rate_id" id="update_id_hidden">
              </div>

              <div class="col-lg-6 mb-2">
                <label>Category <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="category_id" id="update_category_id" required>
                  <option value="" disabled>Select Category</option>
                  @foreach($categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Subcategory</label>
                <select class="form-control select2-js" name="subcategory_id" id="update_subcategory_id">
                  <option value="">Select Subcategory</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Shape</label>
                <select class="form-control select2-js" name="shape_id" id="update_shape_id">
                  <option value="">Select Shape</option>
                  @foreach($shapes as $s)
                    <option value="{{ $s->id }}">{{ $s->value }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Size</label>
                <select class="form-control select2-js" name="size_id" id="update_size_id">
                  <option value="">Select Size</option>
                  @foreach($sizes as $sz)
                    <option value="{{ $sz->id }}">{{ $sz->value }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Rate / Unit <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="rate" id="update_rate" step="any" required>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update Rate</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal-block modal-block-warning mfp-hide">
      <section class="card">
        <form method="POST" id="deleteForm">
          @csrf
          @method('DELETE')
          <header class="card-header">
            <h2 class="card-title">Delete Rate</h2>
          </header>
          <div class="card-body">
            <p>Are you sure you want to delete this rate?</p>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-danger">Delete</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<script>
  function getRateDetails(id) {
      document.getElementById('updateForm').action = `/market_rates/${id}`;
      fetch(`/market_rates/${id}`)
          .then(res => res.json())
          .then(data => {
              document.getElementById('update_id').value = id;
              document.getElementById('update_id_hidden').value = id;

              // Set category first
              $('#update_category_id').val(data.category_id).trigger('change');

              // Load subcategories of this category
              fetch(`/get-subcategories/${data.category_id}`)
                  .then(res => res.json())
                  .then(subs => {
                      let options = '<option value="">Select Subcategory</option>';
                      subs.forEach(sc => {
                          options += `<option value="${sc.id}" ${sc.id == data.subcategory_id ? 'selected' : ''}>${sc.name}</option>`;
                      });
                      $('#update_subcategory_id').html(options).trigger('change');
                  });

              // Shapes & sizes
              $('#update_shape_id').val(data.shape_id).trigger('change');
              $('#update_size_id').val(data.size_id).trigger('change');

              // Rate
              document.getElementById('update_rate').value = data.rate;
          });
  }

  function setDeleteId(id) {
    document.getElementById('deleteForm').action = `/market_rates/${id}`;
  }

  // Dependent dropdown for Add Modal
  $('#add_category_id').on('change', function() {
      let categoryId = $(this).val();
      if (categoryId) {
          fetch(`/get-subcategories/${categoryId}`)
              .then(res => res.json())
              .then(data => {
                  let options = '<option value="">Select Subcategory</option>';
                  data.forEach(sc => {
                      options += `<option value="${sc.id}">${sc.name}</option>`;
                  });
                  $('#add_subcategory_id').html(options).trigger('change');
              });
      }
  });

  // Dependent dropdown for Update Modal
  $('#update_category_id').on('change', function() {
      let categoryId = $(this).val();
      if (categoryId) {
          fetch(`/get-subcategories/${categoryId}`)
              .then(res => res.json())
              .then(data => {
                  let options = '<option value="">Select Subcategory</option>';
                  data.forEach(sc => {
                      options += `<option value="${sc.id}">${sc.name}</option>`;
                  });
                  $('#update_subcategory_id').html(options).trigger('change');
              });
      }
  });
</script>
@endsection
