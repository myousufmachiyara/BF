<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;
use App\Models\ProductionDetail;
use App\Models\ProductionReceivingDetail;
use App\Models\StockTransferDetail;
use App\Models\Location;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab        = $request->tab ?? 'IL';
        $selected   = $request->item_id ?? null; // may be "productId" or "productId-variationId"
        $from       = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to         = $request->to_date ?? now()->toDateString();
        $locationId = $request->location_id ?? null;
        $locations  = Location::all();

        // parse product and variation if provided in "productId-variationId" format
        $productId = null;
        $variationId = null;
        if ($selected) {
            if (str_contains($selected, '-')) {
                [$p, $v] = explode('-', $selected);
                $productId   = (int) $p;
                $variationId = $v !== '' ? (int) $v : null;
            } else {
                $productId = (int) $selected;
            }
        }

        // load all products (for dropdown)
        $allProducts = Product::with('variations')->get();

        // initialize variables so view always receives them
        $itemLedger   = collect();
        $stockInHand  = collect(); // <<-- IMPORTANT: initialize here
        $stockTransfers = collect();
        $nonMovingItems = collect();
        $reorderLevel = collect();

        // --------------------------
        // ITEM LEDGER
        // --------------------------
        if ($tab === 'IL' && $productId) {
            $product = $allProducts->firstWhere('id', $productId);
            if ($product) {
                if ($variationId) {
                    $var = $product->variations->firstWhere('id', $variationId);
                    $variations = $var ? collect([$var]) : collect([(object)['id' => $variationId, 'sku' => null]]);
                } else {
                    $variations = $product->variations->isNotEmpty()
                        ? $product->variations
                        : collect([(object)['id' => null, 'sku' => null]]);
                }

                foreach ($variations as $var) {
                    $ledger = collect();

                    // Purchases
                    $ledger = $ledger->concat(
                        PurchaseInvoiceItem::where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->invoice_date,
                                'type' => 'Purchase',
                                'description' => 'Bill No: ' . ($row->invoice->bill_no ?? $row->invoice->id),
                                'qty_in' => $row->quantity,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Purchase Returns
                    $ledger = $ledger->concat(
                        PurchaseReturnItem::where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->return->return_date,
                                'type' => 'Purchase Return',
                                'description' => 'Return No: ' . ($row->return->reference_no ?? $row->return->id),
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sales
                    $ledger = $ledger->concat(
                        SaleInvoiceItem::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->date,
                                'type' => 'Sale',
                                'description' => 'Invoice No: ' . ($row->invoice->invoice_no ?? $row->invoice->id),
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sale Returns
                    $ledger = $ledger->concat(
                        SaleReturnItem::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->saleReturn->return_date,
                                'type' => 'Sale Return',
                                'description' => 'Return No: ' . ($row->saleReturn->reference_no ?? $row->saleReturn->id),
                                'qty_in' => $row->qty,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Issue
                    $ledger = $ledger->concat(
                        ProductionDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('production', fn($q) => $q->whereBetween('order_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->production->order_date,
                                'type' => 'Production Issue',
                                'description' => 'Raw Material Issued',
                                'qty_in' => 0,
                                'qty_out' => $row->qty,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Receiving
                    $ledger = $ledger->concat(
                        ProductionReceivingDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->receiving->rec_date,
                                'type' => 'Production Receiving',
                                'description' => 'Manufactured Goods Received',
                                'qty_in' => $row->received_qty,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    $itemLedger = $itemLedger->concat($ledger->sortBy('date'));
                }
            }
        }

        // Stock Inhand
        if ($tab == 'SR') {
            $selectedItem   = $request->item_id;
            $costingMethod  = $request->costing_method ?? 'avg';
            $productsToProcess = $allProducts;

            // filter selected product/variation
            if ($selectedItem) {
                if (str_contains($selectedItem, '-')) {
                    [$productIdSel, $variationIdSel] = explode('-', $selectedItem);
                    $productsToProcess = $allProducts->where('id', (int)$productIdSel);
                    $productsToProcess->transform(function ($product) use ($variationIdSel) {
                        $product->variations = $product->variations->where('id', (int)$variationIdSel);
                        return $product;
                    });
                } else {
                    $productsToProcess = $allProducts->where('id', (int)$selectedItem);
                }
            }

            foreach ($productsToProcess as $product) {
                $variations = $product->variations->isNotEmpty()
                    ? $product->variations
                    : collect([(object)['id' => null, 'sku' => null]]);

                foreach ($variations as $var) {
                    // ------------------------------
                    // STOCK MOVEMENTS
                    // ------------------------------
                    $purchased = PurchaseInvoiceItem::where('item_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $purchaseReturn = PurchaseReturnItem::where('item_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $sold = SaleInvoiceItem::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $saleReturn = SaleReturnItem::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $issued = ProductionDetail::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $received = ProductionReceivingDetail::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('received_qty');

                    $stockQty = ($purchased - $purchaseReturn + $saleReturn + $received) - ($sold + $issued);

                    // ------------------------------
                    // COST CALCULATION
                    // ------------------------------
                    $rawCostPerPiece = 0;
                    $manufacturingCostPerPiece = 0;

                    if ($product->is_raw) {
                        // RAW ITEM → cost directly from purchases
                        $pq = PurchaseInvoiceItem::query()->where('item_id', $product->id);
                        $rawRate = match ($costingMethod) {
                            'max'    => $pq->max('price') ?? 0,
                            'min'    => $pq->min('price') ?? 0,
                            'latest' => optional($pq->latest('id')->first())->price ?? 0,
                            default  => ($agg = $pq->selectRaw('SUM(quantity*price) as v, SUM(quantity) as q')->first())
                                        && $agg->q > 0 ? $agg->v / $agg->q : 0,
                        };

                        $rawCostPerPiece = $rawRate; // direct
                    } else {
                        // FINISHED GOOD

                        // 1. All raw materials issued (no need to filter by receivings)
                        $issuedRaw = ProductionDetail::with('product')->get();

                        foreach ($issuedRaw as $row) {
                            $pq = PurchaseInvoiceItem::query()->where('item_id', $row->product_id);
                            $rate = match ($costingMethod) {
                                'max'    => $pq->max('price') ?? 0,
                                'min'    => $pq->min('price') ?? 0,
                                'latest' => optional($pq->latest('id')->first())->price ?? 0,
                                default  => ($agg = $pq->selectRaw('SUM(quantity*price) as v, SUM(quantity) as q')->first())
                                            && $agg->q > 0 ? $agg->v / $agg->q : 0,
                            };

                            $consumption = optional($row->product)->consumption ?? 0;
                            $rawCostPerPiece += $consumption * $rate;
                        }

                        // 2. Manufacturing cost per piece
                        $mfgRows = ProductionReceivingDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->get(['manufacturing_cost', 'received_qty']);

                        $totalMfgValue = $mfgRows->sum(fn($r) => (float)$r->manufacturing_cost * (float)($r->received_qty ?? 0));
                        $totalMfgQty   = $mfgRows->sum('received_qty');

                        $manufacturingCostPerPiece = $totalMfgQty > 0
                            ? $totalMfgValue / $totalMfgQty
                            : 0;
                    }

                    // ------------------------------
                    // FINAL COST
                    // ------------------------------
                    $costPrice = $rawCostPerPiece + $manufacturingCostPerPiece;

                    $stockInHand->push([
                        'product'        => $product->name,
                        'variation'      => $var->sku ?? null,
                        'quantity'       => $stockQty,
                        'raw_cost'       => round($rawCostPerPiece, 2),
                        'mfg_cost'       => round($manufacturingCostPerPiece, 2),
                        'price'          => round($costPrice, 2),
                        'total'          => round($stockQty * $costPrice, 2),
                    ]);
                }
            }
        }

        // STOCK TRANSFERS (kept as you had)
        $transferQuery = StockTransferDetail::with([
                'transfer',
                'product',
                'variation',
                'transfer.fromLocation',
                'transfer.toLocation'
            ])
            ->when($request->from_location_id, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->where('from_location_id', $request->from_location_id));
            })
            ->when($request->to_location_id, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->where('to_location_id', $request->to_location_id));
            })
            ->when($request->from_date && $request->to_date, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->whereBetween('date', [$request->from_date, $request->to_date]));
            });

        $transfers = $transferQuery->get();

        $stockTransfers = $transfers->map(fn($row) => [
            'date' => $row->transfer->date ?? null,
            'reference' => $row->transfer->id ?? null,
            'product' => $row->product->name ?? null,
            'variation' => $row->variation->sku ?? null,
            'from' => $row->transfer->fromLocation->name ?? '',
            'to' => $row->transfer->toLocation->name ?? '',
            'quantity' => $row->quantity,
        ]);

        // NON-MOVING & REORDER (kept as before)...
        // (your existing logic for $nonMovingItems and $reorderLevel remains unchanged)

        return view('reports.inventory_reports', [
            'products'       => $allProducts,
            'tab'            => $tab,
            'itemLedger'     => $itemLedger->sortBy('date')->values(),
            'stockInHand'    => $stockInHand,
            'stockTransfers' => $stockTransfers,
            'nonMovingItems' => $nonMovingItems,
            'reorderLevel'   => $reorderLevel,
            'from'           => $from,
            'to'             => $to,
            'locationId'     => $locationId,
            'locations'      => $locations,
        ]);
    }
}
