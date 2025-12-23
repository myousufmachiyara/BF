@extends('layouts.app')

@section('title', 'Productions | All Orders')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
      @elseif (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
      @endif

      <header class="card-header d-flex justify-content-between">
        <h2 class="card-title">All Productions</h2>
        <div>
          <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Production</a>
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="cust-datatable-default">
            <thead>
              <tr>
                <th>S.No</th>
                <th>PO Code</th>
                <th>Date</th>
                <th>Category</th>
                <th>Vendor</th>
                <th>Total Amount</th>
                <th>Attachments</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($productions as $production)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>PO-{{ $production->id }}</td>
                <td>{{ \Carbon\Carbon::parse($production->order_date)->format('d-m-Y') }}</td>
                <td>{{ $production->category->name ?? '-' }}</td>
                <td>{{ $production->vendor->name ?? '-' }}</td>
                <td>{{ number_format($production->total_amount ?? 0, 0) }}</td>
                <td>
                  @if($production->attachments && count($production->attachments))
                    @foreach($production->attachments as $file)
                      <a href="{{ asset('storage/' . $file) }}" target="_blank">📎</a>
                    @endforeach
                  @else
                    -
                  @endif
                </td>
                <td>
                  <a href="{{ route('production.summary', $production->id) }}" class="text-dark" title="Summary"><i class="fa fa-book"></i></a>
                  <a href="{{ route('production.gatepass', $production->id) }}" class="text-secondary" title="Gatepass"><i class="fa fa-tag"></i></a>
                  <a href="{{ route('production.print', $production->id) }}" class="text-success" title="Print"><i class="fa fa-print"></i></a>
                  <a href="{{ route('production.edit', $production->id) }}" class="text-primary" title="Edit"><i class="fa fa-edit"></i></a>
                  <a href="{{ route('production_receiving.create', ['id'=> $production->id]) }}" class="text-warning" title="Receive"><i class="fa fa-download"></i></a>
                  <form action="{{ route('production.destroy', $production->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this production order?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete"><i class="fa fa-trash-alt"></i></button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</div>

<script>
  $(document).ready(function(){
    $('#cust-datatable-default').DataTable({
      pageLength: 100
    });
  });
</script>
@endsection
