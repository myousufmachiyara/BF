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

        /* ================= PROFIT REPORT (Optimized with Caching) ================= */
        if ($tab === 'PR') {
            $sales = SaleInvoice::with(['account', 'items'])
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    $invoiceRevenue = 0;
                    $invoiceCost = 0;

                    foreach ($sale->items as $item) {
                        $pid = $item->product_id;
                        $invoiceRevenue += ($item->sale_price ?? 0) * $item->quantity;

                        // Cache the Landed Cost for each product to avoid heavy SQL in loops
                        $avgLandedRate = \Cache::remember("avg_cost_prod_{$pid}", 86400, function () use ($pid) {
                            // 1. Purchase Rate logic
                            $pStats = PurchaseInvoiceItem::where('item_id', $pid)
                                ->whereHas('invoice', fn ($q) => $q->whereNull('deleted_at'))
                                ->selectRaw('SUM(quantity * price) as v, SUM(quantity) as q')
                                ->first();
                            $purchaseRate = ($pStats && $pStats->q > 0) ? ($pStats->v / $pStats->q) : 0;

                            // 2. Bilty Rate logic
                            $bTotal = PurchaseBiltyDetail::where('item_id', $pid)
                                ->join('purchase_bilty', fn($j) => $j->on('purchase_bilty.id','purchase_bilty_details.bilty_id')->whereNull('deleted_at'))
                                ->sum(\DB::raw('(purchase_bilty.bilty_amount / (SELECT SUM(quantity) FROM purchase_bilty_details d WHERE d.bilty_id = purchase_bilty.id)) * purchase_bilty_details.quantity'));

                            $bQty = PurchaseBiltyDetail::where('item_id', $pid)
                                ->join('purchase_bilty', fn($j) => $j->on('purchase_bilty.id','purchase_bilty_details.bilty_id')->whereNull('deleted_at'))
                                ->sum('quantity');

                            $biltyRate = ($bQty > 0) ? ($bTotal / $bQty) : 0;

                            return $purchaseRate + $biltyRate;
                        });

                        $invoiceCost += ($avgLandedRate * $item->quantity);
                    }

                    $netRevenue = $invoiceRevenue - ($sale->discount ?? 0);
                    return (object)[
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
}
