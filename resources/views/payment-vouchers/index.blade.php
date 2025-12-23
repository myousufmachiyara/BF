@extends('layouts.app')

@section('title', 'Payments | All Vouchers')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between">
        <h2 class="card-title">Payment Vouchers</h2>
        <div>
          <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
            <i class="fas fa-plus"></i> Add New
          </button>
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
           <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
                <thead>
                    <tr>
                        <th width="5%">Voch#</th>
                        <th width="8%">Date</th>
                        <th width="15%">Account Debit</th>
                        <th width="15%">Account Credit</th>
                        <th width="30%">Remarks</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jv1 as $key => $row)
                        <tr>
                            <td>{{$row->id}}</td>
                            <td>{{ \Carbon\Carbon::parse($row->date)->format('d-m-y') }}</td>
                            <td>{{ $row->debitAccount->name ?? 'N/A' }}</td>
                            <td>{{ $row->creditAccount->name ?? 'N/A' }}</td>
                            <td >{{$row->remarks}}</td>
                            @if (strpos($row->amount, '.') !== false && substr($row->amount, strpos($row->amount, '.') + 1) > '0')
                                <td><strong style="font-size:15px">{{ number_format($row->amount, 0, '.', ',') }}</strong></td>
                            @else
                                <td><strong style="font-size:15px">{{ number_format($row->amount, 0, '.', ',') }}</strong></td>
                            @endif
                            <td class="actions">
                                <a class="mb-1 mt-1 me-1 text-success" href="{{ route('payment_vouchers.print', $row->id) }}">
                                    <i class="fas fa-print"></i>
                                </a>
                                <span class="separator"> | </span>
                                <a class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal modal-with-form" onclick="getJVSDetails({{$row->id}})" href="#updateModal">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                <span class="separator"> | </span>
                                <a class="mb-1 mt-1 me-1 modal-with-zoom-anim ws-normal" onclick="setId({{$row->id}})" href="#deleteModal">
                                    <i class="far fa-trash-alt" style="color:red"></i>
                                </a>
                            </td>       
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
      </div>
    </section>

    <!-- Edit Modal -->

    <div id="updateModal" class="modal-block modal-block-primary mfp-hide" style="z-index: 1050">
        <section class="card">
            <form method="POST" id="updateForm" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                @csrf
                @method('PUT')

                <header class="card-header">
                    <h2 class="card-title">Update Journal Voucher</h2>
                </header>

                <div class="card-body">
                    <div class="row form-group">
                        <div class="col-lg-6">
                            <label>JV1 Code</label>
                            <input type="text" class="form-control" id="update_id" disabled>
                            <input type="hidden" name="voucher_id" id="update_id_hidden">
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Date</label>
                            <input type="date" class="form-control" name="date" id="update_date" required>
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Account Debit<span style="color: red;"><strong>*</strong></span></label>
                            <select class="form-control select2-js" name="ac_dr_sid" id="update_ac_dr_sid" required>
                                <option value="" disabled selected>Select Account</option>
                                @foreach($acc as $row)
                                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Account Credit<span style="color: red;"><strong>*</strong></span></label>
                            <select class="form-control select2-js" name="ac_cr_sid" id="update_ac_cr_sid" required>
                                <option value="" disabled selected>Select Account</option>
                                @foreach($acc as $row)
                                    <option value="{{ $row->id }}">{{ $row->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Amount<span style="color: red;"><strong>*</strong></span></label>
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

                <footer class="card-footer">
                    <div class="row">
                        <div class="col-md-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Journal Voucher</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </div>
                    </div>
                </footer>
            </form>
        </section>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
        <section class="card">
            <form method="post" action="{{ route('payment_vouchers.store') }}" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                @csrf
                <header class="card-header d-flex align-items-center">
                    <h2 class="card-title">Add Journal Voucher</h2>
                </header>
                
                <div class="card-body">
                    <div class="row form-group">
                        <div class="col-lg-6">
                            <label>Voucher Code</label>
                            <input type="number" class="form-control" placeholder="Voucher Code" disabled>
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Date</label>
                            <input type="date" class="form-control" placeholder="Date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Account Debit<span style="color: red;"><strong>*</strong></span></label>
                            <select data-plugin-selecttwo class="form-control select2-js" name ="ac_dr_sid" required>
                                <option value="" disabled selected>Select Account</option>
                                @foreach($acc as $key => $row)	
                                    <option value="{{$row->id}}">{{$row->name}}</option>
                                @endforeach
                            </select>
                        </div>
                              <div class="col-lg-6 mb-2">
                            <label>Account Credit<span style="color: red;"><strong>*</strong></span></label>

                            <select  data-plugin-selecttwo class="form-control select2-js" name ="ac_cr_sid" required>
                                <option value="" disabled selected>Select Account</option>
                                @foreach($acc as $key => $row)	
                                    <option value="{{$row->id}}">{{$row->name}}</option>
                                @endforeach
                            </select> 
                        </div>
                        <div class="col-lg-6 mb-2">
                            <label>Amount<span style="color: red;"><strong>*</strong></span></label>
                            <input type="number" class="form-control" placeholder="Amount" value="0" step="any" name="amount" required>
                        </div>

                        <div class="col-lg-6 mb-2">
                            <label>Attachments</label>
                            <input type="file" class="form-control" name="att[]" multiple accept=".zip, appliation/zip, application/pdf, image/png, image/jpeg">
                        </div>  
                        <div class="col-lg-12 mb-2">
                            <label>Remarks</label>
                            <textarea rows="4" cols="50" class="form-control cust-textarea" placeholder="Remarks" name="remarks"> </textarea>                            </div>
                        </div>
                    </div>
                    <footer class="card-footer">
                        <div class="row">
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">Add Journal Voucher</button>
                                <button class="btn btn-default modal-dismiss">Cancel</button>
                            </div>
                        </div>
                    </footer>
                </div>
            </form>
        </section>
    </div>

  </div>
</div>
<script>
    function getJVSDetails(id) {
        // Set the form action
        document.getElementById('updateForm').action = `/payment_vouchers/${id}`;

        // Fetch existing voucher data via AJAX
        fetch(`/payment_vouchers/${id}`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('update_id').value = id;
                document.getElementById('update_id_hidden').value = id;
                document.getElementById('update_date').value = data.date;
                document.getElementById('update_ac_dr_sid').value = data.ac_dr_sid;
                document.getElementById('update_ac_cr_sid').value = data.ac_cr_sid;
                document.getElementById('update_amount').value = data.amount;
                document.getElementById('update_remarks').value = data.remarks;

                // If you're using select2, trigger change
                $('#update_ac_dr_sid').val(data.ac_dr_sid).trigger('change');
                $('#update_ac_cr_sid').val(data.ac_cr_sid).trigger('change');
            })
            .catch(err => console.error('Failed to load voucher:', err));
    }
</script>
@endsection
