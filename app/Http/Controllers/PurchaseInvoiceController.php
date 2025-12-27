<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Product;
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
    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        return view('purchases.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('purchases.create', compact('products', 'vendors','units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'payment_terms' => 'nullable|string',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'item_id.*'      => 'required|exists:products,id',
            'quantity.*'     => 'required|numeric|min:0.01',
            'unit.*'         => 'required|exists:measurement_units,id',
            'price.*'        => 'required|numeric|min:0',
            'item_remarks.*' => 'nullable|string',
            'convance_charges' => 'nullable|numeric|min:0',
            'labour_charges'   => 'nullable|numeric|min:0',
            'bill_discount'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            Log::info('Starting Purchase Invoice creation', [
                'user_id' => auth()->id(),
                'request' => $request->all()
            ]);

            $invoice = PurchaseInvoice::create([
                'vendor_id'        => $request->vendor_id,
                'invoice_date'     => $request->invoice_date,
                'payment_terms'    => $request->payment_terms,
                'bill_no'          => $request->bill_no,
                'ref_no'           => $request->ref_no,
                'remarks'          => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges'   => $request->labour_charges ?? 0,
                'bill_discount'    => $request->bill_discount ?? 0,
                'created_by'       => auth()->id(),
            ]);

            Log::info('Purchase Invoice created', [
                'invoice_id' => $invoice->id,
            ]);

            $products = Product::pluck('name', 'id');

            foreach ($request->items as $index => $itemData) {
                if (empty($itemData['item_id'])) {
                    continue;
                }

                $invoice->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'item_name'    => $products[$itemData['item_id']] ?? null,
                    'quantity'     => $itemData['quantity'] ?? 0,
                    'unit'         => $itemData['unit'] ?? '',
                    'price'        => $itemData['price'] ?? 0,
                    'remarks'      => $itemData['item_remarks'] ?? null,
                ]);
            }


            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);
                    Log::info('Invoice attachment uploaded', [
                        'invoice_id' => $invoice->id,
                        'file' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Purchase Invoice transaction committed', [
                'invoice_id' => $invoice->id,
            ]);

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase Invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Purchase Invoice Store Error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create invoice. Please try again.']);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice::with(['items', 'attachments'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
        $units = MeasurementUnit::all(); // <-- add this line

        return view('purchases.edit', compact('invoice', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'payment_terms' => 'nullable|string',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',
            'item_id.*'      => 'required|exists:products,id',
            'variation_id.*' => 'nullable|exists:product_variations,id',
            'quantity.*'     => 'required|numeric|min:0.01',
            'unit.*'         => 'required|exists:measurement_units,id',
            'price.*'        => 'required|numeric|min:0',
            'item_remarks.*' => 'nullable|string',
            'convance_charges' => 'nullable|numeric|min:0',
            'labour_charges'   => 'nullable|numeric|min:0',
            'bill_discount'    => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::findOrFail($id);

            // ✅ Update invoice main details
            $invoice->update([
                'vendor_id'        => $request->vendor_id,
                'invoice_date'     => $request->invoice_date,
                'payment_terms'    => $request->payment_terms,
                'bill_no'          => $request->bill_no,
                'ref_no'           => $request->ref_no,
                'remarks'          => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges'   => $request->labour_charges ?? 0,
                'bill_discount'    => $request->bill_discount ?? 0,
            ]);

            Log::info('Purchase Invoice updated', [
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id(),
            ]);

            // ✅ Delete old items
            $invoice->items()->delete();
            Log::info('Old items deleted for invoice', ['invoice_id' => $invoice->id]);

            // ✅ Re-insert updated items
            $products = Product::pluck('name', 'id');

            foreach ($request->items as $index => $itemData) {
                if (empty($itemData['item_id'])) {
                    continue;
                }

                $invoice->items()->create([
                    'item_id'      => $itemData['item_id'],
                    'variation_id' => $itemData['variation_id'] ?? null,
                    'item_name'    => $products[$itemData['item_id']] ?? null,
                    'quantity'     => $itemData['quantity'] ?? 0,
                    'unit'         => $itemData['unit'] ?? '',
                    'price'        => $itemData['price'] ?? 0,
                    'remarks'      => $itemData['item_remarks'] ?? null,
                ]);
            }

            // ✅ Handle new attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');

                    $invoice->attachments()->create([
                        'file_path'     => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'file_type'     => $file->getClientMimeType(),
                    ]);

                    Log::info('Invoice attachment uploaded', [
                        'invoice_id' => $invoice->id,
                        'file' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('purchase_invoices.index')
                            ->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Purchase Invoice update failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to update invoice. Please try again.']);
        }
    }

    public function destroy($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        // Delete attached files from storage
        foreach ($invoice->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $invoice->delete();

        return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice deleted successfully.');
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
            $invoice = PurchaseInvoice::with(['vendor', 'items'])->findOrFail($id);

            $pdf = new \TCPDF();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 10);

            // --- Company Header ---
            $logoPath = public_path('assets/img/mj-logo.jpeg');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 15, 8, 25);
            }
            $pdf->SetXY(50, 10);
            $headerHtml = '
                <div style="text-align:center;">
                    <h2 style="margin:0;">MUSFIRA JEWELRY L.L.C</h2>
                    <p style="margin:0; font-size:10px;">
                        Office 202-201-932, Insurance Building, Al Rigga, Dubai – U.A.E<br>
                        TRN No : 104902647700001
                    </p>
                </div>
            ';
            $pdf->writeHTML($headerHtml, true, false, false, false, '');

            // --- Invoice Title ---
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, 'TAX INVOICE', 0, 1, 'C');

            // --- Customer + Invoice Info ---
            $pdf->Ln(2);
            $infoHtml = '
            <table cellpadding="3" style="font-size:10px;" width="100%">
            <tr>
                <td width="65%">
                    <b>To,</b><br>
                    M/S. ' . ($invoice->vendor->name ?? '-') . '<br>
                    TRN: ' . ($invoice->vendor->trn ?? '-') . '
                </td>
                <td width="35%">
                    <table border="1" cellpadding="3">
                        <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') . '</td></tr>
                        <tr><td><b>Invoice No</b></td><td>' . $invoice->id . '</td></tr>
                    </table>
                </td>
            </tr>
            </table>';
            $pdf->writeHTML($infoHtml, true, false, false, false, '');

            // --- Items Table ---
            $html = '
            <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
                <tr style="font-weight:bold; background-color:#f5f5f5;">
                    <th width="10%">#</th>
                    <th width="40%">Description</th>
                    <th width="15%">Metal</th>
                    <th width="15%">Gold Net</th>
                    <th width="20%">Dia Cts</th>
                </tr>';
            $count = 0; $totalGold = 0; $totalDia = 0;
            foreach ($invoice->items as $item) {
                $count++;
                $html .= '
                <tr>
                    <td>'.$count.'</td>
                    <td>'.$item->product->name.'</td>
                    <td></td>
                    <td>'.$item->gold_net.'</td>
                    <td>'.$item->dia_cts.'</td>
                </tr>';
                $totalGold += $item->gold_net;
                $totalDia  += $item->dia_cts;
            }
            $html .= '
            <tr>
                <td colspan="3" align="right"><b>Total</b></td>
                <td><b>'.number_format($totalGold,3).'</b></td>
                <td><b>'.number_format($totalDia,2).'</b></td>
            </tr>
            </table>';
            $pdf->writeHTML($html, true, false, false, false, '');

            // --- Totals Section ---
            $pdf->Ln(3);
            $totalUsd = 7414.72; // Example (calculate dynamically)
            $totalAed = 27212.00;
            $pdf->SetFont('helvetica','B',11);
            $pdf->Cell(130, 8, 'TOTAL US$: '.number_format($totalUsd,2), 1, 0, 'L');
            $pdf->Cell(60, 8, number_format($totalUsd,2), 1, 1, 'R');
            $pdf->Cell(130, 8, 'TOTAL AED: '.number_format($totalAed,2), 1, 0, 'L');
            $pdf->Cell(60, 8, number_format($totalAed,2), 1, 1, 'R');

            $pdf->Ln(5);
            $pdf->SetFont('helvetica','',9);
            $pdf->MultiCell(0, 6, 'TOTAL US$: Seven Thousand Four Hundred Fourteen Dollars and Seventy-Two Cents', 0, 'L');
            $pdf->MultiCell(0, 6, 'TOTAL AED: Twenty-Seven Thousand Two Hundred Twelve Dirhams', 0, 'L');

            // --- Terms ---
            $pdf->Ln(5);
            $terms = '<p style="font-size:9px; line-height:13px;">
            Goods sold on credit, if not paid when due...<br>
            GOODS ONCE SOLD CANNOT BE RETURNED OR EXCHANGED.<br>
            (etc same as sample invoice)
            </p>';
            $pdf->writeHTML($terms, true, false, false, false, '');

            // --- Signatures ---
            $pdf->Ln(15);
            $pdf->Cell(80, 6, 'Receiver\'s Signature', 0, 0, 'C');
            $pdf->Cell(80, 6, 'Issuer\'s Signature', 0, 1, 'C');

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
