<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;
use App\Models\PurchaseBilty;
use App\Models\PurchaseBiltyDetail;

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
/* ================= STOCK IN HAND (Corrected) ================= */
if ($tab === 'SR') {
    $costing = $request->costing_method ?? 'avg';
    $items = $productId ? Product::where('id', $productId)->get() : $products;

    foreach ($items as $product) {
        // 1. Calculate Quantities
        $purchaseQty = PurchaseInvoiceItem::where('item_id', $product->id)
            ->whereHas('invoice', fn ($q) => $q->whereNull('deleted_at'))->sum('quantity');

        $purchaseReturnQty = PurchaseReturnItem::where('item_id', $product->id)
            ->whereHas('return', fn ($q) => $q->whereNull('deleted_at'))->sum('quantity');

        $saleQty = SaleInvoiceItem::where('product_id', $product->id)
            ->whereHas('saleInvoice', fn ($q) => $q->whereNull('deleted_at'))->sum('quantity');

        $saleReturnQty = SaleReturnItem::where('product_id', $product->id)
            ->whereHas('saleReturn', fn ($q) => $q->whereNull('deleted_at'))->sum('qty');

        // Subtract items consumed via customizations
        $customConsumption = \App\Models\SaleItemCustomization::where('item_id', $product->id)
            ->whereHas('saleInvoice', fn ($q) => $q->whereNull('deleted_at'))
            ->join('sale_invoice_items', 'sale_item_customization.sale_invoice_items_id', '=', 'sale_invoice_items.id')
            ->sum('sale_invoice_items.quantity');

        $qty = $purchaseQty - $purchaseReturnQty - $saleQty + $saleReturnQty - $customConsumption;

        // Skip products with no stock
        if ($qty <= 0) continue;

        /* 2. CALCULATE PURCHASE RATE */
        $purchaseQuery = PurchaseInvoiceItem::where('item_id', $product->id)
            ->whereHas('invoice', fn ($q) => $q->whereNull('deleted_at'));

        $purchaseRate = match ($costing) {
            'max'    => $purchaseQuery->max('price') ?? 0,
            'min'    => $purchaseQuery->min('price') ?? 0,
            'latest' => optional($purchaseQuery->latest('id')->first())->price ?? 0,
            default  => ($r = $purchaseQuery->selectRaw('SUM(quantity * price) v, SUM(quantity) q')->first()) 
                        && $r->q > 0 ? $r->v / $r->q : 0
        };

        /* 3. CALCULATE BILTY RATE */
        $biltyTotal = \App\Models\PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $product->id)
            ->join('purchase_bilty', function ($join) {
                $join->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')
                    ->whereNull('purchase_bilty.deleted_at');
            })
            ->sum(\DB::raw('(purchase_bilty.bilty_amount / (SELECT SUM(quantity) FROM purchase_bilty_details d WHERE d.bilty_id = purchase_bilty.id)) * purchase_bilty_details.quantity'));

        $biltyQtyTotal = \App\Models\PurchaseBiltyDetail::where('purchase_bilty_details.item_id', $product->id)
            ->join('purchase_bilty', function ($join) {
                $join->on('purchase_bilty.id', '=', 'purchase_bilty_details.bilty_id')
                    ->whereNull('purchase_bilty.deleted_at');
            })
            ->sum('purchase_bilty_details.quantity');

        $biltyRate = $biltyQtyTotal > 0 ? $biltyTotal / $biltyQtyTotal : 0;

        /* 4. FINAL LANDED COST */
        $finalRate = $purchaseRate + $biltyRate;

        $stockInHand->push([
            'product'  => $product->name,
            'quantity' => $qty,
            'price'    => round($finalRate, 2),
            'total'    => round($qty * $finalRate, 2),
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
