<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab = $request->get('tab', 'IL');
        $itemId = $request->get('item_id'); 
        $from = $request->get('from_date', date('Y-m-01'));
        $to = $request->get('to_date', date('Y-m-d'));
        $costingMethod = $request->get('costing_method', 'avg');

        $products = Product::orderBy('name', 'asc')->get();
        
        $itemLedger = collect();
        $openingQty = 0;
        $stockInHand = collect();

        // ================= ITEM LEDGER (Specific Product) =================
        // ================= ITEM LEDGER (Specific Product) =================
        if ($tab == 'IL' && $itemId) {

            // Opening Balance
            $opPurchase = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->where('purchase_invoice_items.item_id', $itemId)
                ->where('purchase_invoices.invoice_date', '<', $from)
                ->whereNull('purchase_invoices.deleted_at')
                ->sum('purchase_invoice_items.quantity');

            $opSale = DB::table('sale_invoice_items')
                ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->where('sale_invoice_items.product_id', $itemId)
                ->where('sale_invoices.date', '<', $from)
                ->whereNull('sale_invoices.deleted_at')
                ->sum('sale_invoice_items.quantity');

            $opCustom = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->where('sale_item_customization.item_id', $itemId)
                ->where('sale_invoices.date', '<', $from)
                ->whereNull('sale_invoices.deleted_at')
                ->sum('sale_invoice_items.quantity');

            // NEW: Purchase Returns reduce stock (you sent goods back)
            $opPurchaseReturn = DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->where('purchase_return_items.item_id', $itemId)
                ->where('purchase_returns.return_date', '<', $from)
                ->whereNull('purchase_returns.deleted_at')
                ->sum('purchase_return_items.quantity');

            // NEW: Sale Returns increase stock (customer sent goods back)
            $opSaleReturn = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->where('sale_return_items.product_id', $itemId)
                ->where('sale_returns.return_date', '<', $from)
                ->whereNull('sale_returns.deleted_at')
                ->sum('sale_return_items.qty'); // note: qty not quantity

            $openingQty = $opPurchase - $opSale - $opCustom - $opPurchaseReturn + $opSaleReturn;

            // --- Period Transactions ---

            $purchases = DB::table('purchase_invoice_items')
                ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                ->select(
                    'purchase_invoices.invoice_date as date',
                    DB::raw("'Purchase' as type"),
                    'purchase_invoices.invoice_no as description',
                    'purchase_invoice_items.quantity as qty_in',
                    DB::raw("0 as qty_out")
                )
                ->where('purchase_invoice_items.item_id', $itemId)
                ->whereNull('purchase_invoices.deleted_at')
                ->whereBetween('purchase_invoices.invoice_date', [$from, $to]);

            $sales = DB::table('sale_invoice_items')
                ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                ->select(
                    'sale_invoices.date as date',
                    DB::raw("'Sale' as type"),
                    DB::raw("CONCAT(sale_invoices.invoice_no, ' (Rate: ', sale_invoice_items.sale_price, ')') as description"),
                    DB::raw("0 as qty_in"),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_invoice_items.product_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->whereBetween('sale_invoices.date', [$from, $to]);

            $customizations = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->select(
                    'sale_invoices.date as date',
                    DB::raw("'Customization' as type"),
                    'sale_invoices.invoice_no as description',
                    DB::raw("0 as qty_in"),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_item_customization.item_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->whereBetween('sale_invoices.date', [$from, $to]);

            // NEW: Purchase Returns — stock out
            $purchaseReturns = DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->select(
                    'purchase_returns.return_date as date',
                    DB::raw("'Purchase Return' as type"),
                    'purchase_returns.invoice_no as description',
                    DB::raw("0 as qty_in"),
                    'purchase_return_items.quantity as qty_out'
                )
                ->where('purchase_return_items.item_id', $itemId)
                ->whereNull('purchase_returns.deleted_at')
                ->whereBetween('purchase_returns.return_date', [$from, $to]);

            // NEW: Sale Returns — stock in
            $saleReturns = DB::table('sale_return_items')
                ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                ->select(
                    'sale_returns.return_date as date',
                    DB::raw("'Sale Return' as type"),
                    'sale_returns.invoice_no as description',
                    'sale_return_items.qty as qty_in', // qty_in because stock comes back
                    DB::raw("0 as qty_out")
                )
                ->where('sale_return_items.product_id', $itemId)
                ->whereNull('sale_returns.deleted_at')
                ->whereBetween('sale_returns.return_date', [$from, $to]);

            $itemLedger = $purchases
                ->unionAll($customizations)
                ->unionAll($sales)
                ->unionAll($purchaseReturns) // NEW
                ->unionAll($saleReturns)     // NEW
                ->orderBy('date', 'asc')
                ->get();
        }

        // ================= STOCK IN HAND (Current Snapshot) =================
        if ($tab == 'SR') {
            $query = Product::query();
            if ($itemId) $query->where('id', $itemId);

            $stockInHand = $query->get()->map(function ($product) use ($costingMethod, $to) {

                $tIn = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                $tOut = DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $tCustom = DB::table('sale_item_customization')
                    ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                    ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                    ->where('sale_item_customization.item_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                // NEW: Purchase Returns reduce stock
                $tPurchaseReturn = DB::table('purchase_return_items')
                    ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                    ->where('purchase_return_items.item_id', $product->id)
                    ->where('purchase_returns.return_date', '<=', $to)
                    ->whereNull('purchase_returns.deleted_at')
                    ->sum('purchase_return_items.quantity');

                // NEW: Sale Returns increase stock
                $tSaleReturn = DB::table('sale_return_items')
                    ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                    ->where('sale_return_items.product_id', $product->id)
                    ->where('sale_returns.return_date', '<=', $to)
                    ->whereNull('sale_returns.deleted_at')
                    ->sum('sale_return_items.qty'); // note: qty not quantity

                $qty = $tIn - $tOut - $tCustom - $tPurchaseReturn + $tSaleReturn;

                // Price logic unchanged ...
                $priceQuery = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at');

                if ($costingMethod == 'latest') {
                    $price = $priceQuery->latest('purchase_invoices.invoice_date')->value('price') ?? 0;
                } else {
                    $price = $priceQuery->avg('price') ?? 0;
                }

                return [
                    'product'  => $product->name,
                    'quantity' => $qty,
                    'price'    => $price,
                    'total'    => $qty * $price,
                ];
            });
        }

        return view('reports.inventory_reports', compact(
            'products', 'itemLedger', 'openingQty', 'stockInHand', 'tab', 'from', 'to'
        ));
    }
}