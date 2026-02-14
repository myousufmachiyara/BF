@extends('layouts.app')

@section('title', 'Accounts | All COA')

@section('content')
    <div class="row">
        <div class="col">
            <section class="card">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                @endif
                <header class="card-header">
                    <div style="display: flex;justify-content: space-between;">
                        <h2 class="card-title">All Accounts</h2>
                        <div>
                            @can('coa.create')
                            <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
                                <i class="fas fa-plus"></i> Add Account
                            </button>
                            @endcan
                        </div>
                    </div>
                    @if ($errors->has('error'))
                        <strong class="text-danger">{{ $errors->first('error') }}</strong>
                    @endif
                </header>
                
                <div class="card-body">
                    <div>
                        <form method="GET" action="{{ route('coa.index') }}" class="mb-3 d-flex">
                            <div class="col-md-3">
                                <label> Filter By </label>
                                <select name="subhead" class="form-control" style="margin-right:10px" onchange="this.form.submit()">
                                    <option value="all" {{ request('subhead') == 'all' ? 'selected' : '' }}>All</option>
                                    @foreach($subHeadOfAccounts as $sub)
                                        <option value="{{ $sub->id }}" {{ request('subhead') == $sub->id ? 'selected' : '' }}>
                                            {{ $sub->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-wrapper table-scroll">
                        <table class="table table-bordered table-striped mb-0" id="datatable-default">
                            <thead>
                                <tr>
                                    <th>S.NO</th>
                                    <th>Code</th>
                                    <th>A/C Name</th>
                                    <th>SubHead</th>
                                    <th>A/C Type</th>
                                    <th>Visibility</th>
                                    <th>Phone</th>
                                    <th>Date</th>
                                    <th>Remarks</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($chartOfAccounts as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->account_code }}</td>
                                    <td><strong>{{ $item->name }}</strong></td>
                                    <td>{{ $item->subHeadOfAccount->name }}</td>
                                    <td><strong>{{ $item->account_type }}</strong></td>
                                    <td>
                                        @if($item->visibility === 'private')
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-lock"></i> Private
                                            </span>
                                        @else
                                            <span class="badge bg-success">
                                                <i class="fas fa-globe"></i> Public
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $item->phone_no }}</td>
                                    <td>{{ \Carbon\Carbon::parse($item->opening_date)->format('d-m-y') }}</td>
                                    <td>{{ $item->remarks }}</td>
                                    <td>
                                        @can('coa.edit')
                                            <a href="#" class="text-primary" onclick="editAccount({{ $item->id }})"><i class="fa fa-edit"></i></a>
                                        @endcan

                                        @can('coa.delete')
                                            <form action="{{ route('coa.destroy', $item->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this account? This action cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete Account">
                                                    <i class="fa fa-trash-alt"></i>
                                                </button>
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
            @can('coa.create')
            <div id="addModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="post" id="addForm" action="{{ route('coa.store') }}" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                        @csrf
                        <header class="card-header">
                            <h2 class="card-title">Add New Account</h2>
                        </header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-6 mb-2">
                                    <label>Account Name<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="text" class="form-control" placeholder="Account Name" name="name" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Account Type</label>
                                    <select data-plugin-selecttwo class="form-control select2-js" name="account_type">
                                        <option value="" selected>Select Account Type</option>
                                        <option value="customer">Customer</option>
                                        <option value="vendor">Vendor</option>
                                        <option value="asset">Asset (Inventory/Fixed Assets)</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="expenses">Expenses</option>
                                        <option value="revenue">Revenue</option>
                                        <option value="equity">Equity</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>SubHead Of Account<span style="color: red;"><strong>*</strong></span></label>
                                    <select data-plugin-selecttwo class="form-control select2-js"  name="shoa_id" required>
                                        <option value="" disabled selected>Select Account SubHead</option>
                                        @foreach($subHeadOfAccounts as $row)	
                                            <option value="{{$row->id}}">{{$row->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Visibility<span style="color: red;"><strong>*</strong></span></label>
                                    <select class="form-control" name="visibility" required>
                                        <option value="public" selected>Public (All Staff)</option>
                                        <option value="private">Private (Superadmin Only)</option>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Private accounts are only visible to superadmins
                                    </small>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Receivables<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Receivables" value="0" name="receivables" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Payables<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Payables" value="0" name="payables" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Credit Limit<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Credit Limit" value="0" name="credit_limit" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Date</label>
                                    <input type="date" class="form-control" placeholder="Date" name="opening_date" value="{{ date('Y-m-d') }}" required>
                                </div>  
                                <div class="col-lg-6 mb-2">
                                    <label>Remarks</label>
                                    <input type="text" class="form-control"  placeholder="Remarks" name="remarks" >
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Address</label>
                                    <textarea class="form-control" rows="2" placeholder="Address" name="address"></textarea>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Phone No.</label>
                                    <input type="text" class="form-control"  placeholder="Phone No." name="phone_no" >
                                </div>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="row">
                                <div class="col-md-12 text-end">
                                    <button type="submit" class="btn btn-primary">Add New Account</button>
                                    <button class="btn btn-default modal-dismiss">Cancel</button>
                                </div>
                            </div>
                        </footer>
                    </form>
                </section>
            </div>
            @endcan

            {{-- EDIT MODAL --}}
            @can('coa.edit')
            <div id="editModal" class="modal-block modal-block-primary mfp-hide">
                <section class="card">
                    <form method="post" id="editForm" action="" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                        @csrf
                        @method('PUT')

                        <header class="card-header">
                            <h2 class="card-title">Edit Account</h2>
                        </header>
                        <div class="card-body">
                            <div class="row form-group">
                                <div class="col-lg-6 mb-2">
                                    <label>Account Name<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="text" class="form-control" placeholder="Account Name" name="name" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Account Type</label>
                                    <select data-plugin-selecttwo class="form-control select2-js" name="account_type" required>
                                        <option value="" disabled>Select Account Type</option>
                                        @php
                                            $types = ['customer','vendor','cash','bank','expenses','revenue','equity'];
                                        @endphp
                                        @foreach($types as $type)
                                            <option value="{{ $type }}" {{ (isset($account) && $account->account_type == $type) ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Visibility<span style="color: red;"><strong>*</strong></span></label>
                                    <select class="form-control" name="visibility" required>
                                        <option value="public">Public (All Staff)</option>
                                        <option value="private">Private (Superadmin Only)</option>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Private accounts are only visible to superadmins
                                    </small>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>SubHead Of Account<span style="color: red;"><strong>*</strong></span></label>
                                    <select data-plugin-selecttwo class="form-control select2-js" name="shoa_id" required>
                                        <option value="" disabled selected>Select Account SubHead</option>
                                        @foreach($subHeadOfAccounts as $row)	
                                            <option value="{{$row->id}}">{{$row->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Receivables<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Receivables" value="0" name="receivables" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Payables<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Payables" value="0" name="payables" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Credit Limit<span style="color: red;"><strong>*</strong></span></label>
                                    <input type="number" class="form-control" placeholder="Credit Limit" value="0" name="credit_limit" step="any" required>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Date</label>
                                    <input type="date" class="form-control" name="opening_date" value="{{ date('Y-m-d') }}" required>
                                </div>  
                                <div class="col-lg-6 mb-2">
                                    <label>Remarks</label>
                                    <input type="text" class="form-control" placeholder="Remarks" name="remarks" >
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Address</label>
                                    <textarea class="form-control" rows="2" placeholder="Address" name="address"></textarea>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <label>Phone No.</label>
                                    <input type="text" class="form-control" placeholder="Phone No." name="phone_no" >
                                </div>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <div class="row">
                                <div class="col-md-12 text-end">
                                    <button type="submit" class="btn btn-primary">Update Account</button>
                                    <button class="btn btn-default modal-dismiss">Cancel</button>
                                </div>
                            </div>
                        </footer>
                    </form>
                </section>
            </div>
            @endcan
        </div>
    </div>

    <script>
        function editAccount(id) {
            fetch('/coa/' + id + '/edit')
            .then(res => res.json())
            .then(data => {
                $('#editForm').attr('action', '/coa/' + id);
                
                // Use jQuery selectors scoped to edit form
                $('#editForm [name="name"]').val(data.name);
                $('#editForm [name="account_type"]').val(data.account_type).trigger('change');
                $('#editForm [name="shoa_id"]').val(data.shoa_id).trigger('change');
                $('#editForm [name="visibility"]').val(data.visibility); // ✅ NEW FIELD
                $('#editForm [name="receivables"]').val(data.receivables);
                $('#editForm [name="payables"]').val(data.payables);
                $('#editForm [name="credit_limit"]').val(data.credit_limit);
                $('#editForm [name="opening_date"]').val(data.opening_date);
                $('#editForm [name="remarks"]').val(data.remarks);
                $('#editForm [name="address"]').val(data.address);
                $('#editForm [name="phone_no"]').val(data.phone_no);

                $.magnificPopup.open({
                    items: { src: '#editModal' },
                    type: 'inline'
                });
            });
        }
    </script>
@endsection
