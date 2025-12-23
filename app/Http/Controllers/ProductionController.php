<?php

namespace App\Http\Controllers;

use App\Models\ProductionDetail;
use App\Models\ProductCategory;
use App\Models\ChartOfAccounts;
use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\Production;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\Voucher;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    public function index()
    {
        $productions = Production::with(['vendor', 'category', 'details'])->orderBy('id', 'desc')->get();

        // Calculate total amount for each production
        foreach ($productions as $production) {
            $production->total_amount = $production->details->sum(function($detail) {
                return $detail->rate * $detail->qty;
            });
        }

        return view('production.index', compact('productions'));
    }


    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->where('item_type', 'raw')->get();
        $units = MeasurementUnit::all();

        $allProducts = collect($products)->map(function ($product) {
            return (object)[
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->measurement_unit,
            ];
        });
        
        return view('production.create', compact('vendors', 'categories', 'allProducts', 'units'));
    }

    public function store(Request $request)
    {
        $voucher = null;

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'category_id' => 'nullable|exists:product_categories,id',
            'order_date' => 'required|date',
            'production_type' => 'required|string',
            'att.*' => 'nullable|file|max:2048',
            'item_details' => 'required|array|min:1',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id',
            'item_details.*.invoice_id' => 'nullable|exists:purchase_invoices,id',
            'item_details.*.qty' => 'required|numeric|min:0.01',
            'item_details.*unit' => 'required|exists:measurement_units,id',
            'item_details.*.item_rate' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            Log::info('Production Store: Start storing');

            // Save attachments
            $attachments = [];
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $attachments[] = $file->store('attachments/productions', 'public');
                }
            }

            // Calculate total amount
            $totalAmount = collect($request->item_details)->sum(function ($item) {
                return $item['qty'] * $item['item_rate'];
            });

            // Auto-generate payment voucher production_type is sale_leather
            if ($request->production_type === 'sale_leather') {
                $voucher = Voucher::create([
                    'date' => $request->order_date,
                    'voucher_type' => "payment",
                    'ac_dr_sid' => $request->vendor_id, // Vendor becomes receivable (Dr)
                    'ac_cr_sid' => 5, // Raw Material Inventory (Cr)
                    'amount' => $totalAmount,
                    'remarks' => 'Sold Leather of Amount ' . number_format($totalAmount, 2) . ' for Production',
                ]);

                Log::info('Payment Voucher auto-generated');
            }

            // Create production
            $production = Production::create([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id ?? null,
                'voucher_id' => $voucher->id ?? null,
                'order_date' => $request->order_date,
                'production_type' => $request->production_type,
                'total_amount' => $totalAmount,
                'remarks' => $request->remarks,
                'attachments' => $attachments,
                'created_by' => auth()->id(),
            ]);

            // Save production item details
            if (is_array($request->item_details)) {
                foreach ($request->item_details as $item) {
                    $production->details()->create([
                        'production_id' => $production->id,
                        'invoice_id' => $item['invoice_id'] ?? null,
                        'variation_id' => $item['variation_id'] ?? null,
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'unit' => $item['item_unit'],
                        'rate' => $item['item_rate'],
                        'total_cost' => $item['item_rate'] * $item['qty'],
                        'remarks' => $item['remarks'] ?? null,
                    ]);
                }
            } else {
                throw new \Exception('Items data is not valid.');
            }

            DB::commit();
            Log::info('Production Store: Success for production_id: ' . $production->id);

            return redirect()->route('production.index')->with('success', 'Production created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Production Store Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function edit($id)
    {
        $production = Production::with(['details.variation', 'details.product'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products = Product::with('variations') // <-- load variations
                            ->select('id', 'name', 'barcode', 'measurement_unit')
                            ->where('item_type', 'raw')
                            ->get();
        $units = MeasurementUnit::all();

        $allProducts = $products->map(function ($product) {
            return (object)[
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->measurement_unit,
                'variations' => $product->variations->map(fn($v) => (object)[
                    'id' => $v->id,
                    'sku' => $v->sku,
                ]),
            ];
        });


        return view('production.edit', compact('production', 'vendors', 'categories', 'allProducts', 'units'));
    }


    public function update(Request $request, $id)
    {
        $voucher = null;

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'category_id' => 'nullable|exists:product_categories,id',
            'order_date' => 'required|date',
            'production_type' => 'required|string|in:cmt,sale_leather',
            'attachments.*' => 'nullable|file|max:2048',
            'item_details' => 'required|array|min:1',
            'item_details.*.item_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id',
            'item_details.*.invoice' => 'nullable|exists:purchase_invoices,id',
            'item_details.*.qty' => 'required|numeric|min:0.01',
            'item_details.*.item_unit' => 'required|exists:measurement_units,id',
            'item_details.*.rate' => 'required|numeric|min:0',
            'challan_no' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $production = Production::findOrFail($id);

            // --- Attachments ---
            $attachments = $production->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('attachments/productions', 'public');
                }
            }

            // --- Total ---
            $totalAmount = collect($request->item_details)->sum(fn($item) => $item['qty'] * $item['rate']);

            // --- Delete old voucher ---
            if ($production->voucher_id) {
                Voucher::where('id', $production->voucher_id)->delete();
            }

            // --- Create new voucher if needed ---
            if ($request->production_type === 'sale_leather') {
                $voucher = Voucher::create([
                    'date' => $request->order_date,
                    'voucher_type' => "payment",
                    'ac_dr_sid' => $request->vendor_id, // Vendor becomes receivable (Dr)
                    'ac_cr_sid' => 5, // Raw Material Inventory (Cr)
                    'amount' => $totalAmount,
                    'remarks' => 'Sold Leather of Amount ' . number_format($totalAmount, 2) . ' for Production',
                ]);
            }

            // --- Update production ---
            $production->update([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id ?? null,
                'voucher_id' => $voucher->id ?? null,
                'order_date' => $request->order_date,
                'production_type' => $request->production_type,
                'remarks' => $request->remarks,
                'attachments' => $attachments,
            ]);

            // --- Reset details ---
            $production->details()->delete();

            foreach ($request->item_details as $item) {
                $production->details()->create([
                    'production_id' => $id,
                    'product_id' => $item['item_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'invoice_id' => $item['invoice'] ?? null,
                    'qty' => $item['qty'],
                    'unit' => $item['item_unit'],
                    'rate' => $item['rate'],
                ]);
            }

            DB::commit();

            return redirect()->route('production.index')->with('success', 'Production updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Production Update Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'production_id' => $id,
                'user_id' => auth()->id(),
            ]);

            return back()->withInput()->with('error', 'Failed to update production. Check logs.');
        }
    }


    public function getProductProductions(Request $request, $productId)
    {
        try {
            $variationId = $request->get('variation_id'); // optional

            Log::info("Fetching productions", [
                'product_id'  => $productId,
                'variation_id' => $variationId,
            ]);

            $query = ProductionDetail::with('production')
                ->where('product_id', $productId);

            if ($variationId) {
                // Case 1: Product has variation selected → filter by variation
                $query->where('variation_id', $variationId);
            } else {
                // Case 2: Product has no variation → only include rows where variation_id IS NULL
                $query->whereNull('variation_id');
            }

            $productions = $query->get()->map(function ($detail) {
                return [
                    'id'   => $detail->production_id,
                    'rate' => $detail->rate,
                ];
            });

            Log::info("Productions fetched successfully", [
                'count' => $productions->count(),
                'productions' => $productions,
            ]);

            return response()->json($productions);

        } catch (\Throwable $e) {
            Log::error("Error fetching productions", [
                'product_id' => $productId,
                'variation_id' => $request->get('variation_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Something went wrong while fetching productions.'
            ], 500);
        }
    }


    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }   
    
    public function summary($id)
    {
        $production = Production::with([
            'details.product.measurementUnit',
            'receivings.details.product.measurementUnit'
        ])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your App Name');
        $pdf->SetTitle('Production Costing');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(5);

        // 🔹 Header
        $html = '
        <table class="info-table">
            <tr>
                <td><strong>Production ID:</strong> ' . $production->id . '</td>
                <td style="text-align:right"><strong>Order Date:</strong> ' . $production->order_date . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // 🔹 Raw Details Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Raw Details', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 7, 'Item', 1);
        $pdf->Cell(25, 7, 'Qty', 1);
        $pdf->Cell(25, 7, 'Rate', 1);
        $pdf->Cell(30, 7, 'Total Cost', 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 9);

        $totalRawGiven = 0;
        $totalRawCost = 0;
        foreach ($production->details as $raw) {
            $itemName = optional($raw->product)->name ?? 'N/A';
            $rawQty = $raw->qty;
            $rawUnit = optional($raw->product->measurementUnit)->shortcode ?? '';
            $rate = $raw->rate;
            $totalCost = $rawQty * $rate;

            $totalRawGiven += $rawQty;
            $totalRawCost += $totalCost;

            $pdf->Cell(40, 7, $itemName, 1);
            $pdf->Cell(25, 7, number_format($rawQty, 2) . ' ' . $rawUnit, 1);
            $pdf->Cell(25, 7, number_format($rate, 2), 1);
            $pdf->Cell(30, 7, number_format($totalCost, 2), 1);
            $pdf->Ln();
        }

        // 🔹 Finish Goods Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Finish Good Details', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 7, 'Product', 1);
        $pdf->Cell(25, 7, 'Qty', 1);
        $pdf->Cell(25, 7, 'Cost/pc', 1);
        $pdf->Cell(30, 7, 'Total Cost', 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 9);

        // Calculate avg raw usage & raw cost per unit
        $totalProductsReceived = 0;
        $productSummary = [];
        foreach ($production->receivings as $receiving) {
            foreach ($receiving->details as $detail) {
                $productId = $detail->product_id;
                $productName = optional($detail->product)->name ?? '-';
                $unit = optional($detail->product->measurementUnit)->shortcode ?? '-';
                $receivedQty = $detail->received_qty;
                $mfgCost = optional($detail->product)->manufacturing_cost ?? 0;

                if (!isset($productSummary[$productId])) {
                    $productSummary[$productId] = [
                        'name' => $productName,
                        'unit' => $unit,
                        'qty' => 0,
                        'manufacturing_cost' => $mfgCost,
                    ];
                }

                $productSummary[$productId]['qty'] += $receivedQty;
                $totalProductsReceived += $receivedQty;
            }
        }

        $rawCostPerUnit = $totalProductsReceived > 0 ? $totalRawCost / $totalProductsReceived : 0;

        $grandTotalCost = 0;
        foreach ($productSummary as $product) {
            $qty = $product['qty'];
            $mfgCost = $product['manufacturing_cost'];

            // Cost per pc = avg raw + mfg cost
            $costPerPc = $rawCostPerUnit + $mfgCost;
            $totalCost = $qty * $costPerPc;
            $grandTotalCost += $totalCost;

            $pdf->Cell(50, 7, $product['name'], 1);
            $pdf->Cell(25, 7, number_format($qty, 2) . ' ' . $product['unit'], 1);
            $pdf->Cell(25, 7, number_format($costPerPc, 2), 1);
            $pdf->Cell(30, 7, number_format($totalCost, 2), 1);
            $pdf->Ln();
        }

        // 🔹 Summary Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Summary', 0, 1);
        $pdf->Ln(2);

        $consumption = $totalProductsReceived > 0
            ? ($totalRawGiven / $totalProductsReceived)
            : 0;

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(60, 7, 'Total Raw Given', 1);
        $pdf->Cell(60, 7, number_format($totalRawGiven, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Total Raw Cost', 1);
        $pdf->Cell(60, 7, number_format($totalRawCost, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Total Products Received', 1);
        $pdf->Cell(60, 7, number_format($totalProductsReceived, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Consumption (%)', 1);
        $pdf->Cell(60, 7, number_format($consumption, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Grand Total Cost', 1);
        $pdf->Cell(60, 7, number_format($grandTotalCost, 2), 1);
        $pdf->Ln();

        $pdf->Output('production_' . $production->id . '.pdf', 'I');
    }

    public function print($id)
    {
        $production = Production::with(['vendor', 'details.product', 'details.invoice', 'details.measurementUnit'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Production #' . $production->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // --- Production Info Box ---
        $pdf->SetXY(130, 12);
        $infoHtml = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Production #</b></td><td>' . $production->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($production->order_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($production->vendor->name ?? '-') . '</td></tr>
            <tr><td><b>Type</b></td><td>' . ucfirst(str_replace('_', ' ', $production->production_type)) . '</td></tr>
        </table>';
        $pdf->writeHTML($infoHtml, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(50, 8, 'Production Order', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="28%">Product</th>
                <th width="10%">Invoice #</th>
                <th width="20%">Qty</th>
                <th width="15%">Rate</th>
                <th width="20%">Total</th>
            </tr>';

        $count = 0;
        $totalAmount = 0;

        foreach ($production->details as $detail) {
            $count++;
            $amount = $detail->total_cost ?? ($detail->qty * $detail->rate);
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($detail->product->name ?? '-') . '</td>
                <td align="center">' . ($detail->invoice->id ?? '-') . '</td>
                <td align="center">' . number_format($detail->qty, 2) . ' ' .($detail->measurementUnit->shortcode ?? '-') .'</td>
                <td align="right">' . number_format($detail->rate, 2) . '</td>
                <td align="right">' . number_format($amount, 2) . '</td>
            </tr>';
        }

        // --- Totals ---
        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="5" align="right"><b>Total Amount</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($production->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:9px;">' . nl2br($production->remarks) . '</span>';
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

        return $pdf->Output('production_' . $production->id . '.pdf', 'I');
    }

    public function printGatepass($id)
    {
        $production = Production::with(['vendor', 'details.variation', 'details.product', 'details.measurementUnit'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetTitle('Production Gatepass #' . $production->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        // --- Logo ---
        $logoPath = public_path('assets/img/mj-logo.jpeg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 8, 10, 40);
        }

        // --- Production Info ---
        $pdf->SetXY(130, 12);
        $infoHtml = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Production Order #</b></td><td>' . $production->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($production->date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($production->vendor->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($infoHtml, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(50, 8, 'Gate Pass', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="8%">S.No</th>
                <th width="25%">Item</th>
                <th width="25%">Variation</th>
                <th width="17%">Qty</th>
                <th width="25%">Remarks</th>
            </tr>';

        $count = 0;
        foreach ($production->details as $detail) {
            $count++;
            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($detail->product->name ?? '-') . '</td>
                <td>' . ($detail->variation->sku ?? '-') . '</td>
                <td>' . number_format($detail->qty, 2).' '.($detail->measurementUnit->shortcode). '</td>
                <td>' . ($detail->remarks ?? '-') . '</td>
            </tr>';
        }

        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Footer / Approval ---
        $pdf->Ln(20);
        $y = $pdf->GetY();
        $lineWidth = 60;

        $pdf->Line(20, $y, 20 + $lineWidth, $y);
        $pdf->Line(120, $y, 120 + $lineWidth, $y);

        $pdf->SetXY(20, $y + 2);
        $pdf->Cell($lineWidth, 6, 'Issued By', 0, 0, 'C');
        $pdf->SetXY(120, $y + 2);
        $pdf->Cell($lineWidth, 6, 'Approved By', 0, 0, 'C');

        return $pdf->Output('production_gatepass_' . $production->id . '.pdf', 'I');
    }

}
