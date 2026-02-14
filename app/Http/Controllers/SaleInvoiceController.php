<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleItemCustomization;
use App\Models\PurchaseInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'account')->get();
        return view('sales.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::orderBy('name', 'asc')
        ->withSum('purchaseInvoices as total_purchased', 'quantity')
        ->withSum('saleInvoices as total_sold', 'quantity')
        ->withCount('saleInvoiceParts as total_customized')
        ->get()
        ->map(function ($product) {
            $product->real_time_stock = ($product->total_purchased ?? 0) 
            - ($product->total_sold ?? 0) 
            - ($product->total_customized ?? 0);
            return $product;
        });

        // ✅ Filter customers based on user role
        $customersQuery = ChartOfAccounts::where('account_type', 'customer');
        if (!auth()->user()->hasRole('superadmin')) {
            $customersQuery->where('visibility', 'public');
        }
        $customers = $customersQuery->orderBy('name')->get();

        // ✅ Filter payment accounts based on user role
        $paymentAccountsQuery = ChartOfAccounts::whereIn('account_type', ['cash', 'bank']);
        if (!auth()->user()->hasRole('superadmin')) {
            $paymentAccountsQuery->where('visibility', 'public');
        }
        $paymentAccounts = $paymentAccountsQuery->orderBy('name')->get();

        return view('sales.create', [
            'products' => $products,
            'customers' => $customers,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'               => 'required|date',
            'account_id'         => 'required|exists:chart_of_accounts,id',
            'type'               => 'required|in:cash,credit',
            'discount'           => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.quantity'   => 'required|numeric|min:1',
            'items.*.customizations'   => 'nullable|array',
            'items.*.customizations.*' => 'exists:products,id',
            // Payment receiving fields
            'payment_account_id' => 'nullable|exists:chart_of_accounts,id',
            'amount_received'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Auto-generate Invoice Number
            $lastInvoice = SaleInvoice::withTrashed()->orderBy('id', 'desc')->first();
            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;
            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // 1. Create Invoice
            $invoice = SaleInvoice::create([
                'invoice_no' => $invoiceNo,
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $validated['remarks'],
                'created_by' => Auth::id(),
            ]);

            // 2. Save Items & Track Net Total for Voucher validation
            $totalBill = 0;
            foreach ($validated['items'] as $item) {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                    'discount'        => 0,
                ]);
                
                $totalBill += ($item['sale_price'] * $item['quantity']);

                if (!empty($item['customizations'])) {
                    foreach ($item['customizations'] as $customItemId) {
                        SaleItemCustomization::create([
                            'sale_invoice_id'       => $invoice->id,
                            'sale_invoice_items_id' => $invoiceItem->id,
                            'item_id'               => $customItemId,
                        ]);
                    }
                }
            }

            $netTotal = $totalBill - ($validated['discount'] ?? 0);

            // 3. Record Sales Revenue Entry
            $salesAccount = ChartOfAccounts::where('name', 'Sales Revenue')
                ->orWhere('account_type', 'revenue')
                ->first();

            if (!$salesAccount) throw new \Exception('Sales Revenue account not found.');

            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'], // Debit Customer
                'ac_cr_sid'    => $salesAccount->id,        // Credit Sales
                'amount'       => $netTotal,
                'remarks'      => "Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);

            // 4. Handle Payment (If Cash or partial payment received)
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'], // Debit Cash/Bank
                    'ac_cr_sid'    => $validated['account_id'],         // Credit Customer
                    'amount'       => $validated['amount_received'],
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // 5. NEW: Record Cost of Goods Sold (COGS) Entry
            $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();
            $cogsAccount = ChartOfAccounts::where('account_type', 'cogs')->first();

            if ($inventoryAccount && $cogsAccount) {
                $totalCost = 0;
                foreach ($validated['items'] as $item) {
                    // Find the LATEST purchase of this product to get the most recent price
                    $latestPurchase = PurchaseInvoiceItem::where('item_id', $item['product_id'])
                        ->with('invoice') // Assuming relation exists
                        ->latest()
                        ->first();

                    if ($latestPurchase) {
                        $unitPrice = $latestPurchase->purchase_price;
                        
                        // Calculate Bilty share (Total Bilty / Total Items in that purchase)
                        $totalQtyInPurchase = PurchaseInvoiceItem::where('purchase_invoice_id', $latestPurchase->purchase_invoice_id)->sum('quantity');
                        $biltyCharge = $latestPurchase->purchaseInvoice->bilty_charges ?? 0;
                        
                        $landedCostPerUnit = $unitPrice + ($biltyCharge / ($totalQtyInPurchase ?: 1));
                        
                        $totalCost += ($landedCostPerUnit * $item['quantity']);
                    }
                }

                Voucher::create([
                    'voucher_type' => 'journal',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $cogsAccount->id,      
                    'ac_cr_sid'    => $inventoryAccount->id, 
                    'amount'       => $totalCost,
                    'remarks'      => "COGS (Landed Cost) for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice and Payment processed.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Store failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Error saving invoice.');
        }
    }

    public function edit($id)
    {
        $invoice = SaleInvoice::with(['items.customizations', 'account'])->findOrFail($id);
        
        // Calculate real-time stock exactly like the create method
        $products = Product::orderBy('name', 'asc')
        ->withSum('purchaseInvoices as total_purchased', 'quantity')
        ->withSum('saleInvoices as total_sold', 'quantity')
        ->withCount('saleInvoiceParts as total_customized')
        ->get()
        ->map(function ($product) {
            $product->real_time_stock = ($product->total_purchased ?? 0) - ($product->total_sold ?? 0) - ($product->total_customized ?? 0);
            return $product;
        });

        $amountReceived = Voucher::where('ac_cr_sid', $invoice->account_id)
        ->where('remarks', 'LIKE', "%Invoice #{$invoice->invoice_no}%")
        ->sum('amount');

        // ✅ Filter customers based on user role
        $customersQuery = ChartOfAccounts::where('account_type', 'customer');
        if (!auth()->user()->hasRole('superadmin')) {
            $customersQuery->where('visibility', 'public');
        }
        $customers = $customersQuery->orderBy('name')->get();

        // ✅ Filter payment accounts based on user role
        $paymentAccountsQuery = ChartOfAccounts::whereIn('account_type', ['cash', 'bank']);
        if (!auth()->user()->hasRole('superadmin')) {
            $paymentAccountsQuery->where('visibility', 'public');
        }
        $paymentAccounts = $paymentAccountsQuery->orderBy('name')->get();

        return view('sales.edit', [
            'invoice' => $invoice,
            'products' => $products,
            'customers' => $customers,
            'paymentAccounts' => $paymentAccounts,
            'amountReceived' => $amountReceived,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'               => 'required|date',
            'account_id'         => 'required|exists:chart_of_accounts,id',
            'type'               => 'required|in:cash,credit',
            'discount'           => 'nullable|numeric|min:0',
            'remarks'            => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.customizations'   => 'nullable|array',
            'items.*.customizations.*' => 'exists:products,id',
            'payment_account_id' => 'nullable|exists:chart_of_accounts,id',
            'amount_received'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $invoice = SaleInvoice::findOrFail($id);
            $invoiceNo = $invoice->invoice_no;

            // 1. Update Invoice Basic Data
            $invoice->update([
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $validated['remarks'],
            ]);

            // 2. Clear Existing Items & Customizations
            // We delete them and re-add to handle row removals/changes easily
            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();

            // 3. Re-insert Items & Calculate Totals
            $totalBill = 0;
            $totalCost = 0;

            $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();
            $cogsAccount = ChartOfAccounts::where('account_type', 'cogs')->first();
            $salesAccount = ChartOfAccounts::where('account_type', 'revenue')->first();

            foreach ($validated['items'] as $item) {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                ]);

                $totalBill += ($item['sale_price'] * $item['quantity']);

                // --- COGS Calculation ---
                $latestPurchase = PurchaseInvoiceItem::where('item_id', $item['product_id'])
                    ->with('invoice')
                    ->latest()
                    ->first();

                $itemTotalCost = 0;
                if ($latestPurchase) {
                    $unitPrice = $latestPurchase->price;
                    $totalQtyInPurchase = PurchaseInvoiceItem::where('purchase_invoice_id', $latestPurchase->purchase_invoice_id)->sum('quantity');
                    $biltyCharge = $latestPurchase->invoice->bilty_charges ?? 0;
                    $landedCostPerUnit = $unitPrice + ($biltyCharge / ($totalQtyInPurchase ?: 1));
                    $itemTotalCost = $landedCostPerUnit;
                }

                // Handle Customizations
                if (!empty($item['customizations'])) {
                    foreach ($item['customizations'] as $customId) {
                        SaleItemCustomization::create([
                            'sale_invoice_id'       => $invoice->id,
                            'sale_invoice_items_id' => $invoiceItem->id,
                            'item_id'               => $customId,
                        ]);
                        
                        // Add customization material cost to item COGS
                        $customPurchase = PurchaseInvoiceItem::where('item_id', $customId)->latest()->first();
                        if ($customPurchase) {
                            $itemTotalCost += $customPurchase->price;
                        }
                    }
                }
                $totalCost += ($itemTotalCost * $item['quantity']);
            }

            $netTotal = $totalBill - ($validated['discount'] ?? 0);
            $invoice->update(['net_amount' => $netTotal]);

            // 4. Update Financial Vouchers
            // Delete ONLY the Journal and Receipt vouchers linked specifically to this invoice ID
            Voucher::where('reference', $invoice->id)->delete();

            // Re-create Sales Revenue Entry (Journal)
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'], 
                'ac_cr_sid'    => $salesAccount->id ?? null,
                'amount'       => $netTotal,
                'remarks'      => "Updated: Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);

            // Re-create COGS Entry
            if ($inventoryAccount && $cogsAccount && $totalCost > 0) {
                Voucher::create([
                    'voucher_type' => 'journal',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $cogsAccount->id,      
                    'ac_cr_sid'    => $inventoryAccount->id, 
                    'amount'       => $totalCost,
                    'remarks'      => "Updated: COGS for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // 5. Handle NEW Payment Receipt
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            DB::commit();

            // Refresh cache for all items in the invoice
            foreach($validated['items'] as $item) {
                \Cache::forget("landed_cost_prod_{$item['product_id']}");
            }

            return redirect()->route('sale_invoices.index')->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Invoice Update Error: " . $e->getMessage());
            return back()->with('error', 'Update Failed: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $invoice = SaleInvoice::findOrFail($id);

            // 1. Delete associated customizations first (Foreign Key constraints)
            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();

            // 2. Delete invoice items
            $invoice->items()->delete();

            // 3. Remove all financial entries linked to this invoice
            // This clears the Sales Revenue, COGS, and any Payment Receipts
            Voucher::where('reference', $invoice->id)->delete();

            // 4. Finally, delete the invoice itself
            $invoice->delete();

            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Invoice and associated financial records deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[SaleInvoice] Delete failed: " . $e->getMessage());
            return back()->with('error', 'Failed to delete invoice: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $invoice = SaleInvoice::with('items.product', 'items.variation', 'account')
            ->findOrFail($id);
        return response()->json($invoice);
    }

    public function print($id)
    {
        // 1. Fetch Invoice with Relations
        $invoice = SaleInvoice::with(['account', 'items.product'])->findOrFail($id);

        // 2. Fetch Amount Received from Vouchers
        // We search the ac_cr_sid (Customer) and matching Invoice Number in remarks
        $amountReceived = Voucher::where('ac_cr_sid', $invoice->account_id)
            ->where('remarks', 'LIKE', "%Invoice #{$invoice->invoice_no}%")
            ->sum('amount');

        // 3. Initialize TCPDF
        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Bilwani Furnitures');
        $pdf->SetTitle('SALE-' . $invoice->invoice_no);

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        /* ---------------- Company Header ---------------- */
        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        // Invoice Title (Top Right)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Sale Invoice', 0, 1, 'R');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        /* ---------------- Customer + Invoice Info ---------------- */
        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="100%">
            <tr>
                <td width="40%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="30%"><b>Customer</b></td>
                            <td width="70%">' . ($invoice->account->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Invoice No</b></td>
                            <td width="70%">' . $invoice->invoice_no . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">' . \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') . '</td>
                        </tr>
                    </table>
                </td>
                <td width="60%"></td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        /* ---------------- Items Table ---------------- */
        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="6%">#</th>
                <th width="54%">Item</th>
                <th width="10%">Qty</th>
                <th width="15%">Price</th>
                <th width="15%">Total</th>
            </tr>';

        $count = 0;
        $totalQty = 0;
        $subTotal = 0;

        foreach ($invoice->items as $item) {
            $count++;
            // Calculate line total (Price * Qty)
            $lineTotal = $item->sale_price * $item->quantity;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td style="text-align:left">' . ($item->product->name ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . number_format($item->sale_price, 2) . '</td>
                <td>' . number_format($lineTotal, 2) . '</td>
            </tr>';

            $totalQty += $item->quantity;
            $subTotal += $lineTotal;
        }

        // Calculations
        $invoiceDiscount = $invoice->discount ?? 0;
        $netTotal = $subTotal - $invoiceDiscount;
        $balanceDue = $netTotal - $amountReceived;

        // Footer of Table
        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total Items Qty</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td align="right"><b>Sub Total</b></td>
            <td><b>' . number_format($subTotal, 2) . '</b></td>
        </tr>';

        if ($invoiceDiscount > 0) {
            $html .= '
            <tr>
                <td colspan="4" align="right"><b>Less: Discount</b></td>
                <td>' . number_format($invoiceDiscount, 2) . '</td>
            </tr>';
        }

        $html .= '
        <tr style="background-color:#f5f5f5;">
            <td colspan="4" align="right"><b>Net Payable</b></td>
            <td><b>' . number_format($netTotal, 2) . '</b></td>
        </tr>
        <tr>
            <td colspan="4" align="right"><b>Amount Received</b></td>
            <td style="color:green;">' . number_format($amountReceived, 2) . '</td>
        </tr>
        <tr style="background-color:#f5f5f5;">
            <td colspan="4" align="right"><b>Remaining Balance</b></td>
            <td style="color:red;"><b>' . number_format($balanceDue, 2) . '</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        /* ---------------- Remarks ---------------- */
        if (!empty($invoice->remarks)) {
            $pdf->Ln(5);
            $pdf->writeHTML('<b>Remarks:</b><br>' . nl2br($invoice->remarks), true, false, false, false, '');
        }

        /* ---------------- Footer Signatures ---------------- */
        // Ensure footer doesn't break page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }

        $pdf->Ln(20);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();

        $pdf->Line(28, $yPosition, 28 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 130 + $lineWidth, $yPosition);

        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 10);

        $pdf->SetXY(28, $yPosition + 2);
        $pdf->Cell($lineWidth, 10, 'Customer Signature', 0, 0, 'C');

        $pdf->SetXY(130, $yPosition + 2);
        $pdf->Cell($lineWidth, 10, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output('Invoice_' . $invoice->invoice_no . '.pdf', 'I');
    }

}
