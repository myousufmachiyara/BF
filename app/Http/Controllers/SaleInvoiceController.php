<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleItemCustomization;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'account')
            ->latest()->get();

        return view('sales.index', compact('invoices'));
    }

    public function create()
    {
        return view('sales.create', [
            'products' => Product::get(),
            'accounts' => ChartOfAccounts::where('account_type', 'customer')->get(), // or your logic
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'         => 'required|date',
            'account_id'   => 'required|exists:chart_of_accounts,id',
            'type'         => 'required|in:cash,credit',
            'discount'     => 'nullable|numeric|min:0',
            'remarks'      => 'nullable|string',

            'items'        => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:1',

            // 🔥 Customization rules
            'items.*.customizations'    => 'nullable|array',
            'items.*.customizations.*'  => 'exists:products,id',
        ]);

        DB::beginTransaction();

        try {
            Log::info('[SaleInvoice] Store started', [
                'user_id' => Auth::id(),
                'payload' => $validated,
            ]);

             $lastInvoice = SaleInvoice::withTrashed()
            ->orderBy('id', 'desc')
            ->first();

            $nextNumber = $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1;

            $invoiceNo = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            /* Create Invoice */
            $invoice = SaleInvoice::create([
                'invoice_no'       => $invoiceNo,
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            Log::info('[SaleInvoice] Invoice created', [
                'invoice_id' => $invoice->id,
            ]);

            /* Create Invoice Items */
            foreach ($validated['items'] as $index => $item) {

                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                    'discount'        => 0,
                ]);

                Log::info('[SaleInvoiceItem] Item saved', [
                    'invoice_item_id' => $invoiceItem->id,
                    'product_id'      => $item['product_id'],
                ]);

                /* Save Customizations (OPTIONAL) */
                if (!empty($item['customizations'])) {

                    foreach ($item['customizations'] as $customItemId) {

                        SaleItemCustomization::create([
                            'sale_invoice_id'        => $invoice->id,
                            'sale_invoice_items_id' => $invoiceItem->id,
                            'item_id'               => $customItemId,
                        ]);

                        Log::info('[SaleItemCustomization] Saved', [
                            'invoice_id'        => $invoice->id,
                            'invoice_item_id'   => $invoiceItem->id,
                            'custom_item_id'    => $customItemId,
                        ]);
                    }
                }
            }

            DB::commit();

            Log::info('[SaleInvoice] Transaction committed', [
                'invoice_id' => $invoice->id,
            ]);

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Sale invoice created successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('[SaleInvoice] Store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error saving invoice.');
        }
    }

    public function edit($id)
    {
        $invoice = SaleInvoice::with('items.product','items.customizations.item')->findOrFail($id);

        return view('sales.edit', [
            'invoice'   => $invoice,
            'products'  => Product::get(),
            'accounts'  => ChartOfAccounts::where('account_type', 'customer')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'       => 'required|date',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'type'       => 'required|in:cash,credit',
            'discount'   => 'nullable|numeric|min:0',
            'remarks'    => 'nullable|string',

            'items'      => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.sale_price' => 'required|numeric|min:0',
            'items.*.quantity'   => 'required|numeric|min:1',

            // 👇 customizations
            'items.*.customizations'   => 'nullable|array',
            'items.*.customizations.*' => 'exists:products,id',
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

            // 🔥 Remove old items & customizations
            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();

            // ✅ Reinsert items + customizations
            foreach ($validated['items'] as $item) {

                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                ]);

                // 👉 Save customizations (optional)
                if (!empty($item['customizations'])) {
                    foreach ($item['customizations'] as $customItemId) {
                        SaleItemCustomization::create([
                            'sale_invoice_id'        => $invoice->id,
                            'sale_invoice_items_id' => $invoiceItem->id,
                            'item_id'               => $customItemId,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('sale_invoices.index')
                ->with('success', 'Sale invoice updated successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('[SaleInvoice] Update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error updating invoice.');
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
        $invoice = SaleInvoice::with(['account', 'items.product'])->findOrFail($id);

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
        <table cellpadding="3" cellspacing="0" width="40%">
            <tr>
                <td>
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
        $totalAmount = 0;

        foreach ($invoice->items as $item) {
            $count++;

            $discount = ($item->sale_price * ($item->discount ?? 0)) / 100;
            $netPrice = $item->sale_price - $discount;
            $lineTotal = $netPrice * $item->quantity;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . number_format($netPrice, 2) . '</td>
                <td>' . number_format($lineTotal, 2) . '</td>
            </tr>';

            $totalQty += $item->quantity;
            $totalAmount += $lineTotal;
        }

        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td></td>
            <td><b>' . number_format($totalAmount, 2) . '</b></td>
        </tr>';

        if (!empty($invoice->discount)) {
            $html .= '
            <tr>
                <td colspan="4" align="right"><b>Invoice Discount</b></td>
                <td>' . number_format($invoice->discount, 2) . '</td>
            </tr>';

            $totalAmount -= $invoice->discount;
        }

        $html .= '
        <tr style="background-color:#f5f5f5;">
            <td colspan="4" align="right"><b>Net Total</b></td>
            <td><b>' . number_format($totalAmount, 2) . '</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        /* ---------------- Remarks ---------------- */
        if (!empty($invoice->remarks)) {
            $pdf->Ln(5);
            $pdf->writeHTML('<b>Remarks:</b><br>' . nl2br($invoice->remarks), true, false, false, false, '');
        }

        /* ---------------- Footer Signatures ---------------- */
        $pdf->Ln(20);
        $lineWidth = 60;
        $yPosition = $pdf->GetY();

        $pdf->Line(28, $yPosition, 20 + $lineWidth, $yPosition);
        $pdf->Line(130, $yPosition, 120 + $lineWidth, $yPosition);

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 12);

        $pdf->SetXY(23, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Received By', 0, 0, 'C');

        $pdf->SetXY(125, $yPosition);
        $pdf->Cell($lineWidth, 10, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('sale_invoice_' . $invoice->id . '.pdf', 'I');
    }

}
