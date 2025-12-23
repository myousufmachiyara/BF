<?php

namespace App\Http\Controllers;

use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\Production;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionReceivingController extends Controller
{
    public function index()
    {
        $receivings = ProductionReceiving::with(['vendor', 'production'])
            ->withSum(['details as total_amount' => function ($query) {
                $query->select(\DB::raw('SUM(manufacturing_cost * received_qty)'));
            }], 'received_qty') // dummy second param, not used
            ->orderBy('id', 'desc')
            ->get();

        return view('production-receiving.index', compact('receivings'));
    }

    public function create(Request $request)
    {
        $productions = Production::all();
        $products = Product::get();
        $accounts = ChartOfAccounts::where('account_type','vendor')->get();

        // ✅ Get optional ID from query string
        $selectedProductionId = $request->query('id');

        return view('production-receiving.create', compact(
            'productions',
            'products',
            'selectedProductionId',
            'accounts'
        ));
    }

    public function store(Request $request)
    {
        Log::info('Production Receiving Store Request', $request->all());

        $validated = $request->validate([
            'production_id' => 'nullable|exists:productions,id',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id',
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
            'convance_charges' => 'required|numeric|min:0',
            'bill_discount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $grn_no = 'GRN-' . str_pad(ProductionReceiving::count() + 1, 5, '0', STR_PAD_LEFT);

            $receiving = ProductionReceiving::create([
                'production_id' => $validated['production_id'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'rec_date' => $validated['rec_date'],
                'grn_no' => $grn_no,
                'convance_charges' => $validated['convance_charges'],
                'bill_discount' => $validated['bill_discount'],
                'received_by' => auth()->id(),
            ]);

            foreach ($validated['item_details'] as $detail) {            
                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'product_id' => $detail['product_id'],
                    'variation_id' => $detail['variation_id'] ?? null,
                    'manufacturing_cost' => $detail['manufacturing_cost'],
                    'received_qty' => $detail['received_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                ]);

            }

            DB::commit();

            return redirect()->route('production_receiving.index')
                ->with('success', 'Production receiving created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Production Receiving Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save receiving. Please check logs.');
        }
    }

    public function edit($id)
    {
        $receiving = ProductionReceiving::with(['details.product', 'details.variation'])->findOrFail($id);
        $productions = Production::all();
        $products = Product::get();
        $accounts = ChartOfAccounts::where('account_type','vendor')->get();

        return view('production-receiving.edit', compact('receiving', 'productions', 'products','accounts'));
    }

    public function update(Request $request, $id) 
    {
        Log::info("Production Receiving Update Request: ", $request->all());

        $validated = $request->validate([
            'production_id' => 'nullable|exists:productions,id',
            'vendor_id' => 'required|exists:chart_of_accounts,id',            
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id', // nullable allowed
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
            'convance_charges' => 'required|numeric|min:0',
            'bill_discount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $receiving = ProductionReceiving::findOrFail($id);

            // Update master
            $receiving->update([
                'production_id' => $validated['production_id'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'rec_date' => $validated['rec_date'],
                'convance_charges' => $validated['convance_charges'],
                'bill_discount' => $validated['bill_discount'],
            ]);

            // Delete existing details (fresh replace)
            $receiving->details()->delete();

            // Insert new details
            $detailData = [];
            foreach ($validated['item_details'] as $detail) {
                $detailData[] = [
                    'production_receiving_id' => $receiving->id,
                    'product_id' => $detail['product_id'],
                    'variation_id' => $detail['variation_id'] ?? null,
                    'manufacturing_cost' => $detail['manufacturing_cost'],
                    'received_qty' => $detail['received_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ProductionReceivingDetail::insert($detailData);

            DB::commit();
            Log::info("ProductionReceiving #{$id} updated successfully.");

            return redirect()->route('production_receiving.index')
                ->with('success', 'Production receiving updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Production Receiving Update Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withInput()->withErrors([
                'error' => 'Failed to update receiving. Please check logs.',
            ]);
        }
    }

    public function print($id)
    {
        $receiving = ProductionReceiving::with(['production.vendor', 'details.product', 'details.variation'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Production Receiving #' . $receiving->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 8, 10, 40);
        }

        // --- Info Box ---
        $pdf->SetXY(130, 12);
        $infoHtml = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Receiving #</b></td><td>' . $receiving->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($receiving->rec_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Production</b></td><td>PROD-' . ($receiving->production->id ?? '-') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($receiving->production->vendor->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($infoHtml, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Bar ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(60, 8, 'Production Receiving', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 12);

        $pdf->Ln(5);

        // --- Items Table ---
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="20%">Item Name</th>
                <th width="30%">Variation</th>
                <th width="10%">M.Cost</th>
                <th width="10%">Received</th>
                <th width="23%">Remarks</th>
            </tr>';

        $count = 0;
        foreach ($receiving->details as $detail) {
            $count++;
            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($detail->product->name ?? '-') . '</td>
                <td>' . ($detail->variation->sku ?? '-') . '</td>
                <td align="right">' . number_format($detail->manufacturing_cost, 2) . '</td>
                <td align="center">' . $detail->received_qty . '</td>
                <td>' . ($detail->remarks ?? '-') . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="6" align="right"><b>Total Items:</b> ' . $receiving->details->count() . '</td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($receiving->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:9px;">' . nl2br($receiving->remarks) . '</span>';
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

        return $pdf->Output('production_receiving_' . $receiving->id . '.pdf', 'I');
    }
}
