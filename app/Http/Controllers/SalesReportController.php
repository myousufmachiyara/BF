<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseBiltyDetail;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function saleReports(Request $request)
    {
        $tab = $request->get('tab', 'SR');

        $from = $request->get('from_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('to_date', Carbon::now()->toDateString());

        $customerId = $request->get('customer_id');

        $sales        = collect();
        $returns      = collect();
        $customerWise = collect();

        /* ================= SALES REGISTER ================= */
        if ($tab === 'SR') {
            $sales = SaleInvoice::with(['account', 'items'])
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    $total = $sale->items->sum(function ($item) {
                        return ($item->sale_price ?? $item->price) * $item->quantity;
                    });

                    return (object)[
                        'id'       => $sale->id, // Add this here too!
                        'date'     => $sale->date,
                        'invoice'  => $sale->invoice_no ?? $sale->id,
                        'customer' => $sale->account->name ?? '',
                        'revenue'  => $total - ($sale->discount ?? 0), // Added this
                        'total'    => $total, // Kept for safety
                        'cost'     => 0,      // Placeholder to prevent Blade errors
                        'profit'   => 0,      // Placeholder
                        'margin'   => 0       // Placeholder
                    ];
                });
        }

        /* ================= SALES RETURN ================= */
        if ($tab === 'SRET') {
            $returns = SaleReturn::with(['customer', 'items'])
                ->whereBetween('return_date', [$from, $to])
                ->get()
                ->map(function ($ret) {

                    $total = $ret->items->sum(function ($item) {
                        return $item->qty * $item->price;
                    });

                    return (object)[
                        'date'     => $ret->return_date,
                        'invoice'  => $ret->invoice_no ?? $ret->id,
                        'customer' => $ret->account->name ?? '',
                        'total'    => $total,
                    ];
                });
        }

        /* ================= CUSTOMER WISE ================= */
        if ($tab === 'CW') {

            $query = SaleInvoice::with(['account', 'items'])
                ->whereBetween('date', [$from, $to]);

            if ($customerId) {
                $query->where('account_id', $customerId);
            }

            $customerWise = $query->get()
                ->groupBy('account_id')
                ->map(function ($sales) {

                    $customerName = $sales->first()->account->name ?? 'Unknown Customer';

                    $items = collect();

                    foreach ($sales as $sale) {
                        foreach ($sale->items as $item) {
                            $qty   = $item->quantity ?? $item->qty ?? 0;
                            $price = $item->sale_price ?? $item->price ?? 0;

                            $items->push((object)[
                                'invoice_date' => $sale->date,
                                'invoice_no'   => $sale->invoice_no ?? $sale->id,
                                'item_name'    => $item->product->name ?? 'N/A',
                                'quantity'     => $qty,
                                'rate'         => $price,
                                'total'        => $qty * $price,
                            ]);
                        }
                    }

                    return (object)[
                        'customer_name' => $customerName,
                        'items'         => $items,
                        'total_qty'     => $items->sum('quantity'),
                        'total_amount'  => $items->sum('total'),
                    ];
                })
                ->values();
        }

        /* ================= PROFIT REPORT (Synced + Customization Costing) ================= */
        if ($tab === 'PR') {
            $sales = SaleInvoice::with(['account', 'items.customizations'])
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    $invoiceRevenue = 0;
                    $invoiceCost = 0;

                    // Reusable helper for Landed Cost (Purchase + Bilty)
                    $getLandedCost = function ($productId) {
                        return \Cache::remember("landed_cost_prod_{$productId}", 86400, function () use ($productId) {
                            // 1. Purchase Rate (Average)
                            $pStats = PurchaseInvoiceItem::where('item_id', $productId)
                            ->whereHas('invoice', fn ($q) => $q->whereNull('deleted_at'))
                            ->selectRaw('SUM(quantity * price) as v, SUM(quantity) as q')
                            ->first();
                            $purchaseRate = ($pStats && $pStats->q > 0) ? ($pStats->v / $pStats->q) : 0;

                            // 2. Bilty Cost logic
                            $biltyTotal = PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $productId)
                                ->join('purchase_bilty', function ($join) {
                                    $join->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')
                                        ->whereNull('purchase_bilty.deleted_at');
                                })
                                ->sum(\DB::raw('(purchase_bilty.bilty_amount / (SELECT SUM(quantity) FROM purchase_bilty_details d WHERE d.bilty_id = purchase_bilty.id)) * purchase_bilty_details.quantity'));

                            $biltyQty = PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $productId)
                                ->join('purchase_bilty', function ($join) {
                                    $join->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')
                                        ->whereNull('purchase_bilty.deleted_at');
                                })
                                ->sum('purchase_bilty_details.quantity');

                            $biltyRate = ($biltyQty > 0) ? ($biltyTotal / $biltyQty) : 0;

                            return $purchaseRate + $biltyRate;
                        });
                    };

                    foreach ($sale->items as $item) {
                        $invoiceRevenue += ($item->sale_price ?? 0) * $item->quantity;

                        // Main Item Cost
                        $unitCost = $getLandedCost($item->product_id);

                        // Add Customization Costs to the unit cost
                        if ($item->customizations) {
                            foreach ($item->customizations as $custom) {
                                $unitCost += $getLandedCost($custom->item_id);
                            }
                        }

                        $invoiceCost += ($unitCost * $item->quantity);
                    }

                    $netRevenue = $invoiceRevenue - ($sale->discount ?? 0);
                    return (object)[
                        'id'       => $sale->id, // <--- ADD THIS LINE TO FIX THE ERROR
                        'date'     => $sale->date,
                        'invoice'  => $sale->invoice_no,
                        'customer' => $sale->account->name ?? 'N/A',
                        'revenue'  => $netRevenue,
                        'cost'     => $invoiceCost,
                        'profit'   => $netRevenue - $invoiceCost,
                        'margin'   => $netRevenue > 0 ? (($netRevenue - $invoiceCost) / $netRevenue) * 100 : 0
                    ];
                });
        }

        $customers = ChartOfAccounts::where('account_type', 'customer')->get();

        return view('reports.sales_reports', compact(
            'tab',
            'from',
            'to',
            'sales',
            'returns',
            'customerWise',
            'customers',
            'customerId'
        ));
    }


    public function printProfitReport($id)
    {
        // 1. Fetch Invoice with Relations (Same as your report logic)
        $invoice = SaleInvoice::with(['account', 'items.product', 'items.customizations'])->findOrFail($id);

        // 2. The Reusable Cost Logic (Same as your report logic)
        $getLandedCost = function ($productId) {
            return \Cache::remember("landed_cost_prod_{$productId}", 86400, function () use ($productId) {
                $pStats = PurchaseInvoiceItem::where('item_id', $productId)
                    ->whereHas('invoice', fn ($q) => $q->whereNull('deleted_at'))
                    ->selectRaw('SUM(quantity * price) as v, SUM(quantity) as q')
                    ->first();
                $purchaseRate = ($pStats && $pStats->q > 0) ? ($pStats->v / $pStats->q) : 0;

                $biltyTotal = PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $productId)
                    ->join('purchase_bilty', fn($j) => $j->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')->whereNull('purchase_bilty.deleted_at'))
                    ->sum(\DB::raw('(purchase_bilty.bilty_amount / (SELECT SUM(quantity) FROM purchase_bilty_details d WHERE d.bilty_id = purchase_bilty.id)) * purchase_bilty_details.quantity'));

                $biltyQty = PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $productId)
                    ->join('purchase_bilty', fn($j) => $j->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')->whereNull('purchase_bilty.deleted_at'))
                    ->sum('purchase_bilty_details.quantity');

                return $purchaseRate + (($biltyQty > 0) ? ($biltyTotal / $biltyQty) : 0);
            });
        };

        // 3. Setup TCPDF
        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetTitle('Profit Analysis - ' . $invoice->invoice_no);
        $pdf->AddPage();

        // --- Header ---
        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) { $pdf->Image($logoPath, 12, 8, 35); }
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Profit Analysis Report', 0, 1, 'R');

        // --- Customer Info ---
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 9);
        $infoHtml = '
        <table border="1" cellpadding="3">
            <tr>
                <td width="15%" bgcolor="#f5f5f5"><b>Customer:</b></td><td width="50%">' . ($invoice->account->name ?? '-') . '</td>
                <td width="15%" bgcolor="#f5f5f5"><b>Date:</b></td><td width="20%">' . date('d-m-Y', strtotime($invoice->date)) . '</td>
            </tr>
            <tr>
                <td bgcolor="#f5f5f5"><b>Invoice #:</b></td><td>' . $invoice->invoice_no . '</td>
                <td bgcolor="#f5f5f5"><b>Currency:</b></td><td>PKR</td>
            </tr>
        </table>';
        $pdf->writeHTML($infoHtml);

        // --- Items Table ---
        $html = '
        <table border="1" cellpadding="4" style="font-size:9px; text-align:center;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="5%">#</th>
                <th width="35%">Product & Customizations</th>
                <th width="10%">Qty</th>
                <th width="15%">Sale Val</th>
                <th width="15%">Landed Cost</th>
                <th width="20%">Line Profit</th>
            </tr>';

        $totalRevenue = 0;
        $totalLandedCost = 0;

        foreach ($invoice->items as $idx => $item) {
            // Calculate Cost exactly like your map function
            $unitCost = $getLandedCost($item->product_id);
            $customNames = [];
            if ($item->customizations) {
                foreach ($item->customizations as $custom) {
                    $unitCost += $getLandedCost($custom->item_id);
                    $customNames[] = $custom->product->name ?? 'Custom';
                }
            }

            $lineRev = $item->sale_price * $item->quantity;
            $lineCost = $unitCost * $item->quantity;
            $lineProfit = $lineRev - $lineCost;

            $totalRevenue += $lineRev;
            $totalLandedCost += $lineCost;

            $productDisplay = '<b>' . ($item->product->name ?? '-') . '</b>';
            if (!empty($customNames)) {
                $productDisplay .= '<br><small style="color:#555;">Incl: ' . implode(', ', $customNames) . '</small>';
            }

            $html .= '<tr>
                <td>' . ($idx + 1) . '</td>
                <td align="left">' . $productDisplay . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . number_format($lineRev, 2) . '</td>
                <td>' . number_format($lineCost, 2) . '</td>
                <td style="font-weight:bold;">' . number_format($lineProfit, 2) . '</td>
            </tr>';
        }

        $netRev = $totalRevenue - ($invoice->discount ?? 0);
        $netProfit = $netRev - $totalLandedCost;
        $margin = $netRev > 0 ? ($netProfit / $netRev) * 100 : 0;

        $html .= '
            <tr style="background-color:#f9f9f9;">
                <td colspan="3" align="right"><b>Gross Totals</b></td>
                <td><b>' . number_format($totalRevenue, 2) . '</b></td>
                <td><b>' . number_format($totalLandedCost, 2) . '</b></td>
                <td><b>' . number_format($totalRevenue - $totalLandedCost, 2) . '</b></td>
            </tr>
            <tr>
                <td colspan="5" align="right">Less: Discount</td>
                <td>(' . number_format($invoice->discount ?? 0, 2) . ')</td>
            </tr>
            <tr style="background-color:#e8f5e9;">
                <td colspan="5" align="right"><b>NET PROFIT</b></td>
                <td style="color:green;"><b>' . number_format($netProfit, 2) . '</b></td>
            </tr>
            <tr style="background-color:#f5f5f5;">
                <td colspan="5" align="right"><b>Margin %</b></td>
                <td><b>' . number_format($margin, 2) . '%</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html);
        return $pdf->Output('Profit_Analysis_' . $invoice->invoice_no . '.pdf', 'I');
    }
}
