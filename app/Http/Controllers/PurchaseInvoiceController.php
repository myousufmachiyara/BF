<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // 1. Initialize query with relationships
        $query = PurchaseInvoice::with(['vendor', 'attachments']);

        // 2. Filter for Soft-Deleted items if requested
        if ($request->has('view_deleted')) {
            $query->onlyTrashed();
        }

        // 3. Privacy Logic: If NOT superadmin, restrict to own records
        if (!$user->hasRole('superadmin')) {
            $query->where('created_by', $user->id);
        }

        // 4. Execute with latest first
        $invoices = $query->latest()->get();

        return view('purchases.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::get();
        
        // ✅ Filter vendors based on user role
        $vendorsQuery = ChartOfAccounts::where('account_type', 'vendor');
        if (!auth()->user()->hasRole('superadmin')) {
            $vendorsQuery->where('visibility', 'public');
        }
        $vendors = $vendorsQuery->orderBy('name')->get();
        
        $units = MeasurementUnit::all();

        return view('purchases.create', compact('products', 'vendors', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'items' => 'required|array|min:1', // Ensure items array exists
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // 1. Generate Invoice Number
            $lastInvoice = PurchaseInvoice::withTrashed()->orderBy('id', 'desc')->first();
            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;
            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // 2. Create Invoice Header
            $invoice = PurchaseInvoice::create([
                'invoice_no'   => $invoiceNo,
                'vendor_id'    => $request->vendor_id,
                'invoice_date' => $request->invoice_date,
                'bill_no'      => $request->bill_no,
                'ref_no'       => $request->ref_no,
                'remarks'      => $request->remarks,
                'created_by'   => auth()->id(),
            ]);

            $totalAmount = 0; 
            $products = Product::pluck('name', 'id');

            // 3. Create Items & Calculate Total

            $position = 0; // Initialize manual counter

            foreach ($request->items as $index => $itemData) {                
                $lineTotal = ($itemData['quantity'] ?? 0) * ($itemData['price'] ?? 0);
                $totalAmount += $lineTotal; // FIXED: Adding to total

                $invoice->items()->create([
                    'item_id'   => $itemData['item_id'],
                    'item_name' => $products[$itemData['item_id']] ?? null,
                    'quantity'  => $itemData['quantity'] ?? 0,
                    'unit'      => $itemData['unit'] ?? '',
                    'price'     => $itemData['price'] ?? 0,
                    'remarks'   => $itemData['item_remarks'] ?? null,
                    'sort_order' => $position, // Use clean counter instead of $index                
                ]);
                $position++;
            }
            // 4. Find Debit Account (Inventory/Stock)
            $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();

            if (!$inventoryAccount) {
                throw new \Exception("Default Inventory/Stock account not found. Please check Chart of Accounts.");
            }

            // 5. Create Voucher (The Accounting Entry)
            Voucher::create([
                'date'         => $request->invoice_date,
                'voucher_type' => 'journal',
                'ac_dr_sid'    => $inventoryAccount->id, // Debit: Stock
                'ac_cr_sid'    => $request->vendor_id,    // Credit: Vendor
                'amount'       => $totalAmount,           // FIXED: Now has the full invoice value
                'reference'    => (string)$invoice->id,   // Cast to string to ensure update lookup works
            ]);

            // 6. Handle Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Invoice Store Error: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice::with(['items' => function($q) {
            $q->orderBy('sort_order', 'asc');
        }, 'attachments'])->findOrFail($id);        

        $user = auth()->user();

        // Only admin or creator can edit
        if (!$user->hasRole('superadmin') && $invoice->created_by != $user->id) {
            abort(403, 'Unauthorized access');
        }

        // ✅ Filter vendors based on user role
        $vendorsQuery = ChartOfAccounts::where('account_type', 'vendor');
        if (!$user->hasRole('superadmin')) {
            $vendorsQuery->where('visibility', 'public');
        }
        $vendors = $vendorsQuery->orderBy('name')->get();
        $products = Product::select('id', 'name', 'measurement_unit')->get();
        $units = MeasurementUnit::all();

        return view('purchases.edit', compact('invoice', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
            'convance_charges' => 'nullable|numeric|min:0',
            'labour_charges'   => 'nullable|numeric|min:0',
            'bill_discount'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::findOrFail($id);

            // 1. Update Invoice Main Details
            $invoice->update([
                'vendor_id'    => $request->vendor_id,
                'invoice_date' => $request->invoice_date,
                'bill_no'      => $request->bill_no,
                'ref_no'       => $request->ref_no,
                'remarks'      => $request->remarks,
            ]);

            // 2. Refresh Items
            $invoice->items()->delete();

            $totalItemsAmount = 0;
            $products = Product::pluck('name', 'id');

            // USE A MANUAL COUNTER instead of the $index from the request
            $position = 0; 

            foreach ($request->items as $itemData) { 
                if (empty($itemData['item_id'])) continue;

                $lineTotal = ($itemData['quantity'] ?? 0) * ($itemData['price'] ?? 0);
                $totalItemsAmount += $lineTotal;

                $invoice->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'item_name'    => $products[$itemData['item_id']] ?? null,
                    'quantity'     => $itemData['quantity'] ?? 0,
                    'unit'         => $itemData['unit'] ?? '',
                    'price'        => $itemData['price'] ?? 0,
                    'remarks'      => $itemData['item_remarks'] ?? null,
                    'sort_order'   => $position
                ]);

                $position++; // Increment for the next item
            }

            // 3. Calculate Final Net Amount (Items + Charges - Discount)
            $convance = (float)($request->convance_charges ?? 0);
            $labour   = (float)($request->labour_charges ?? 0);
            $discount = (float)($request->bill_discount ?? 0);
            $finalVoucherAmount = ($totalItemsAmount + $convance + $labour) - $discount;

            // 3. Find the Inventory Account (Stock in Hand)
            $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();

            // 4. Update or Create the General Ledger (Voucher)
            // First, try to find the existing voucher linked to this invoice
            $voucher = Voucher::where('reference', (string)$invoice->id)->where('voucher_type', 'journal')->first();

            if (!$voucher) {
                // If no voucher exists, create a new instance
                $voucher = new Voucher();
                $voucher->reference = (string)$invoice->id;
                $voucher->voucher_type = 'journal';
            }

            // Update the fields (this applies to both existing and new vouchers)
            $voucher->date      = $request->invoice_date;
            $voucher->ac_dr_sid = $inventoryAccount->id;
            $voucher->ac_cr_sid = $request->vendor_id;
            $voucher->amount    = $finalVoucherAmount;
            $voucher->remarks   = "Updated Purchase Invoice #{$invoice->invoice_no}";
            $voucher->save();

            // 5. Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update Error: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::findOrFail($id);

            // 1. Soft Delete the Voucher 
            // This removes the transaction from all accounting reports
            Voucher::where('reference', $invoice->id)->where('voucher_type', 'journal')->delete();

            // 2. Soft Delete the Invoice
            $invoice->delete();

            DB::commit();

            return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice and Ledger entry soft-deleted.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete invoice $id: " . $e->getMessage());
            return back()->withErrors(['error' => 'Deletion failed. Ledger was not updated.']);
        }
    }

    public function restore($id)
    {
        $invoice = PurchaseInvoice::onlyTrashed()->findOrFail($id);
        
        DB::beginTransaction();
        
        $invoice->restore();
        Voucher::onlyTrashed()->where('reference', $id)->restore();
        
        DB::commit();
        return redirect()->back()->with('success', 'Invoice and Ledger restored.');
    }

    public function getInvoicesByItem($itemId)
    {
        $invoices = PurchaseInvoice::whereHas('items', function ($q) use ($itemId) {
            $q->where('item_id', $itemId);
        })
        ->with('vendor')
        ->get(['id', 'vendor_id']);

        return response()->json(
            $invoices->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'vendor' => $inv->vendor->name ?? '',
                ];
            })
        );
    }

    public function getItemDetails($invoiceId, $itemId)
    {
        $item = PurchaseInvoiceItem::with(['product', 'measurementUnit'])
            ->where('purchase_invoice_id', $invoiceId)
            ->where('item_id', $itemId)
            ->first();

        if (!$item) {
            return response()->json(['error' => 'Item not found in this invoice.'], 404);
        }

        return response()->json([
            'item_id'   => $item->item_id,
            'item_name' => $item->product->name ?? '',
            'quantity'  => $item->quantity,
            'unit_id'   => $item->unit_id,
            'unit_name' => $item->unit->name ?? '',
            'price'     => $item->price,
        ]);
    }

    public function print($id)
    {
        $invoice = PurchaseInvoice::with(['vendor', 'items' => function($q) {
            $q->orderBy('sort_order', 'asc');
        }])->findOrFail($id);

        $pdf = new \TCPDF();

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('Bilwani Furnitures');
        $pdf->SetTitle('PUR-' . $invoice->invoice_no);
        $pdf->SetSubject('Purchase Invoice');
        $pdf->SetKeywords('PUR, TCPDF, PDF');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // --- Company Header ---
        $logoPath = public_path('assets/img/bf_logo.jpg');

        // Logo (Top Left)
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        // Purchase INVOICE (Top Right)
        $pdf->SetFont('helvetica', 'B', 14);

        // Page width = 210 (A4) - margins (10+10)
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Purchase Invoice', 0, 1, 'R');

       
        // --- Customer + Invoice Info ---
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="40%">
            <tr>
                <td>
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="30%"><b>Vendor</b></td>
                            <td width="70%">'.($invoice->vendor->name ?? '-').'</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Invoice No</b></td>
                            <td width="70%">'.$invoice->invoice_no.'</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">'.\Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y').'</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        // --- Items Table ---
        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="8%">#</th>
                <th width="40%">Item</th>
                <th width="15%">Qty</th>
                <th width="15%">Price</th>
                <th width="20%">Total</th>
            </tr>';
        $count = 0; $totalQty=0; $totalAmount=0;
        foreach ($invoice->items as $item) {
            $count++;
            $html .= '
            <tr>
                <td>'.$count.'</td>
                <td>'.$item->product->name.'</td>
                <td>'.$item->quantity.'</td>
                <td>'.$item->price.'</td>
                <td>'.($item->price*$item->quantity).'</td>
            </tr>';
            $totalQty += $item->quantity;
            $totalAmount += ($item->price*$item->quantity);
        }
        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>'.number_format($totalQty,3).'</b></td>
            <td colspan="2"><b>'.number_format($totalAmount,2).'</b></td>
        </tr>
        </table>';
        $pdf->writeHTML($html, true, false, false, false, '');       

        // Footer Signature Lines
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Ln(20);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();

        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);
        $pdf->Ln(5);

        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Approved By', 0, 0, 'C');

        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Received By', 0, 0, 'C');
 
        return $pdf->Output('purchase_invoice_' . $invoice->id . '.pdf', 'I');
    }

    public function getProductInvoices($productId)
    {
        try {
            // Fetch invoices for this vendor that include this product
            $invoices = PurchaseInvoice::whereHas('items', function($q) use ($productId) {
                    $q->where('item_id', $productId);
                })
                ->with(['items' => function($q) use ($productId) {
                    $q->where('item_id', $productId);
                }])
                ->get();

            $data = $invoices->map(function($inv) {
                $item = $inv->items->first(); // get the first matching item
                return [
                    'id' => $inv->id,
                    'number' => $inv->invoice_number,
                    'rate' => $item ? $item->price : 0, // safe fallback
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            Log::error('Invoice fetch failed: '.$e->getMessage());
            return response()->json(['error' => 'Failed to load invoices'], 500);
        }
    }
}
