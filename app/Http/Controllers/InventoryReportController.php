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
        if ($tab == 'IL' && $itemId) {
            
            // 1. Calculate Opening Balance (Purchases - Sales - Customizations) before $from date
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

            // FIX: Join by PK using correct FK column 'sale_invoice_items_id'
            // Sums the parent sale item's quantity instead of count() rows
            $opCustom = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->where('sale_item_customization.item_id', $itemId)
                ->where('sale_invoices.date', '<', $from)
                ->whereNull('sale_invoices.deleted_at')
                ->sum('sale_invoice_items.quantity');

            $openingQty = $opPurchase - $opSale - $opCustom;

            // 2. Combine Transactions using Triple UNION
            
            // SOURCE 1: Purchases (Stock In)
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

            // SOURCE 2: Sales (Stock Out)
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

            // SOURCE 3: Customizations (Stock Out)
            // FIX: Join by PK using correct FK column 'sale_invoice_items_id'
            // e.g. 4 chairs sold → wheel/base/machine each deduct 4, not 1
            $customizations = DB::table('sale_item_customization')
                ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                ->select(
                    'sale_invoices.date as date',
                    DB::raw("'Customization Fee' as type"),
                    'sale_invoices.invoice_no as description',
                    DB::raw("0 as qty_in"),
                    'sale_invoice_items.quantity as qty_out'
                )
                ->where('sale_item_customization.item_id', $itemId)
                ->whereNull('sale_invoices.deleted_at')
                ->whereBetween('sale_invoices.date', [$from, $to]);

            // Use unionAll to ensure duplicate invoice numbers with different rates are NOT merged
            $itemLedger = $purchases->unionAll($customizations)->unionAll($sales)->orderBy('date', 'asc')->get();
        }

        // ================= STOCK IN HAND (Current Snapshot) =================
        if ($tab == 'SR') {
            $query = Product::query();
            if ($itemId) $query->where('id', $itemId);

            $stockInHand = $query->get()->map(function ($product) use ($costingMethod, $to) {

                // Only count purchases UP TO the $to date
                $tIn = DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $product->id)
                    ->where('purchase_invoices.invoice_date', '<=', $to)
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                // Only count sales UP TO the $to date
                $tOut = DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                // FIX: Join by PK using correct FK column 'sale_invoice_items_id'
                // Sum parent item quantity — 4 chairs sold = 4 of each part deducted
                $tCustom = DB::table('sale_item_customization')
                    ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                    ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                    ->where('sale_item_customization.item_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');
                
                $qty = $tIn - $tOut - $tCustom;

                // Price Logic: latest price or average up to the $to date
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
                    'total'    => $qty * $price
                ];
            });
        }

        return view('reports.inventory_reports', compact(
            'products', 'itemLedger', 'openingQty', 'stockInHand', 'tab', 'from', 'to'
        ));
    }
}