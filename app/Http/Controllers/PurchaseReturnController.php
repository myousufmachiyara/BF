<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;
use App\Models\Voucher;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with('vendor')
            ->withSum('items as total_amount', DB::raw('quantity * price'))
            ->latest()
            ->get();

        return view('purchase-returns.index', compact('returns'));
    }

    public function create()
    {
        $invoices = PurchaseInvoice::with('vendor')->get();
        $products = Product::get();
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units    = MeasurementUnit::all();

        return view('purchase-returns.create', compact('invoices', 'products', 'units', 'vendors'));
    }

    public function store(Request $request)
    {
        Log::info('Storing Purchase Return', ['request' => $request->all()]);

        $request->validate([
            'vendor_id'            => 'required|exists:chart_of_accounts,id',
            'return_date'          => 'required|date',
            'remarks'              => 'nullable|string|max:1000',
            'items.*.item_id'      => 'required|exists:products,id',
            'items.*.quantity'     => 'required|numeric|min:0',
            'items.*.unit'         => 'required|exists:measurement_units,id',
            'items.*.price'        => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate invoice number
            $lastReturn  = PurchaseReturn::withTrashed()->orderBy('id', 'desc')->first();
            $nextNumber  = $lastReturn ? intval($lastReturn->invoice_no) + 1 : 1;
            $invoiceNo   = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            // Create purchase return header
            $purchaseReturn = PurchaseReturn::create([
                'invoice_no'  => $invoiceNo,
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'created_by'  => auth()->id(),
            ]);

            Log::info('Purchase Return created', ['id' => $purchaseReturn->id]);

            // Create line items
            foreach ($request->items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id'            => $item['item_id'],
                    'quantity'           => $item['quantity'],
                    'unit_id'            => $item['unit'],
                    'price'              => $item['price'],
                ]);
            }

            Log::info('Purchase Return items created', ['purchase_return_id' => $purchaseReturn->id]);

            // Create accounting voucher
            // Dr: Vendor (reduces payable) | Cr: Purchase/Inventory account
            $this->createVoucher(
                $request->vendor_id,
                $request->return_date,
                $request->items,
                $invoiceNo
            );

            DB::commit();
            Log::info('Purchase Return transaction committed', ['id' => $purchaseReturn->id]);

            return redirect()->route('purchase_return.index')
                ->with('success', 'Purchase Return saved successfully.');

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
        $purchaseReturn = PurchaseReturn::with(['items', 'items.unit'])->findOrFail($id);
        $products = Product::all();
        $vendors  = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units    = MeasurementUnit::all();

        return view('purchase-returns.edit', compact('purchaseReturn', 'products', 'vendors', 'units'));
    }

    public function update(Request $request, $id)
    {
        Log::info('PurchaseReturn Update Request', $request->all());

        $request->validate([
            'vendor_id'            => 'required|exists:chart_of_accounts,id',
            'return_date'          => 'required|date',
            'remarks'              => 'nullable|string|max:1000',
            'items.*.item_id'      => 'required|exists:products,id',
            'items.*.quantity'     => 'required|numeric|min:0',
            'items.*.unit'         => 'required|exists:measurement_units,id',
            'items.*.price'        => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::findOrFail($id);

            // Reverse old accounting entry before making any changes
            Voucher::where('narration', 'Purchase Return #' . $purchaseReturn->invoice_no)->delete();

            // Update header
            $purchaseReturn->update([
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
            ]);

            Log::info('PurchaseReturn header updated', ['id' => $purchaseReturn->id]);

            // Replace line items
            PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)->delete();
            Log::info('Old PurchaseReturnItems deleted', ['purchase_return_id' => $purchaseReturn->id]);

            foreach ($request->items as $item) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id'            => $item['item_id'],
                    'quantity'           => $item['quantity'],
                    'unit_id'            => $item['unit'],
                    'price'              => $item['price'],
                ]);
            }

            Log::info('New PurchaseReturnItems created', ['purchase_return_id' => $purchaseReturn->id]);

            // Re-create accounting voucher with updated amounts
            $this->createVoucher(
                $request->vendor_id,
                $request->return_date,
                $request->items,
                $purchaseReturn->invoice_no
            );

            DB::commit();
            Log::info('PurchaseReturn update committed', ['id' => $purchaseReturn->id]);

            return redirect()->route('purchase_return.index')
                ->with('success', 'Purchase Return updated successfully.');

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

        // Logo
        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        // Title
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Purchase Return Invoice', 0, 1, 'R');

        // Header info table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="40%">
            <tr>
                <td>
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr>
                            <td width="30%"><b>Vendor</b></td>
                            <td width="70%">' . ($return->vendor->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Return No</b></td>
                            <td width="70%">' . $return->invoice_no . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">' . Carbon::parse($return->return_date)->format('d-m-Y') . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        // Items table
        $pdf->Ln(5);
        $html = '
        <table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="33%">Item Name</th>
                <th width="20%">Qty</th>
                <th width="15%">Rate</th>
                <th width="25%">Amount</th>
            </tr>';

        $totalAmount = 0;
        $count       = 0;

        foreach ($return->items as $item) {
            $count++;
            $amount       = $item->price * $item->quantity;
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td align="center">'  . $count . '</td>
                <td>'                 . ($item->item->name ?? '-') . '</td>
                <td align="center">'  . number_format($item->quantity, 2) . ' ' . ($item->unit->shortcode ?? '-') . '</td>
                <td align="right">'   . number_format($item->price, 2) . '</td>
                <td align="right">'   . number_format($amount, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr>
                <td colspan="4" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Remarks
        if (!empty($return->remarks)) {
            $pdf->writeHTML(
                '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($return->remarks) . '</span>',
                true, false, true, false, ''
            );
        }

        // Signatures
        $pdf->Ln(20);
        $yPos      = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');

        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('purchase_return_' . $return->id . '.pdf', 'I');
    }

    /* ---------------------------------------------------------------
     | Private helper — creates the double-entry voucher
     | Dr: Vendor account  (reduces payable)
     | Cr: Purchase/Inventory account  (goods leaving inventory)
     --------------------------------------------------------------- */
    private function createVoucher(int $vendorId, string $date, array $items, string $invoiceNo): void
    {
        $totalAmount = collect($items)->sum(fn($i) => $i['quantity'] * $i['price']);

        $purchaseAccount = ChartOfAccounts::where('account_type', 'purchase')
            ->orWhere('account_type', 'inventory')
            ->first();

        if (!$purchaseAccount) {
            Log::warning('Purchase Return voucher skipped — no purchase/inventory account found in COA.');
            return;
        }

        Voucher::create([
            'date'       => $date,
            'ac_dr_sid'  => $vendorId,
            'ac_cr_sid'  => $purchaseAccount->id,
            'amount'     => $totalAmount,
            'narration'  => 'Purchase Return #' . $invoiceNo,
            'created_by' => auth()->id(),
        ]);

        Log::info('Purchase Return voucher created', [
            'narration' => 'Purchase Return #' . $invoiceNo,
            'amount'    => $totalAmount,
        ]);
    }
}