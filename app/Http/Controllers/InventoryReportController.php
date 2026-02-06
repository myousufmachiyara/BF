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
            // Calculate Opening Balance
            $opPurchase = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_invoice_items.item_id', $itemId)
            ->where('purchase_invoices.invoice_date', '<', $from)
            ->whereNull('purchase_invoices.deleted_at') // Add this
            ->sum('purchase_invoice_items.quantity');

            $opSale = DB::table('sale_invoice_items')
            ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
            ->where('sale_invoice_items.product_id', $itemId)
            ->where('sale_invoices.date', '<', $from)
            ->whereNull('sale_invoices.deleted_at') // Add this
            ->sum('sale_invoice_items.quantity');

            $opCustom = DB::table('sale_item_customization')
            ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
            ->where('sale_item_customization.item_id', $itemId)
            ->where('sale_invoices.date', '<', $from)
            ->whereNull('sale_invoices.deleted_at') // Add this
            ->count();

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

            $sales = DB::table('sale_invoice_items')
            ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
            ->select(
                'sale_invoices.date as date', 
                DB::raw("'Sale' as type"), 
                // Adding the rate to the description helps identify the specific customization line
                DB::raw("CONCAT(sale_invoices.invoice_no, ' (Rate: ', sale_invoice_items.sale_price, ')') as description"), 
                DB::raw("0 as qty_in"), 
                'sale_invoice_items.quantity as qty_out'
            )
            ->where('sale_invoice_items.product_id', $itemId)
            ->whereNull('sale_invoices.deleted_at')
            ->whereBetween('sale_invoices.date', [$from, $to]);

            $customizations = DB::table('sale_item_customization')
            ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
            ->select(
                'sale_invoices.date as date', 
                DB::raw("'Customization Fee' as type"), 
                'sale_invoices.invoice_no as description', 
                DB::raw("0 as qty_in"), 
                DB::raw("1 as qty_out")
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
                    ->where('purchase_invoices.invoice_date', '<=', $to) // Date Filter
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                // Only count sales UP TO the $to date
                $tOut = DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to) // Date Filter
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                // Only count customizations UP TO the $to date
                $tCustom = DB::table('sale_item_customization')
                    ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_item_customization.item_id', $product->id)
                    ->where('sale_invoices.date', '<=', $to) // Date Filter
                    ->whereNull('sale_invoices.deleted_at')
                    ->count();
                
                $qty = $tIn - $tOut - $tCustom;

                // Price Logic (Usually we want latest price up to the $to date as well)
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
                    'product' => $product->name,
                    'quantity' => $qty,
                    'price' => $price,
                    'total' => $qty * $price
                ];
            });
        }

        return view('reports.inventory_reports', compact(
            'products', 'itemLedger', 'openingQty', 'stockInHand', 'tab', 'from', 'to'
        ));
    }
}