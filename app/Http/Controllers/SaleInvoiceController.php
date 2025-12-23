<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{
    public function create()
    {
        return view('sales.create', [
            'products' => Product::get(),
            'productions' => Production::latest()->get(),
            'accounts' => ChartOfAccounts::where('account_type', 'customer')->get(), // or your logic
        ]);
    }

    public function store(Request $request)
    {

        // ✅ Validate incoming request
        $validated = $request->validate([
            'date'         => 'required|date',
            'account_id'   => 'required|exists:chart_of_accounts,id',
            'type'         => 'required|in:cash,credit',
            'discount'     => 'nullable|numeric|min:0',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.disc_price'   => 'nullable|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.total'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            Log::info('[SaleInvoice] Store start', [
                'request' => $validated,
                'user_id' => Auth::id(),
            ]);

            // ✅ Create invoice
            $invoice = SaleInvoice::create([
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'created_by' => Auth::id(),
                'remarks'    => $request->remarks,
            ]);

            Log::info('[SaleInvoice] Invoice created', [
                'invoice_id' => $invoice->id,
                'account_id' => $invoice->account_id,
            ]);

            // ✅ Save items
            foreach ($validated['items'] as $index => $item) {
                try {
                    $saved = SaleInvoiceItem::create([
                        'sale_invoice_id' => $invoice->id,
                        'product_id'      => $item['product_id'],
                        'variation_id'    => $item['variation_id'] ?? null,
                        'sale_price'      => $item['sale_price'],
                        'discount'        => $item['disc_price'] ?? 0,
                        'quantity'        => $item['quantity'],
                    ]);

                    Log::info('[SaleInvoiceItem] Saved', [
                        'invoice_id' => $invoice->id,
                        'item_index' => $index,
                        'item_id'    => $saved->id,
                        'data'       => $item,
                    ]);
                } catch (\Throwable $itemEx) {
                    Log::error('[SaleInvoiceItem] Save failed', [
                        'invoice_id' => $invoice->id,
                        'item_index' => $index,
                        'item_data'  => $item,
                        'error'      => $itemEx->getMessage(),
                    ]);
                    throw $itemEx; // rollback everything
                }
            }

            DB::commit();
            Log::info('[SaleInvoice] Transaction committed', [
                'invoice_id' => $invoice->id,
            ]);

            return redirect()->route('sale_invoices.index')
                ->with('success', 'Sale invoice created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Store failed', [
                'request_data' => $request->all(),
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Error saving invoice. Please contact administrator.');
        }
    }

    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'items.variation', 'items.production', 'account')
            ->latest()->get();

        return view('sales.index', compact('invoices'));
    }

    public function edit($id)
    {
        $invoice = SaleInvoice::with('items.product', 'items.variation')->findOrFail($id);

        return view('sales.edit', [
            'invoice'   => $invoice,
            'products'  => Product::get(),
            'accounts'  => ChartOfAccounts::where('account_type', 'customer')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'         => 'required|date',
            'account_id'   => 'required|exists:chart_of_accounts,id',
            'type'         => 'required|in:cash,credit',
            'discount'     => 'nullable|numeric|min:0',
            'remarks'      => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.disc_price'   => 'nullable|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.total'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $invoice = SaleInvoice::findOrFail($id);

            // ✅ Update invoice
            $invoice->update([
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $request->remarks,
            ]);

            // ✅ Remove old items & re-insert
            $invoice->items()->delete();

            foreach ($validated['items'] as $item) {
                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'variation_id'    => $item['variation_id'] ?? null,
                    'sale_price'      => $item['sale_price'],
                    'discount'        => $item['disc_price'] ?? 0,
                    'quantity'        => $item['quantity'],
                ]);
            }

            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Sale invoice updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()->with('error', 'Error updating invoice. Please contact administrator.');
        }
    }

    public function show($id)
    {
        $invoice = SaleInvoice::with('items.product', 'items.variation', 'items.production', 'account')
            ->findOrFail($id);
        return response()->json($invoice);
    }

    public function print($id)
    {
        $invoice = SaleInvoice::with(['account', 'items.product', 'items.variation'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Sale Invoice #' . $invoice->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 8, 10, 40);
        }

        // --- Invoice Info Box ---
        $pdf->SetXY(130, 12);
        $invoiceInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Invoice #</b></td><td>' . $invoice->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($invoice->date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Customer</b></td><td>' . ($invoice->account->name ?? '-') . '</td></tr>
            <tr><td><b>Type</b></td><td>' . ucfirst($invoice->type) . '</td></tr>
        </table>';
        $pdf->writeHTML($invoiceInfo, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Sale Invoice', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="20%">Product</th>
                <th width="28%">Variation</th>
                <th width="8%">Qty</th>
                <th width="11%">Price</th>
                <th width="13%">Discount</th>
                <th width="13%">Total</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($invoice->items as $item) {
            $count++;

            $discountPercent = $item->discount ?? 0;
            $discountAmount = ($item->sale_price * $discountPercent) / 100;
            $netPrice = $item->sale_price - $discountAmount;
            $lineTotal = $netPrice * $item->quantity;
            $totalAmount += $lineTotal;

            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . ($item->variation->sku ?? '-') . '</td>
                <td align="center">'. $item->quantity . '</td>
                <td align="right">' . number_format($item->sale_price, 2) . '</td>
                <td align="right">' . number_format($item->discount, 0) . '%</td>
                <td align="right">' . number_format($lineTotal, 2) . '</td>
            </tr>';
        }

        // --- Totals ---
        $html .= '
            <tr>
                <td colspan="6" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>';

        if (!empty($invoice->discount)) {
            $totalAmount -= $invoice->discount;
            $html .= '
            <tr>
                <td colspan="6" align="right"><b>Invoice Discount (PKR) </b></td>
                <td align="right">' . number_format($invoice->discount, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="6" align="right"><b>Net Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($invoice->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($invoice->remarks) . '</span>';
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

        return $pdf->Output('sale_invoice_' . $invoice->id . '.pdf', 'I');
    }
}
