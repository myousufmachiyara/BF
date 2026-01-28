@extends('layouts.app')

@section('title', 'Finance | PDC Management')

@section('content')
    <div class="row">
        <div class="col">
            <section class="card">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <header class="card-header">
                    <div style="display: flex; justify-content: space-between;">
                        <h2 class="card-title">Post-Dated Cheques (PDC)</h2>
                        <div>
                            @can('pdc.create')
                            <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                                <i class="fas fa-plus"></i> Receive Cheque
                            </button>
                            @endcan
                        </div>
                    </div>
                </header>
                
                <div class="card-body">
                    <div class="modal-wrapper table-scroll">
                        <table class="table table-bordered table-striped mb-0" id="datatable-default">
                            <thead>
                                <tr>
                                    <th>S.NO</th>
                                    <th>Cheque Date</th>
                                    <th>Cheque #</th>
                                    <th>Bank Name</th>
                                    <th>Received From</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cheques as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ \Carbon\Carbon::parse($item->cheque_date)->format('d-m-Y') }}</strong>
                                        @if($item->status == 'received' && $item->cheque_date <= date('Y-m-d'))
                                            <span class="badge badge-danger">Due</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->cheque_number }}</td>
                                    <td>{{ $item->bank_name }}</td>
                                    <td>{{ $item->received_from }}</td>
                                    <td>{{ number_format($item->amount, 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $item->status == 'cleared' ? 'success' : ($item->status == 'bounced' ? 'danger' : ($item->status == 'deposited' ? 'warning' : 'dark')) }}">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        {{-- Lifecycle Actions --}}
                                        @if($item->status == 'received')
                                            <form action="{{ route('pdc.deposit', $item->id) }}" method="POST" class="d-inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-link p-0 m-0 text-warning" title="Deposit"><i class="fas fa-university"></i></button>
                                            </form>
                                        @endif

                                        @if($item->status == 'deposited')
                                            <form action="{{ route('pdc.clear', $item->id) }}" method="POST" class="d-inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-link p-0 m-0 text-success" title="Clear"><i class="fas fa-check-circle"></i></button>
                                            </form>
                                        @endif

                                        {{-- Edit & Delete --}}
                                        @can('pdc.edit')
                                            <a href="#" class="text-primary ml-2" onclick="editPDC({{ $item->id }})"><i class="fa fa-edit"></i></a>
                                        @endcan

                                        @can('pdc.delete')
                                            <form action="{{ route('pdc.destroy', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 m-0 text-danger"><i class="fa fa-trash-alt"></i></button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ADD MODAL --}}
            @can('pdc.create')
            <div id="addModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="post" action="{{ route('pdc.store') }}" onkeydown="return event.key != 'Enter';">
                        @csrf
                        <header class="card-header"><h2 class="card-title">Receive New PDC</h2></header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-6 mb-2">
                                    <label>Cheque Number<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="cheque_number" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Cheque Date<span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="cheque_date" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Amount<span class="text-danger">*</span></label>
                                    <input type="number" step="any" class="form-control" name="amount" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Bank Name<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="bank_name" required>
                                </div>
                                <div class="col-lg-12 mb-2">
                                    <label>Received From (Account)<span class="text-danger">*</span></label>
                                    <select data-plugin-selecttwo class="form-control select2-js" name="coa_id" required>
                                        <option value="" disabled selected>Select Account</option>
                                        @foreach($chartOfAccounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->name }} ({{ $account->account_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <footer class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Save Cheque</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </footer>
                    </form>
                </section>
            </div>
            @endcan

            {{-- EDIT MODAL --}}
            @can('pdc.edit')
            <div id="editModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="post" id="editForm" action="">
                        @csrf @method('PUT')
                        <header class="card-header"><h2 class="card-title">Edit PDC Record</h2></header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-6 mb-2">
                                    <label>Cheque Number</label>
                                    <input type="text" class="form-control" name="cheque_number" id="edit_cheque_number">
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Amount</label>
                                    <input type="number" step="any" class="form-control" name="amount" id="edit_amount">
                                </div>
                                {{-- Add other fields as needed --}}
                            </div>
                        </div>
                        <footer class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">Update Record</button>
                            <button class="btn btn-default modal-dismiss">Cancel</button>
                        </footer>
                    </form>
                </section>
            </div>
            @endcan
        </div>
    </div>

    <script>
        function editPDC(id) {
            fetch('/pdc/' + id + '/edit')
                .then(res => res.json())
                .then(data => {
                    $('#editForm').attr('action', '/pdc/' + id);
                    $('#edit_cheque_number').val(data.cheque_number);
                    $('#edit_amount').val(data.amount);
                    
                    // Set the COA dropdown and refresh Select2
                    $('[name="coa_id"]').val(data.coa_id).trigger('change');
                    
                    $.magnificPopup.open({
                        items: { src: '#editModal' },
                        type: 'inline'
                    });
                });
        }
    </script>
@endsection