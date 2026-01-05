<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab  = $request->tab ?? 'IL';
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date ?? now()->toDateString();

        $productId = $request->item_id;

        $products    = Product::orderBy('name')->get();
        $itemLedger  = collect();
        $stockInHand = collect();
        $openingQty  = 0;

        /* ================= OPENING BALANCE ================= */
        if ($productId) {
            $openingQty =
                PurchaseInvoiceItem::where('item_id', $productId)
                    ->whereHas('invoice', fn ($q) =>
                        $q->whereDate('invoice_date', '<', $from)
                    )
                    ->sum('quantity')

                - PurchaseReturnItem::where('item_id', $productId)
                    ->whereHas('return', fn ($q) =>
                        $q->whereDate('return_date', '<', $from)
                    )
                    ->sum('quantity')

                - SaleInvoiceItem::where('product_id', $productId)
                    ->whereHas('saleInvoice', fn ($q) =>
                        $q->whereDate('date', '<', $from)
                    )
                    ->sum('quantity')

                + SaleReturnItem::where('product_id', $productId)
                    ->whereHas('saleReturn', fn ($q) =>
                        $q->whereDate('return_date', '<', $from)
                    )
                    ->sum('qty');
        }

        /* ================= ITEM LEDGER ================= */
        if ($tab === 'IL' && $productId) {

            $itemLedger = collect()

                ->merge(
                    PurchaseInvoiceItem::where('item_id', $productId)
                        ->whereHas('invoice', fn ($q) =>
                            $q->whereBetween('invoice_date', [$from, $to])
                        )
                        ->with('invoice')
                        ->get()
                        ->map(fn ($r) => [
                            'date'        => $r->invoice->invoice_date,
                            'type'        => 'Purchase',
                            'description' => 'Bill #'.($r->invoice->bill_no ?? $r->invoice->invoice_no),
                            'qty_in'      => $r->quantity,
                            'qty_out'     => 0,
                        ])
                )

                ->merge(
                    PurchaseReturnItem::where('item_id', $productId)
                        ->whereHas('return', fn ($q) =>
                            $q->whereBetween('return_date', [$from, $to])
                        )
                        ->with('return')
                        ->get()
                        ->map(fn ($r) => [
                            'date'        => $r->return->return_date,
                            'type'        => 'Purchase Return',
                            'description' => 'Return #'.$r->return->invoice_no,
                            'qty_in'      => 0,
                            'qty_out'     => $r->quantity,
                        ])
                )

                ->merge(
                    SaleInvoiceItem::where('product_id', $productId)
                        ->whereHas('saleInvoice', fn ($q) =>
                            $q->whereBetween('date', [$from, $to])
                        )
                        ->with('saleInvoice')
                        ->get()
                        ->map(fn ($r) => [
                            'date'        => $r->saleInvoice->date,
                            'type'        => 'Sale',
                            'description' => 'Invoice #'.$r->saleInvoice->invoice_no,
                            'qty_in'      => 0,
                            'qty_out'     => $r->quantity,
                        ])
                )

                ->merge(
                    SaleReturnItem::where('product_id', $productId)
                        ->whereHas('saleReturn', fn ($q) =>
                            $q->whereBetween('return_date', [$from, $to])
                        )
                        ->with('saleReturn')
                        ->get()
                        ->map(fn ($r) => [
                            'date'        => $r->saleReturn->return_date,
                            'type'        => 'Sale Return',
                            'description' => 'Return #'.$r->saleReturn->invoice_no,
                            'qty_in'      => $r->qty,
                            'qty_out'     => 0,
                        ])
                )

                ->sortBy('date')
                ->values();
        }

        /* ================= STOCK IN HAND ================= */
        if ($tab === 'SR') {

            $costing = $request->costing_method ?? 'avg';

            $items = $productId
                ? Product::where('id', $productId)->get()
                : $products;

            foreach ($items as $product) {

                $qty =
                    PurchaseInvoiceItem::where('item_id', $product->id)->sum('quantity')
                    - PurchaseReturnItem::where('item_id', $product->id)->sum('quantity')
                    - SaleInvoiceItem::where('product_id', $product->id)->sum('quantity')
                    + SaleReturnItem::where('product_id', $product->id)->sum('qty');

                if ($qty <= 0) continue;

                $q = PurchaseInvoiceItem::where('item_id', $product->id);

                $rate = match ($costing) {
                    'max'    => $q->max('price') ?? 0,
                    'min'    => $q->min('price') ?? 0,
                    'latest' => optional($q->latest('id')->first())->price ?? 0,
                    default  => ($r = $q->selectRaw('SUM(quantity*price) v, SUM(quantity) q')->first()) && $r->q > 0
                                    ? $r->v / $r->q
                                    : 0
                };

                $stockInHand->push([
                    'product'  => $product->name,
                    'quantity' => $qty,
                    'price'    => round($rate, 2),
                    'total'    => round($qty * $rate, 2),
                ]);
            }
        }

        return view('reports.inventory_reports', compact(
            'products',
            'tab',
            'itemLedger',
            'stockInHand',
            'from',
            'to',
            'openingQty'
        ));
    }
}
