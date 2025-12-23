<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Production;
use App\Models\ProductionReceiving;
use Carbon\Carbon;

class ProductionReportController extends Controller
{
    public function productionReports(Request $request)
    {
        $tab = $request->get('tab', 'RMI'); // default tab: Raw Issued

        // Default date range: 1st day of current month → today
        $from = $request->get('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));

        // Ensure all variables are defined for Blade
        $rawIssued   = collect();
        $produced    = collect();
        $costings    = collect();

        // --- RAW MATERIAL ISSUED (Production Order) ---
        if ($tab === 'RMI') {
            $rawIssued = Production::with('details.product')
                ->whereBetween('order_date', [$from, $to])
                ->get()
                ->flatMap(function ($prod) {
                    return $prod->details->map(function ($detail) use ($prod) {
                        return (object)[
                            'date'       => $prod->order_date,
                            'production' => $prod->id,
                            'item_name'  => $detail->product->name ?? '',
                            'qty'        => $detail->qty,
                            'rate'       => $detail->rate,
                            'total'      => $detail->qty * $detail->rate,
                        ];
                    });
                });
        }

        // --- PRODUCTION RECEIVING (FG Received) ---
        if ($tab === 'PR') {
            $produced = ProductionReceiving::with('production', 'details.product')
                ->whereBetween('rec_date', [$from, $to])
                ->get()
                ->flatMap(function ($rec) {
                    return $rec->details->map(function ($detail) use ($rec) {
                        return (object)[
                            'date'       => $rec->rec_date,
                            'production' => $rec->production->id ?? '',
                            'item_name'  => $detail->product->name ?? '',
                            'qty'        => $detail->received_qty,
                            'm_cost'     => $detail->manufacturing_cost,
                            'total'      => $detail->received_qty * $detail->manufacturing_cost,
                        ];
                    });
                });
        }

        // --- PRODUCT COSTING (Each Item Average Cost) ---
        if ($tab === 'CR') {
            // You can replace this with your actual costing logic once you have ProjectCosting table
            // For now, placeholder: calculate average cost per item from production received
            $costings = ProductionReceiving::with('details.product')
                ->whereBetween('rec_date', [$from, $to])
                ->get()
                ->groupBy('details.*.product_id')
                ->map(function ($group, $productId) {
                    $totalQty  = 0;
                    $totalCost = 0;
                    foreach ($group as $rec) {
                        foreach ($rec->details as $detail) {
                            $totalQty  += $detail->received_qty;
                            $totalCost += $detail->received_qty * $detail->manufacturing_cost;
                        }
                    }
                    return (object)[
                        'product_name' => $group->first()->details->first()->product->name ?? 'N/A',
                        'total_qty'    => $totalQty,
                        'avg_cost'     => $totalQty ? $totalCost / $totalQty : 0,
                        'total_cost'   => $totalCost,
                    ];
                });
        }

        return view('reports.production_reports', compact(
            'tab', 'from', 'to', 'rawIssued', 'produced', 'costings'
        ));
    }
}
