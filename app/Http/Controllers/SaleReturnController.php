<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleInvoice;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with(['customer', 'items.product'])->latest()->get()
            ->map(function ($return) {
                $return->total_amount = $return->items->sum(fn($item) => $item->qty * $item->price);
                return $return;
            });

        return view('sale_returns.index', compact('returns'));
    }

    public function create()
    {
        return view('sale_returns.create', [
            'products'  => Product::orderBy('name')->get(),
            'customers' => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices'  => SaleInvoice::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'        => 'required|exists:chart_of_accounts,id',
            'return_date'        => 'required|date',
            'sale_invoice_no'    => 'nullable|string|max:50',
            'remarks'            => 'nullable|string|max:500',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Generate invoice number
            $last       = SaleReturn::withTrashed()->orderBy('id', 'desc')->first();
            $invoiceNo  = str_pad($last ? intval($last->invoice_no) + 1 : 1, 6, '0', STR_PAD_LEFT);

            $return = SaleReturn::create([
                'invoice_no'      => $invoiceNo,
                'account_id'      => $validated['customer_id'],
                'return_date'     => $validated['return_date'],
                'sale_invoice_no' => $validated['sale_invoice_no'] ?? null,
                'remarks'         => $validated['remarks'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'product_id'     => $item['product_id'],
                    'qty'            => $item['qty'],
                    'price'          => $item['price'],
                ]);
            }

            // Create accounting voucher
            $this->createVoucher(
                $validated['customer_id'],
                $validated['return_date'],
                $validated['items'],
                $invoiceNo
            );

            DB::commit();
            Log::info('[SaleReturn] Stored successfully', ['id' => $return->id]);

            return redirect()->route('sale_return.index')->with('success', 'Sale return created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Error saving sale return: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $return = SaleReturn::with(['items.product'])->findOrFail($id);

        return view('sale_returns.edit', [
            'return'    => $return,
            'products'  => Product::orderBy('name')->get(),
            'customers' => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices'  => SaleInvoice::latest()->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'account_id'         => 'required|exists:chart_of_accounts,id',
            'return_date'        => 'required|date',
            'sale_invoice_no'    => 'nullable|string|max:50',
            'remarks'            => 'nullable|string|max:500',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:1',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $return = SaleReturn::findOrFail($id);

            // Reverse old accounting entry
            Voucher::where('narration', 'Sale Return #' . $return->invoice_no)->delete();

            $return->update([
                'account_id'      => $validated['account_id'],
                'return_date'     => $validated['return_date'],
                'sale_invoice_no' => $validated['sale_invoice_no'] ?? null,
                'remarks'         => $validated['remarks'] ?? null,
            ]);

            // Replace line items
            $return->items()->delete();

            foreach ($validated['items'] as $item) {
                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'product_id'     => $item['product_id'],
                    'qty'            => $item['qty'],
                    'price'          => $item['price'],
                ]);
            }

            // Re-create voucher with updated amounts
            $this->createVoucher(
                $validated['account_id'],
                $validated['return_date'],
                $validated['items'],
                $return->invoice_no
            );

            DB::commit();
            Log::info('[SaleReturn] Updated successfully', ['id' => $return->id]);

            return redirect()->route('sale_return.index')->with('success', 'Sale return updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Update failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Error updating sale return: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $return = SaleReturn::findOrFail($id);

            // Reverse accounting entry before deleting
            Voucher::where('narration', 'Sale Return #' . $return->invoice_no)->delete();

            $return->items()->delete();
            $return->delete();

            DB::commit();
            Log::info('[SaleReturn] Deleted successfully', ['id' => $id]);

            return redirect()->route('sale_return.index')->with('success', 'Sale return deleted.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Delete failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Error deleting sale return: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $return = SaleReturn::with('items.product', 'account', 'saleInvoice')->findOrFail($id);
        return response()->json($return);
    }

    public function print($id)
    {
        $return = SaleReturn::with(['account', 'items.product'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Sale Return #' . $return->invoice_no);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Sale Return Invoice', 0, 1, 'R');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="40%">
            <tr><td>
                <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                    <tr>
                        <td width="30%"><b>Customer</b></td>
                        <td width="70%">' . ($return->account->name ?? '-') . '</td>
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
            </td></tr>
        </table>';

        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        $pdf->Ln(5);
        $html = '
        <table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="8%">S.No</th>
                <th width="50%">Product</th>
                <th width="12%">Qty</th>
                <th width="15%">Price</th>
                <th width="15%">Amount</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($return->items as $item) {
            $count++;
            $lineTotal    = $item->qty * $item->price;
            $totalAmount += $lineTotal;

            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td align="center">' . number_format($item->qty, 2) . '</td>
                <td align="right">' . number_format($item->price, 2) . '</td>
                <td align="right">' . number_format($lineTotal, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="4" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        if (!empty($return->remarks)) {
            $pdf->writeHTML(
                '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($return->remarks) . '</span>',
                true, false, true, false, ''
            );
        }

        $pdf->Ln(20);
        $yPos      = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');
        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('sale_return_' . $return->id . '.pdf', 'I');
    }

    /* ---------------------------------------------------------------
     | Private helper — double-entry for a sale return
     |
     | When a customer returns goods:
     |   Dr: Sales Revenue  (we reverse the revenue — customer no longer owes)
     |   Cr: Customer account (reduces their receivable / they get credit)
     |
     | Stock side is handled by InventoryReportController reading
     | sale_return_items directly — no separate inventory voucher needed
     | unless you want COGS reversal too (add below if required).
     --------------------------------------------------------------- */
    private function createVoucher(int $customerId, string $date, array $items, string $invoiceNo): void
    {
        $totalAmount = collect($items)->sum(fn($i) => $i['qty'] * $i['price']);

        $salesAccount = ChartOfAccounts::where('account_type', 'revenue')
            ->orWhere('name', 'Sales Revenue')
            ->first();

        if (!$salesAccount) {
            Log::warning('[SaleReturn] Voucher skipped — no revenue account found in COA.');
            return;
        }

        // Dr: Sales Revenue (reverses the original sale entry)
        // Cr: Customer     (reduces what they owe, or creates a credit balance)
        Voucher::create([
            'date'         => $date,
            'voucher_type' => 'journal',
            'ac_dr_sid'    => $salesAccount->id,  // Dr: Sales Revenue
            'ac_cr_sid'    => $customerId,         // Cr: Customer
            'amount'       => $totalAmount,
            'narration'    => 'Sale Return #' . $invoiceNo,
            'created_by'   => Auth::id(),
        ]);

        Log::info('[SaleReturn] Voucher created', [
            'narration' => 'Sale Return #' . $invoiceNo,
            'amount'    => $totalAmount,
        ]);
    }
}