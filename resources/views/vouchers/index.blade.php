@extends('layouts.app')

@section('title', ucfirst($type) . ' Vouchers')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">{{ ucfirst($type) }} Vouchers</h2>
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
      </header>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="datatable-default">
            <thead>
              <tr>
                <th>Voch#</th>
                <th>Date</th>
                <th>Account Debit</th>
                <th>Account Credit</th>
                <th>Remarks</th>
                <th>Amount</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($vouchers as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-Y') }}</td>
                  <td>{{ $row->debitAccount->name ?? 'N/A' }}</td>
                  <td>{{ $row->creditAccount->name ?? 'N/A' }}</td>
                  <td>{{ $row->remarks }}</td>
                  <td><strong>{{ number_format($row->amount, 0, '.', ',') }}</strong></td>
                  <td class="actions">
                    <a class="text-success" href="{{ route('vouchers.print', ['type' => $type, 'id' => $row->id]) }}"><i class="fas fa-print"></i></a>
                    <a class="text-primary modal-with-form" onclick="getVoucherDetails({{ $row->id }})" href="#updateModal"><i class="fas fa-edit"></i></a>
                    <a class="btn btn-link p-0 m-0 text-danger modal-with-form"  onclick="setDeleteId({{ $row->id }})" href="#deleteModal">
                      <i class="fas fa-trash-alt"></i>
                    </a>                  
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Add Voucher Modal -->
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="post" action="{{ route('vouchers.store', $type) }}" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
          @csrf
          <input type="hidden" name="voucher_type" value="{{ $type }}">
          <header class="card-header">
            <h2 class="card-title">Add {{ ucfirst($type) }} Voucher</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Date</label>
                <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Account Debit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_dr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $row)
                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Account Credit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_cr_sid" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts as $row)
                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount" step="any" value="0" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="att[]" multiple accept=".zip,application/pdf,image/png,image/jpeg">
              </div>

              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="3" class="form-control" name="remarks"></textarea>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Add {{ ucfirst($type) }} Voucher</button>
            <button class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

    <!-- Update Voucher Modal -->
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="updateForm" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <input type="hidden" name="voucher_type" value="{{ $type }}">

          <header class="card-header">
            <h2 class="card-title">Update {{ ucfirst($type) }} Voucher</h2>
          </header>

          <div class="card-body">
            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Voucher ID</label>
                <input type="text" class="form-control" id="update_id" disabled>
                <input type="hidden" name="voucher_id" id="update_id_hidden">
              </div>

              <div class="col-lg-6 mb-2">
                <label>Date</label>
                <input type="date" class="form-control" name="date" id="update_date" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Account Debit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_dr_sid" id="update_ac_dr_sid" required>
                  <option value="" disabled>Select Account</option>
                  @foreach($accounts as $row)
                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Account Credit <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="ac_cr_sid" id="update_ac_cr_sid" required>
                  <option value="" disabled>Select Account</option>
                  @foreach($accounts as $row)
                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount" id="update_amount" step="any" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="att[]" multiple accept=".zip,application/pdf,image/png,image/jpeg">
              </div>

              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="3" class="form-control" name="remarks" id="update_remarks"></textarea>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Update {{ ucfirst($type) }} Voucher</button>
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
            <h2 class="card-title">Delete Voucher</h2>
          </header>
          <div class="card-body">
            <div class="modal-wrapper">
              <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
              </div>
              <div class="modal-text">
                <p>Are you sure you want to delete this voucher? This action cannot be undone.</p>
              </div>
            </div>
            <footer class="card-footer text-end b-none">
              <button type="submit" class="btn btn-danger">Confirm Delete</button>
              <button class="btn btn-default modal-dismiss">Cancel</button>
            </footer>
          </div>
          
        </form>
      </section>
    </div>

  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
  });
  
  function getVoucherDetails(id) {
      document.getElementById('updateForm').action = `/vouchers/{{ $type }}/${id}`;
      fetch(`/vouchers/{{ $type }}/${id}`)
          .then(res => res.json())
          .then(data => {
              document.getElementById('update_id').value = id;
              document.getElementById('update_id_hidden').value = id;
              document.getElementById('update_date').value = data.date;
              $('#update_ac_dr_sid').val(data.ac_dr_sid).trigger('change');
              $('#update_ac_cr_sid').val(data.ac_cr_sid).trigger('change');
              document.getElementById('update_amount').value = data.amount;
              document.getElementById('update_remarks').value = data.remarks;
          });
  }

  function setDeleteId(id) {
      // We use the blade variable {{ $type }} to match your route structure: /vouchers/{type}/{id}
      const deleteUrl = `/vouchers/{{ $type }}/${id}`;
      document.getElementById('deleteForm').action = deleteUrl;
  }
</script>
@endsection
