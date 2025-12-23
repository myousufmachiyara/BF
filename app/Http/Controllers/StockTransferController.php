<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StockTransferController extends Controller
{
    // List all stock transfers
    public function index()
    {
        try {
            $transfers = StockTransfer::with(['fromLocation', 'toLocation', 'details.product', 'details.variation'])
                ->orderBy('date', 'desc')
                ->get();

            return view('stock-transfer.index', compact('transfers'));
        } catch (\Exception $e) {
            Log::error('Failed to load stock transfers: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Failed to load stock transfers.');
        }
    }

    // Show create form
    public function create()
    {
        try {
            $locations = Location::all();
            $products = Product::with('variations')->get();
            return view('stock-transfer.create', compact('locations', 'products'));
        } catch (\Exception $e) {
            Log::error('Failed to load create stock transfer form: '.$e->getMessage());
            return back()->with('error', 'Failed to load create form.');
        }
    }

    // Store new stock transfer
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id',
            'remarks' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            \DB::beginTransaction();

            $transfer = StockTransfer::create([
                'date' => $request->date,
                'remarks' => $request->remarks,
                'from_location_id' => $request->from_location_id,
                'to_location_id' => $request->to_location_id,
                'to_location_id' => $request->to_location_id,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                StockTransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            \DB::commit();
            return redirect()->route('stock_transfer.index')->with('success', 'Stock transfer created successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to store stock transfer: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Failed to create stock transfer.');
        }
    }

    // Show edit form
    public function edit($id)
    {
        try {
            $transfer = StockTransfer::with(['details'])->findOrFail($id);
            $locations = Location::all();
            $products = Product::with('variations')->get();
            return view('stock-transfer.edit', compact('transfer', 'locations', 'products'));
        } catch (\Exception $e) {
            Log::error('Failed to load edit stock transfer form: '.$e->getMessage());
            return back()->with('error', 'Failed to load edit form.');
        }
    }

    // Update existing stock transfer
    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id',
            'remarks' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            \DB::beginTransaction();

            $transfer = StockTransfer::findOrFail($id);
            $transfer->update([
                'date' => $request->date,
                'from_location_id' => $request->from_location_id,
                'to_location_id' => $request->to_location_id,
                'remarks' => $request->remarks,
            ]);

            // Delete old details and insert new
            $transfer->details()->delete();
            foreach ($request->items as $item) {
                StockTransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            \DB::commit();
            return redirect()->route('stock_transfer.index')->with('success', 'Stock transfer updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to update stock transfer: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Failed to update stock transfer.');
        }
    }

    // Delete a stock transfer
    public function destroy($id)
    {
        try {
            $transfer = StockTransfer::findOrFail($id);
            $transfer->delete();
            return redirect()->route('stock_transfers.index')->with('success', 'Stock transfer deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete stock transfer: '.$e->getMessage());
            return back()->with('error', 'Failed to delete stock transfer.');
        }
    }

    // Print stock transfer PDF
    public function print($id)
    {
        try {
            $transfer = StockTransfer::with(['fromLocation', 'toLocation', 'details.product', 'details.variation'])
                ->findOrFail($id);

            $pdf = new \TCPDF();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetCreator('Your App');
            $pdf->SetAuthor('Your Company');
            $pdf->SetTitle('Stock Transfer #' . $transfer->id);
            $pdf->SetMargins(10, 10, 10);
            $pdf->AddPage();
            $pdf->setCellPadding(1.5);

            // --- Logo ---
            $logoPath = public_path('assets/img/mj-logo.jpeg');
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 8, 10, 40);
            }

            $pdf->SetXY(130, 12);
            $transferInfo = '
            <table cellpadding="2" style="font-size:10px; line-height:14px;">
                <tr><td><b>Transfer #</b></td><td>' . $transfer->id . '</td></tr>
                <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($transfer->date)->format('d/m/Y') . '</td></tr>
                <tr><td><b>From</b></td><td>' . ($transfer->fromLocation->name ?? '-') . '</td></tr>
                <tr><td><b>To</b></td><td>' . ($transfer->toLocation->name ?? '-') . '</td></tr>
            </table>';
            $pdf->writeHTML($transferInfo, false, false, false, false, '');

            $pdf->Line(60, 52.25, 200, 52.25);

            $pdf->SetXY(10, 48);
            $pdf->SetFillColor(23, 54, 93);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(50, 8, 'Stock Transfer', 0, 1, 'C', 1);
            $pdf->SetTextColor(0, 0, 0);

            $pdf->Ln(5);
            $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
                <tr style="background-color:#f5f5f5; font-weight:bold;">
                    <th width="8%">S.No</th>
                    <th width="38%">Product</th>
                    <th width="38%">Variation</th>
                    <th width="16%">Quantity</th>
                </tr>';

            $count = 0;
            foreach ($transfer->details as $item) {
                $count++;
                $product = $item->product; // main product
                $variation = $item->variation; // may be null
                $unit = $product->measurementUnit->shortcode ?? '-';

                $html .= '
                <tr>
                    <td align="center">' . $count . '</td>
                    <td>' . ($product->name ?? '-') . '</td>
                    <td>' . ($variation->sku ?? '-') . '</td>
                    <td align="center">' .number_format($item->quantity, 2).' '.$unit.'</td>
                </tr>';
            }
            $html .= '</table>';

            $pdf->writeHTML($html, true, false, true, false, '');

            $pdf->Ln(20);
            $yPos = $pdf->GetY();
            $lineWidth = 40;
            $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
            $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);
            $pdf->SetXY(28, $yPos + 2);
            $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');
            $pdf->SetXY(130, $yPos + 2);
            $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

            return $pdf->Output('stock_transfer_' . $transfer->id . '.pdf', 'I');
        } catch (\Exception $e) {
            Log::error('Failed to print stock transfer: '.$e->getMessage());
            return back()->with('error', 'Failed to generate stock transfer PDF.');
        }
    }
}
