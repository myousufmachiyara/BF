<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleInvoice;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with(['customer','items.product','items.variation'])->latest()->get()
            ->map(function ($return) {
                $return->total_amount = $return->items->sum(function ($item) {
                    return $item->qty * $item->price; // adjust field name
                });
                return $return;
            });

        return view('sale_returns.index', compact('returns'));
    }

    public function create()
    {
        return view('sale_returns.create', [
            'products'  => Product::get(),
            'customers'  => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices'  => SaleInvoice::latest()->get(), // optional link to original
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'          => 'required|exists:chart_of_accounts,id',
            'return_date'          => 'required|date',
            'sale_invoice_no'      => 'nullable|string|max:50',
            'remarks'              => 'nullable|string|max:500',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.qty'          => 'required|numeric|min:1',
            'items.*.price'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            Log::info('[SaleReturn] Store request received', [
                'user_id' => Auth::id(),
                'payload' => $validated,
            ]);

            // Create Sale Return
            $return = SaleReturn::create([
                'account_id'     => $validated['customer_id'],
                'return_date'     => $validated['return_date'],
                'sale_invoice_no' => $validated['sale_invoice_no'] ?? null,
                'remarks'         => $validated['remarks'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            Log::info('[SaleReturn] Main record created', [
                'return_id' => $return->id,
                'customer'  => $return->customer_id,
            ]);

            // Create Sale Return Items
            foreach ($validated['items'] as $idx => $item) {
                try {
                    $savedItem = SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'product_id'     => $item['product_id'],
                        'variation_id'   => $item['variation_id'] ?? null,
                        'qty'            => $item['qty'],
                        'price'          => $item['price'],
                    ]);

                    Log::debug('[SaleReturn] Item created', [
                        'return_id'  => $return->id,
                        'item_index' => $idx,
                        'item_id'    => $savedItem->id,
                        'product_id' => $item['product_id'],
                    ]);
                } catch (\Throwable $itemEx) {
                    Log::error('[SaleReturn] Item save failed', [
                        'return_id'  => $return->id,
                        'item_index' => $idx,
                        'error'      => $itemEx->getMessage(),
                    ]);
                    throw $itemEx; // rethrow so transaction rolls back
                }
            }

            DB::commit();
            Log::info('[SaleReturn] Completed successfully', [
                'return_id' => $return->id,
                'by'        => Auth::id(),
            ]);

            return redirect()
                ->route('sale_return.index')
                ->with('success', 'Sale return created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Store failed', [
                'user_id'   => Auth::id(),
                'payload'   => $request->all(), // raw input for debugging
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Error saving sale return. Please contact administrator.');
        }
    }

    public function edit($id)
    {
        $return = SaleReturn::with(['items.product', 'items.variation'])->findOrFail($id);

        return view('sale_returns.edit', [
            'return'    => $return,
            'products'  => Product::get(),
            'customers' => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices'  => SaleInvoice::latest()->get(),
        ]);
    }
    
    public function update(Request $request, $id)
    {
        // Log the incoming request
        Log::info('[SaleReturn] Update Request', [
            'sale_return_id' => $id,
            'request_data'   => $request->except(['_token', '_method']),
        ]);

        $validated = $request->validate([
            'account_id'           => 'required|exists:chart_of_accounts,id',
            'return_date'          => 'required|date',
            'sale_invoice_no'      => 'nullable|string|max:50',
            'remarks'              => 'nullable|string|max:500',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.qty'          => 'required|numeric|min:1',
            'items.*.price'        => 'required|numeric|min:0',
        ]);

        // Log validated data
        Log::info('[SaleReturn] Validated Data', $validated);

        DB::beginTransaction();
        try {
            $return = SaleReturn::findOrFail($id);

            $return->update([
                'account_id'      => $validated['account_id'],
                'return_date'     => $validated['return_date'],
                'sale_invoice_no' => $validated['sale_invoice_no'] ?? null,
                'remarks'         => $validated['remarks'] ?? null,
            ]);

            // Delete old items and reinsert
            $return->items()->delete();

            foreach ($validated['items'] as $idx => $item) {
                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'product_id'     => $item['product_id'],
                    'variation_id'   => $item['variation_id'] ?? null,
                    'qty'            => $item['qty'],
                    'price'          => $item['price'],
                ]);
            }

            DB::commit();

            Log::info('[SaleReturn] Update Success', [
                'sale_return_id' => $return->id,
                'items_count'    => count($validated['items']),
            ]);

            return redirect()->route('sale_return.index')
                ->with('success', 'Sale return updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('[SaleReturn] Update Failed', [
                'sale_return_id' => $id,
                'error_message'  => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Error updating sale return.');
        }
    }

    public function show($id)
    {
        $return = SaleReturn::with('items.product','items.variation','account','saleInvoice')->findOrFail($id);
        return response()->json($return);
    }

    public function destroy($id)
    {
        try {
            $return = SaleReturn::findOrFail($id);
            $return->delete();
            return redirect()->route('sale_returns.index')->with('success','Sale return deleted.');
        } catch (\Throwable $e) {
            Log::error('[SaleReturn] Delete failed', ['error'=>$e->getMessage()]);
            return back()->with('error','Error deleting sale return.');
        }
    }

    public function print($id)
    {
        $return = SaleReturn::with(['customer','items.product','items.variation'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Sale Return #'.$return->id);
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
            <tr><td><b>Return #</b></td><td>'.$return->id.'</td></tr>
            <tr><td><b>Date</b></td><td>'.\Carbon\Carbon::parse($return->return_date)->format('d/m/Y').'</td></tr>
            <tr><td><b>Customer</b></td><td>'.($return->customer->name ?? '-').'</td></tr>
            <tr><td><b>Sale Invoice</b></td><td>'.($return->sale_invoice_no ?? '-').'</td></tr>
        </table>';
        $pdf->writeHTML($returnInfo, false, false, false, false, '');

        // --- Title Box ---
        $pdf->Line(60, 52.25, 200, 52.25);
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Sale Return', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="8%">S.No</th>
                <th width="25%">Product</th>
                <th width="30%">Variation</th>
                <th width="10%">Qty</th>
                <th width="12%">Price</th>
                <th width="15%">Total</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($return->items as $item) {
            $count++;
            $lineTotal = $item->qty * $item->price;
            $totalAmount += $lineTotal;

            $html .= '
            <tr>
                <td align="center">'.$count.'</td>
                <td>'.($item->product->name ?? '-').'</td>
                <td>'.($item->variation->sku ?? '-').'</td>
                <td align="center">'.number_format($item->qty, 2).'</td>
                <td align="right">'.number_format($item->price, 2).'</td>
                <td align="right">'.number_format($lineTotal, 2).'</td>
            </tr>';
        }

        // --- Totals ---
        $html .= '
            <tr>
                <td colspan="5" align="right"><b>Total</b></td>
                <td align="right"><b>'.number_format($totalAmount, 2).'</b></td>
            </tr>';

        if (!empty($return->discount)) {
            $totalAmount -= $return->discount;
            $html .= '
            <tr>
                <td colspan="5" align="right"><b>Return Discount</b></td>
                <td align="right">'.number_format($return->discount, 2).'</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="5" align="right"><b>Net Total</b></td>
                <td align="right"><b>'.number_format($totalAmount, 2).'</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($return->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">'.nl2br($return->remarks).'</span>';
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

        return $pdf->Output('sale_return_'.$return->id.'.pdf', 'I');
    }

}
