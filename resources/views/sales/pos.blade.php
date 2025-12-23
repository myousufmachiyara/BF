@extends('layouts.pos-header')

@section('title', 'POS System')

@section('content')	
    <body>
		<section class="body">
            <div class="inner-wrapper cust-pad">
                <section role="main" class="content-body" style="margin:0px">
                <div class="row">
                    <div class="col-12 mb-3">								
                        <section class="card">
                            <header class="card-header" style="display: flex;justify-content: space-between;">
                                <h2 class="card-title">New Invoice</h2>
                                <div class="card-actions">
                                    <button type="button" class="btn btn-primary" onclick="addNewRow_btn()"> <i class="fas fa-plus"></i> Add New Item </button>
                                </div>
                            </header>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-3 row" style="margin:0px;">
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card card-modern card-modern-alt-padding col-6 mt-0">
                                            <div class="card-body bg-light">
                                                <div class="image-frame mb-2">
                                                    <div class="image-frame-wrapper">
                                                        <img src="/assets/img/empty-300x240.jpg" class="img-fluid" alt="Product Short Name">
                                                    </div>
                                                </div>
                                                <h4 class="text-4 line-height-2 mt-0 mb-2"><a href="ecommerce-products-form.html" class="ecommerce-sidebar-link text-color-dark text-color-hover-primary text-decoration-none">Product Name</a></h4>
                                                <div class="product-price">
                                                    <div class="regular-price on-sale">$59.00</div>
                                                    <div class="sale-price">$49.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-9">
                                        <div class="card-body" style="overflow-x:auto;max-height:450px;overflow-y:auto">
                                            <form method="post" id="myForm" enctype="multipart/form-data" onkeydown="return event.key != 'Enter';">
                                                @csrf
                                                <table class="table table-bordered table-striped mb-0" id="myTable" >
                                                    <thead>
                                                        <tr>
                                                            <th width="15%">Item Code<span style="color: red;"><strong>*</strong></span></th>
                                                            <th>Item Name<span style="color: red;"><strong>*</strong></span></th>
                                                            <th width="15%">Qty<span style="color: red;"><strong>*</strong></span></th>
                                                            <th width="15%">Price<span style="color: red;"><strong>*</strong></span></th>
                                                            <th width="15%">Amount</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="saleInvoiceTable">
                                                        <tr>
                                                            <td>
                                                                <input type="number" id="item_code1" name="item_code[]" placeholder="Code" class="form-control" required onchange="getItemDetails(1,1)">
                                                            </td>
                                                            <td>
                                                                <select data-plugin-selecttwo class="form-control select2-js" id="item_name1" onchange="getItemDetails(1,2)" name="item_name[]" required>
                                                                    <option selected>Select Item</option>
                                                                    @foreach($items as $key => $row)	
                                                                        <option value="{{$row->it_cod}}">{{$row->item_name}}</option>
                                                                    @endforeach
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="number" id="item_qty1" name="item_qty[]" onchange="rowTotal(0)" placeholder="Qty" value="0" step="any" required class="form-control">
                                                            </td>
                                                            <td>
                                                                <input type="number" id="price1" name="item_price[]" onchange="rowTotal(1)" placeholder="Price" value="0" step="any" required class="form-control">
                                                            </td>
                                                            <td>
                                                                <input type="number" id="amount1" name="item_amount[]" placeholder="Amount" class="form-control" value="0" step="any" required disabled>
                                                            </td>
                                                            <td>
                                                                <button type="button" onclick="removeRow(this)" class="btn btn-danger" tabindex="1"><i class="fas fa-times"></i></button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </form>
									    </div>
                                        <footer class="card-footer">
                                            <div class="row form-group mb-3">
                                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                                    <label class="col-form-label">Total Amount</label>
                                                    <input type="number" id="total_amount_show" placeholder="Total Amount" class="form-control" step="any" disabled>
                                                    <input type="hidden" id="totalAmount" name="totalAmount" step="any" placeholder="Total Amount" class="form-control">
                                                </div>

                                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                                    <label class="col-form-label">Total Quantity</label>
                                                    <input type="number" id="total_quantity" name="total_quantity" placeholder="Total Weight" class="form-control" step="any" disabled>
                                                </div>

                                                <div class="col-6 col-md-2 pb-sm-3 pb-md-0">
                                                    <label class="col-form-label">Bill Discount</label>
                                                    <input type="number" id="bill_discount"  onchange="netTotal()" name="bill_discount" placeholder="Bill Discount" step="any" value="0" class="form-control">
                                                </div>

                                            </div>
                                            <div class="col-sm-12 col-md-12 pb-sm-3 pb-md-0 text-end">
                                                <h3 class="font-weight-bold mt-3 mb-0 text-5 text-primary">Net Amount</h3>
                                                <span>
                                                    <strong class="text-4 text-primary">PKR <span id="netTotal" class="text-4 text-danger">0.00 </span></strong>
                                                </span>
                                            </div>
									    </footer>
                                        <footer class="card-footer">
                                            <div class="row form-group mb-2">
                                                <div class="text-end">
                                                    <button type="button" class="btn btn-danger mt-2" <i class="fas fa-trash"></i> Discard Invoice</button>
                                                    <button type="submit" class="btn btn-primary mt-2"> <i class="fas fa-save"></i> Add Invoice</button>
                                                </div>
                                            </div>
                                        </footer>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
                </section>
            </div>
        </section>
	</body>
    <script>
        var index=2;

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                addNewRow();	
            }
        });

        function getItemDetails(row_no,option){
            var itemId;
            if(option==1){
                itemId = document.getElementById("item_code"+row_no).value;
            }
            else if(option==2){
                itemId = document.getElementById("item_name"+row_no).value;
            }
            $.ajax({
                type: "GET",
                url: "/items/detail",
                data: {id:itemId},
                success: function(result){
                    $('#item_code'+row_no).val(result[0]['it_cod']);
                    $('#item_name'+row_no).val(result[0]['it_cod']).select2();
                    $('#price'+row_no).val(result[0]['sales_price']);
                },
                error: function(){
                    alert("error");
                }
            });
            
        }

        function addNewRow(){		
            var lastRow =  $('#myTable tr:last');
            latestValue=lastRow[0].cells[1].querySelector('select').value;

            if(latestValue!="Select Item"){
                var table = document.getElementById('myTable').getElementsByTagName('tbody')[0];
                var newRow = table.insertRow(table.rows.length);

                var cell1 = newRow.insertCell(0);
                var cell2 = newRow.insertCell(1);
                var cell3 = newRow.insertCell(2);
                var cell4 = newRow.insertCell(3);
                var cell5 = newRow.insertCell(4);
                var cell6 = newRow.insertCell(5);

                cell1.innerHTML = '<input type="text" id="item_code'+index+'" name="item_code[]" onchange="getItemDetails('+index+','+1+')" placeholder="Code" class="form-control" required>';
                cell2.innerHTML = '<select data-plugin-selecttwo class="form-control select2-js" id="item_name'+index+'" required onchange="getItemDetails('+index+','+2+')" name="item_name">'+
                                        '<option>Select Item</option>'+
                                        @foreach($items as $key => $row)	
                                            '<option value="{{$row->it_cod}}">{{$row->item_name}}</option>'+
                                        @endforeach
                                    '</select>';
                cell3.innerHTML = '<input type="number" id="item_qty'+index+'"  onchange="rowTotal('+index+')" name="item_qty[]" placeholder="Qty" value="0" step="any" required class="form-control">';
                cell4.innerHTML = '<input type="number" id="price'+index+'" onchange="rowTotal('+index+')" name="item_price[]"  placeholder="Price" value="0" step="any" required class="form-control">';
                cell5.innerHTML = '<input type="number" id="amount'+index+'" name="item_amount[]" placeholder="Amount" class="form-control" value="0" step="any" required disabled>';
                cell6.innerHTML = '<button type="button" onclick="removeRow(this)" class="btn btn-danger" tabindex="1"><i class="fas fa-times"></i></button>';

                index++;

                var itemCount = Number($('#itemCount').val());
                itemCount = itemCount+1;
                $('#itemCount').val(itemCount);
                $('#myTable select[data-plugin-selecttwo]').select2();

            }
	    }

        function rowTotal(index){
            var qty = $('#item_qty'+index+'').val();
            var price = $('#price'+index+'').val();
            var amount = qty * price;
            $('#amount'+index+'').val(amount);
            tableTotal();
	    }

        function tableTotal(){
            var totalAmount=0;
            var totalQuantity=0;
            var tableRows = $("#saleInvoiceTable tr").length;
            var table = document.getElementById('myTable').getElementsByTagName('tbody')[0];

            for (var i = 0; i < tableRows; i++) {
                var currentRow =  table.rows[i];
                totalAmount = totalAmount + Number(currentRow.cells[6].querySelector('input').value);
                totalQuantity = totalQuantity + Number(currentRow.cells[2].querySelector('input').value);
            }

            $('#totalAmount').val(totalAmount);
            $('#total_amount_show').val(totalAmount);
            $('#total_quantity').val(totalQuantity);

            netTotal();
        }

        function netTotal(){
            var netTotal = 0;
            var total = Number($('#totalAmount').val());
            var bill_discount = Number($('#bill_discount').val());

            netTotal = total - bill_discount;
            netTotal = netTotal.toFixed(0);
            FormattednetTotal = formatNumberWithCommas(netTotal);
            document.getElementById("netTotal").innerHTML = '<span class="text-4 text-danger">'+FormattednetTotal+'</span>';
        }

        function formatNumberWithCommas(number) {
            // Convert number to string and add commas
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    </script>
</html>
@endsection