<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBilty;
use App\Models\PurchaseBiltyDetail;
use App\Models\PurchaseInvoice;
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

class PurchaseBiltyController extends Controller
{
    public function index()
    {
        $invoices = PurchaseBilty::with('vendor')->latest()->get();
        return view('purchase-bilty.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        $purchaseInvoices = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_no']);

        return view('purchase-bilty.create', compact('products', 'vendors','units', 'purchaseInvoices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_id'   => 'nullable|exists:purchase_invoices,id',
            'vendor_id'     => 'required|exists:chart_of_accounts,id',
            'bilty_date'    => 'required|date',
            'bilty_amount'  => 'required|numeric',
            'items'         => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit'     => 'required|exists:measurement_units,id',
        ]);

        try {
            \DB::beginTransaction();
            \Log::info('Step 1: Started transaction');

            // Create Purchase Bilty
            $bilty = PurchaseBilty::create([
                'purchase_id'   => $request->purchase_id,
                'vendor_id'     => $request->vendor_id,
                'bilty_date'    => $request->bilty_date,
                'ref_no'        => $request->ref_no ?? null,
                'remarks'       => $request->remarks ?? null,
                'bilty_amount'  => $request->bilty_amount,
                'created_by'    => auth()->id(),
            ]);
            \Log::info('Step 2: Purchase Bilty created', ['bilty_id' => $bilty->id]);

            // Insert Bilty Details
            foreach ($request->items as $key => $item) {
                \Log::info("Step 3: Processing item index {$key}", ['item' => $item]);

                $detail = PurchaseBiltyDetail::create([
                    'bilty_id' => $bilty->id,
                    'item_id'  => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit'     => $item['unit'],
                    'remarks'  => $item['remarks'] ?? null,
                ]);

                \Log::info("Step 4: Bilty detail created", ['detail_id' => $detail->id]);
            }

            \DB::commit();
            \Log::info('Step 5: Transaction committed successfully');

            return redirect()->route('purchase_bilty.index')->with('success', 'Purchase Bilty created successfully.');

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Purchase Bilty Store Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return redirect()->back()->withInput()->with('error', 'Something went wrong while creating the Purchase Bilty.');
        }
    }

    public function edit($id)
    {
        try {

            $bilty = PurchaseBilty::with('details')->findOrFail($id);

            $products = Product::get();
            $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
            $units = MeasurementUnit::all();
            $purchaseInvoices = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_no']);

            return view('purchase-bilty.edit', compact(
                'bilty',
                'products',
                'vendors',
                'units',
                'purchaseInvoices'
            ));

        } catch (\Exception $e) {
            \Log::error('Purchase Bilty Edit Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return redirect()->route('purchase_bilty.index')
                ->with('error', 'Unable to load Purchase Bilty for editing.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'purchase_id'   => 'nullable|exists:purchase_invoices,id',
            'vendor_id'     => 'required|exists:chart_of_accounts,id',
            'bilty_date'    => 'required|date',
            'bilty_amount'  => 'required|numeric',
            'items'         => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit'     => 'required|exists:measurement_units,id',
        ]);

        try {
            \DB::beginTransaction();
            \Log::info('Update Step 1: Transaction started', ['bilty_id' => $id]);

            $bilty = PurchaseBilty::findOrFail($id);

            // Update main bilty
            $bilty->update([
                'purchase_id'  => $request->purchase_id,
                'vendor_id'    => $request->vendor_id,
                'bilty_date'   => $request->bilty_date,
                'ref_no'       => $request->ref_no ?? null,
                'remarks'      => $request->remarks ?? null,
                'bilty_amount' => $request->bilty_amount,
            ]);

            \Log::info('Update Step 2: Bilty updated', ['bilty_id' => $bilty->id]);

            // Remove old details
            PurchaseBiltyDetail::where('bilty_id', $bilty->id)->delete();
            \Log::info('Update Step 3: Old bilty details deleted');

            // Insert new details
            foreach ($request->items as $key => $item) {
                \Log::info("Update Step 4: Processing item {$key}", $item);

                $detail = PurchaseBiltyDetail::create([
                    'bilty_id' => $bilty->id,
                    'item_id'  => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit'     => $item['unit'],
                    'remarks'  => $item['remarks'] ?? null,
                ]);

                \Log::info('Update Step 5: Detail created', [
                    'detail_id' => $detail->id
                ]);
            }

            \DB::commit();
            \Log::info('Update Step 6: Transaction committed successfully');

            return redirect()
                ->route('purchase_bilty.index')
                ->with('success', 'Purchase Bilty updated successfully.');

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Purchase Bilty Update Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Something went wrong while updating the Purchase Bilty.');
        }
    }

    public function destroy($id)
    {
        $invoice = PurchaseBilty::findOrFail($id);

        // ✅ Check before looping
        if ($invoice->attachments) {
            foreach ($invoice->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $invoice->delete();

        return redirect()
            ->route('purchase_bilty.index')
            ->with('success', 'Purchase Bilty Invoice deleted successfully.');
    }


    public function getInvoiceItems($id) {
        // Fetch items with product relation to get unit and bilty charges
        $items = \App\Models\PurchaseInvoiceItem::where('purchase_invoice_id', $id)->get();
        return response()->json($items);
    }

    public function print($id)
    {
        $bilty = PurchaseBilty::with([
            'vendor',
            'purchase',
            'details.product',
            'details.measurementUnit'
        ])->findOrFail($id);

        $pdf = new \TCPDF();

        // Basic PDF settings
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAuthor('Bilwani Furnitures');
        $pdf->SetTitle('BILTY-' . $bilty->id);
        $pdf->SetSubject('Purchase Bilty');
        $pdf->SetKeywords('BILTY, TCPDF, PDF');

        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // -------------------------------
        // Company Header
        // -------------------------------
        $logoPath = public_path('assets/img/bf_logo.jpg');

        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        // Title (Top Right)
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Purchase Bilty', 0, 1, 'R');

        // -------------------------------
        // Vendor + Bilty Info
        // -------------------------------
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="40%">
            <tr>
                <td>
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="30%"><b>Vendor</b></td>
                            <td width="70%">' . ($bilty->vendor->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Bilty No</b></td>
                            <td width="70%">BILTY-' . $bilty->id . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">' . \Carbon\Carbon::parse($bilty->bilty_date)->format('d-m-Y') . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        // -------------------------------
        // Items Table
        // -------------------------------
        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="10%">#</th>
                <th width="60%">Item</th>
                <th width="30%">Qty</th>
            </tr>';

        $count = 0;
        $totalQty = 0;

        foreach ($bilty->details as $item) {
            $count++;
            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
            </tr>';

            $totalQty += $item->quantity;
        }

        $html .= '
            <tr>
                <td colspan="2" align="right"><b>Total</b></td>
                <td><b>' . number_format($totalQty, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        // -------------------------------
        // Bilty Amount Summary
        // -------------------------------
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Bilty Amount: PKR ' . number_format($bilty->bilty_amount, 2), 0, 1, 'R');

        // -------------------------------
        // Footer Signatures
        // -------------------------------
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

        return $pdf->Output('purchase_bilty_' . $bilty->id . '.pdf', 'I');
    }

}
