@extends('layouts.app')
@section('title', 'Product Barcoding')

@section('content')

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title">Product Barcoding</h4>
        <input type="text" id="searchBox" class="form-control w-25" placeholder="Search product by name...">
    </div>

    <div class="card-body">
        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>There were some problems with your input:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Session Error --}}
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('products.generateBarcodes') }}" method="POST">
            @csrf
            <div class="text-end mb-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upc-scan"></i> Generate Barcodes
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="productsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">Select</th>
                            <th>Product</th>
                            <th>Variation (SKU)</th>
                            <th>Selling Price</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($variations as $variation)
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_variations[]" value="{{ $variation->id }}">
                                </td>
                                <td class="product-name">{{ $variation->product->name }}</td>
                                <td>{{ $variation->sku }}</td>
                                <td>{{ number_format($variation->product->selling_price, 2) }}</td>
                                <td>
                                    <input type="number" name="quantity[{{ $variation->id }}]" value="0" min="0" class="form-control form-control-sm">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

{{-- Search Filter Script --}}
<script>
    document.getElementById('searchBox').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#productsTable tbody tr');

        rows.forEach(row => {
            let productName = row.querySelector('.product-name').textContent.toLowerCase();
            row.style.display = productName.includes(filter) ? '' : 'none';
        });
    });
</script>

@endsection
