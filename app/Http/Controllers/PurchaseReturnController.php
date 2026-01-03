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
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $lastInvoice = PurchaseReturn::withTrashed()
            ->orderBy('id', 'desc')
            ->first();

            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;

            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $purchaseReturn = PurchaseReturn::create([
                'invoice_no'=> $invoiceNo,
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'created_by' => auth()->id(),
            ]);

            Log::info('Purchase Return created', ['id' => $purchaseReturn->id]);

            foreach ($request->items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit'],
                    'price' => $item['price'],
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
            'items.unit'        // load unit for each item
        ])->findOrFail($id);

        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('purchase-returns.edit', compact('purchaseReturn', 'products', 'vendors', 'units'));
    }

    public function update(Request $request, $id)
    {
        Log::info('PurchaseReturn Update Request', $request->all());

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',

            // Validate nested items
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|exists:measurement_units,id',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::findOrFail($id);

            $purchaseReturn->update([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
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
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit'],
                    'price' => $item['price'],
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
        $return = PurchaseReturn::with(['vendor', 'items.item', 'items.unit'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Purchase Return #' . $return->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

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
        $pdf->Cell(80, 8, 'Purchase Return Invoice', 0, 1, 'R');

       
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
                            <td width="70%">'.($return->vendor->name ?? '-').'</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Invoice No</b></td>
                            <td width="70%">'.$return->invoice_no.'</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">'.\Carbon\Carbon::parse($return->invoice_date)->format('d-m-Y').'</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

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
