<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with('vendor')
            ->withSum('items as total_amount', \DB::raw('quantity * price'))
            ->latest()
            ->get();

        return view('purchase-returns.index', compact('returns'));
    }

    public function create()
    {
        $invoices = PurchaseInvoice::with('vendor')->get();
        $products = Product::get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        return view('purchase-returns.create', compact('invoices', 'products', 'units','vendors'));
    }

    public function store(Request $request)
    {
        Log::info('Storing Purchase Return', ['request' => $request->all()]);

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',

            // Validate each item row
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.invoice_id' => 'required|exists:purchase_invoices,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::create([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'created_by'       => auth()->id(),
            ]);

            Log::info('Purchase Return created', ['id' => $purchaseReturn->id]);

            foreach ($request->items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id' => $item['item_id'],
                    'variation_id' => $item['variation_id'] ?? null, // <- variation_id
                    'purchase_invoice_id' => $item['invoice_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit'],
                    'price' => $item['price'],
                    'amount' => $item['amount'],
                ]);

                Log::info('Purchase Return Item created', ['data' => $item]);
            }

            DB::commit();
            Log::info('Purchase Return transaction committed successfully.');

            return redirect()->route('purchase_return.index')->with('success', 'Purchase Return saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Return store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors(['error' => 'Failed to save: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $purchaseReturn = PurchaseReturn::with([
            'items',
            'items.item.purchaseInvoices', // load invoices via product
            'items.variation',  // load variation for each item
            'items.invoice',    // load invoice for each item
            'items.unit'        // load unit for each item
        ])->findOrFail($id);

        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        $invoices = PurchaseInvoice::with('vendor')->get();

        return view('purchase-returns.edit', compact('purchaseReturn', 'products', 'vendors', 'units', 'invoices'));
    }

    public function update(Request $request, $id)
    {
        Log::info('PurchaseReturn Update Request', $request->all());

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0',
            'net_amount_hidden' => 'required|numeric|min:0',

            // Validate nested items
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.invoice_id' => 'required|exists:purchase_invoices,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::findOrFail($id);

            $purchaseReturn->update([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'total_amount' => $request->total_amount,
                'net_amount' => $request->net_amount_hidden,
            ]);

            Log::info('PurchaseReturn updated', ['id' => $purchaseReturn->id]);

            // Remove old items
            PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)->delete();
            Log::info('Old PurchaseReturnItems deleted', ['purchase_return_id' => $purchaseReturn->id]);

            // Insert updated items
            foreach ($request->items as $item) {
                $data = [
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id' => $item['item_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'purchase_invoice_id' => $item['invoice_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit'],
                    'price' => $item['price'],
                    'amount' => $item['amount'],
                    'remarks' => $item['remarks'] ?? null,
                ];

                Log::info('Creating PurchaseReturnItem', $data);

                PurchaseReturnItem::create($data);
            }

            DB::commit();
            Log::info('PurchaseReturn update committed successfully', ['id' => $purchaseReturn->id]);

            return redirect()->route('purchase_return.index')->with('success', 'Purchase Return updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PurchaseReturn update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    public function print($id)
    {
        $return = PurchaseReturn::with(['vendor', 'items.item', 'items.unit', 'items.invoice'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Purchase Return #' . $return->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 8, 10, 40);
        }

        // --- Return Info Box ---
        $pdf->SetXY(130, 12);
        $returnInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Return #</b></td><td>' . $return->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($return->return_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($return->vendor->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($returnInfo, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Purchase Return', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="28%">Item Name</th>
                <th width="10%">Inv. #</th>
                <th width="20%">Qty</th>
                <th width="15%">Rate</th>
                <th width="20%">Amount</th>
            </tr>';

        $totalAmount = 0;
        $count = 0;

        foreach ($return->items as $item) {
            $count++;
            $amount = $item->price * $item->quantity; // per item amount
            $totalAmount += $amount; // add to total

            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($item->item->name ?? '-') . '</td>
                <td align="center">' . ($item->invoice->id ?? '-') . '</td>
                <td align="center">' . number_format($item->quantity, 2) . ' ' . ($item->unit->shortcode ?? '-') .'</td>
                <td align="right">' . number_format($item->price, 2) . '</td>
                <td align="right">' . number_format($amount, 2) . '</td>
            </tr>';
        }

        // --- Totals ---
        $html .= '
            <tr>
                <td colspan="5" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($return->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($return->remarks) . '</span>';
            $pdf->writeHTML($remarksHtml, true, false, true, false, '');
        }

        // --- Signatures ---
        $pdf->Ln(20);
        $yPos = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');
        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('purchase_return_' . $return->id . '.pdf', 'I');
    }

}
