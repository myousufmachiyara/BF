<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Models\ProductionReturn;
use App\Models\ProductionReturnItem;
use App\Models\Production;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;

class ProductionReturnController extends Controller
{
    public function index()
    {
        $returns = ProductionReturn::with('vendor')
            ->withSum('items as total_amount', \DB::raw('quantity * price'))
            ->latest()
            ->get();

        return view('production-return.index', compact('returns'));
    }

    public function create()
    {
        $productions = Production::with('vendor')->get();
        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('production-return.create', compact('productions', 'products', 'vendors', 'units'));
    }

    public function store(Request $request)
    {
        Log::info('Production Return store() called', [
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        $request->validate([
            'vendor_id'   => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks'     => 'nullable|string|max:1000',

            'items.*.item_id'       => 'required|exists:products,id',
            'items.*.variation_id'  => 'nullable|exists:product_variations,id',
            'items.*.production_id' => 'nullable|exists:productions,id',
            'items.*.quantity'      => 'required|numeric|min:0',
            'items.*.unit'          => 'required|exists:measurement_units,id',
            'items.*.price'         => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $return = ProductionReturn::create([
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'created_by'  => auth()->id(),
            ]);

            Log::info('ProductionReturn created', [
                'return_id' => $return->id,
                'vendor_id' => $return->vendor_id,
            ]);

            foreach ($request->items as $index => $item) {
                $row = ProductionReturnItem::create([
                    'production_return_id' => $return->id,
                    'product_id'           => $item['item_id'],
                    'variation_id'         => $item['variation_id'] ?? null,
                    'production_id'        => $item['production_id'] ?? null,
                    'quantity'             => $item['quantity'],
                    'unit_id'              => $item['unit'],
                    'price'                => $item['price'],
                ]);

                Log::info("ProductionReturnItem created", [
                    'row_index' => $index,
                    'row_id'    => $row->id,
                    'product'   => $item['item_id'],
                    'variation' => $item['variation_id'] ?? null,
                    'qty'       => $item['quantity'],
                    'price'     => $item['price'],
                    'amount'    => $item['amount'],
                ]);
            }

            DB::commit();

            Log::info('Production Return committed successfully', [
                'return_id'   => $return->id,
                'total_items' => count($request->items),
                'user_id'     => auth()->id(),
            ]);

            return redirect()->route('production_return.index')
                ->with('success', 'Production Return saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Production Return store failed', [
                'user_id'     => auth()->id(),
                'request'     => $request->all(),
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->withErrors(['error' => 'Failed to save: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $return = ProductionReturn::with('items')->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::with('variations')->get();
        $units = MeasurementUnit::all();

        return view('production-return.edit', compact('return', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        Log::info('Production Return update() called', [
            'user_id'      => auth()->id(),
            'return_id'    => $id,
            'request_data' => $request->all()
        ]);

        try {
            // Step 1: Validate
            Log::info('Validating request data for Production Return update', ['return_id' => $id]);

            $request->validate([
                'vendor_id'   => 'required|exists:chart_of_accounts,id',
                'return_date' => 'required|date',
                'remarks'     => 'nullable|string|max:1000',

                'items.*.item_id'       => 'required|exists:products,id',
                'items.*.variation_id'  => 'nullable|exists:product_variations,id',
                'items.*.production_id' => 'nullable|exists:productions,id',
                'items.*.quantity'      => 'required|numeric|min:0',
                'items.*.unit'          => 'required|exists:measurement_units,id',
                'items.*.price'         => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Step 2: Find return
            $return = ProductionReturn::findOrFail($id);
            Log::info('Found Production Return record', ['return_id' => $return->id]);

            // Step 3: Update header
            $return->update([
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'updated_by'  => auth()->id(),
            ]);
            Log::info('Updated Production Return header', [
                'return_id' => $return->id,
                'vendor_id' => $return->vendor_id,
                'return_date' => $return->return_date
            ]);

            // Step 4: Replace items
            $return->items()->delete();
            Log::info('Deleted old items for Production Return', ['return_id' => $return->id]);

            foreach ($request->items as $i => $item) {
                $created = ProductionReturnItem::create([
                    'production_return_id' => $return->id,
                    'product_id'           => $item['item_id'],
                    'variation_id'         => $item['variation_id'] ?? null,
                    'production_id'        => $item['production_id'] ?? null,
                    'quantity'             => $item['quantity'],
                    'unit_id'              => $item['unit'],
                    'price'                => $item['price'],
                    'amount'               => $item['amount'],
                ]);
                Log::info('Created Production Return item', [
                    'return_id' => $return->id,
                    'item_index' => $i,
                    'item_data' => $created->toArray()
                ]);
            }

            DB::commit();
            Log::info('Production Return updated successfully', ['return_id' => $return->id]);

            return redirect()->route('production_return.index')
                            ->with('success', 'Production Return updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            // Validation errors logged separately
            Log::warning('Validation failed during Production Return update', [
                'errors'    => $ve->errors(),
                'return_id' => $id
            ]);
            throw $ve; // Let Laravel redirect back with errors
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Production Return update failed', [
                'error_message' => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
                'return_id'     => $id
            ]);
            return back()->withInput()
                        ->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    public function print($id)
    {
        $return = ProductionReturn::with(['vendor', 'items.product', 'items.unit', 'items.productionReturn'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 8, 10, 40);
        }

        // --- Return Info ---
        $pdf->SetXY(130, 12);
        $returnInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Return #</b></td><td>' . $return->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($return->return_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($return->vendor->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($returnInfo, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Bar ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 8, 'Production Return', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 12);

        $pdf->Ln(5);

        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="28%">Item Name</th>
                <th width="12%">Prod. #</th>
                <th width="20%">Qty</th>
                <th width="15%">Rate</th>
                <th width="18%">Amount</th>
            </tr>';

        $totalAmount = 0;
        $count = 0;

        foreach ($return->items as $item) {
            $count++;
            $amount = $item->price * $item->quantity;
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . ($item->production->id ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . ' ' . ($item->unit->shortcode ?? '-') . '</td>
                <td align="right">' . number_format($item->price, 2) . '</td>
                <td align="right">' . number_format($amount, 2) . '</td>
            </tr>';
        }

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

        return $pdf->Output('production_return_' . $return->id . '.pdf', 'I');
    }
}
