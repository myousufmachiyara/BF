<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ChartOfAccounts;
use App\Models\Voucher;
use App\Models\SaleInvoice;
use App\Models\PurchaseInvoice;
use App\Models\SaleReturn;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        // Non-superadmins get the plain dashboard
        if (!auth()->user()->hasRole('superadmin')) {
            return view('home');
        }

        $today     = Carbon::today()->toDateString();
        $weekStart = Carbon::now()->startOfWeek()->toDateString();
        $monthStart= Carbon::now()->startOfMonth()->toDateString();

        // ── SALES ────────────────────────────────────────────────────────
        $salesToday  = $this->netSales($today,      $today);
        $salesWeek   = $this->netSales($weekStart,  $today);
        $salesMonth  = $this->netSales($monthStart, $today);

        // ── PURCHASES ────────────────────────────────────────────────────
        $purchaseToday = $this->totalPurchases($today,      $today);
        $purchaseWeek  = $this->totalPurchases($weekStart,  $today);
        $purchaseMonth = $this->totalPurchases($monthStart, $today);

        // ── PROFIT (Revenue - COGS vouchers for the period) ──────────────
        $profitToday = $this->netProfit($today,      $today);
        $profitWeek  = $this->netProfit($weekStart,  $today);
        $profitMonth = $this->netProfit($monthStart, $today);

        // ── RECEIVABLES & PAYABLES (as of today) ─────────────────────────
        $totalReceivables = $this->totalReceivables($today);
        $totalPayables    = $this->totalPayables($today);

        // ── SALE RETURNS ──────────────────────────────────────────────────
        $saleReturnsMonth = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->whereNull('sale_returns.deleted_at')
            ->whereBetween('sale_returns.return_date', [$monthStart, $today])
            ->sum(DB::raw('sale_return_items.qty * sale_return_items.price'));

        // ── PURCHASE RETURNS ──────────────────────────────────────────────
        $purchaseReturnsMonth = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->whereNull('purchase_returns.deleted_at')
            ->whereBetween('purchase_returns.return_date', [$monthStart, $today])
            ->sum(DB::raw('purchase_return_items.quantity * purchase_return_items.price'));

        // ── INVOICE COUNTS ────────────────────────────────────────────────
        $invoicesToday = SaleInvoice::whereNull('deleted_at')
            ->whereDate('date', $today)->count();
        $invoicesMonth = SaleInvoice::whereNull('deleted_at')
            ->whereBetween('date', [$monthStart, $today])->count();

        // ── TOP 5 SELLING ITEMS THIS MONTH ────────────────────────────────
        $topSellingItems = DB::table('sale_invoice_items')
            ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
            ->join('products', 'sale_invoice_items.product_id', '=', 'products.id')
            ->whereNull('sale_invoices.deleted_at')
            ->whereBetween('sale_invoices.date', [$monthStart, $today])
            ->groupBy('sale_invoice_items.product_id', 'products.name')
            ->select(
                'products.name',
                DB::raw('SUM(sale_invoice_items.quantity) as total_qty'),
                DB::raw('SUM(sale_invoice_items.quantity * sale_invoice_items.sale_price) as total_revenue')
            )
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // ── ITEMS WITH NEGATIVE / ZERO STOCK ─────────────────────────────
        $negativeStockItems = Product::whereNull('deleted_at')
            ->get()
            ->map(function ($p) {
                $in  = (float) DB::table('purchase_invoice_items')
                    ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
                    ->where('purchase_invoice_items.item_id', $p->id)
                    ->whereNull('purchase_invoices.deleted_at')
                    ->sum('purchase_invoice_items.quantity');

                $out = (float) DB::table('sale_invoice_items')
                    ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
                    ->where('sale_invoice_items.product_id', $p->id)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $custom = (float) DB::table('sale_item_customization')
                    ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
                    ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
                    ->where('sale_item_customization.item_id', $p->id)
                    ->whereNull('sale_invoices.deleted_at')
                    ->sum('sale_invoice_items.quantity');

                $purchaseReturn = (float) DB::table('purchase_return_items')
                    ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                    ->where('purchase_return_items.item_id', $p->id)
                    ->whereNull('purchase_returns.deleted_at')
                    ->sum('purchase_return_items.quantity');

                $saleReturn = (float) DB::table('sale_return_items')
                    ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
                    ->where('sale_return_items.product_id', $p->id)
                    ->whereNull('sale_returns.deleted_at')
                    ->sum('sale_return_items.qty');

                $stock = $in - $out - $custom - $purchaseReturn + $saleReturn;

                return ['name' => $p->name, 'stock' => $stock];
            })
            ->filter(fn($p) => $p['stock'] <= 0)
            ->values();

        // ── MONTHLY SALES TREND (last 6 months) ───────────────────────────
        $salesTrend = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month      = Carbon::now()->subMonths($i);
            $mStart     = $month->copy()->startOfMonth()->toDateString();
            $mEnd       = $month->copy()->endOfMonth()->toDateString();
            $salesTrend->push([
                'month'  => $month->format('M Y'),
                'amount' => $this->netSales($mStart, $mEnd),
            ]);
        }

        // ── CASH & BANK BALANCES ──────────────────────────────────────────
        $cashBalance = $this->accountTypeBalance('cash', $today);
        $bankBalance = $this->accountTypeBalance('bank', $today);

        // ── RECENT 5 INVOICES ─────────────────────────────────────────────
        $recentInvoices = SaleInvoice::with('account')
            ->whereNull('deleted_at')
            ->latest('date')
            ->limit(5)
            ->get()
            ->map(function ($inv) {
                $total = DB::table('sale_invoice_items')
                    ->where('sale_invoice_id', $inv->id)
                    ->sum(DB::raw('quantity * sale_price'));
                $received = Voucher::where('reference', $inv->id)
                    ->where('voucher_type', 'receipt')
                    ->whereNull('deleted_at')
                    ->sum('amount');
                $inv->net_total = $total - ($inv->discount ?? 0);
                $inv->received  = $received;
                $inv->balance   = $inv->net_total - $received;
                return $inv;
            });

        return view('home', compact(
            'salesToday', 'salesWeek', 'salesMonth',
            'purchaseToday', 'purchaseWeek', 'purchaseMonth',
            'profitToday', 'profitWeek', 'profitMonth',
            'totalReceivables', 'totalPayables',
            'saleReturnsMonth', 'purchaseReturnsMonth',
            'invoicesToday', 'invoicesMonth',
            'topSellingItems', 'negativeStockItems',
            'salesTrend', 'cashBalance', 'bankBalance',
            'recentInvoices'
        ));
    }

    // ── Private Helpers ───────────────────────────────────────────────────

    private function netSales(string $from, string $to): float
    {
        $gross = DB::table('sale_invoice_items')
            ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
            ->whereNull('sale_invoices.deleted_at')
            ->whereBetween('sale_invoices.date', [$from, $to])
            ->sum(DB::raw('sale_invoice_items.quantity * sale_invoice_items.sale_price'));

        $discount = DB::table('sale_invoices')
            ->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to])
            ->sum('discount');

        return max(0, (float)$gross - (float)$discount);
    }

    private function totalPurchases(string $from, string $to): float
    {
        return (float) DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->whereBetween('purchase_invoices.invoice_date', [$from, $to])
            ->sum(DB::raw('purchase_invoice_items.quantity * purchase_invoice_items.price'));
    }

    private function netProfit(string $from, string $to): float
    {
        // Revenue from sales revenue vouchers (credit side)
        $revenue = (float) Voucher::whereNull('deleted_at')
            ->where('voucher_type', 'journal')
            ->whereHas('creditAccount', fn($q) => $q->where('account_type', 'revenue'))
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        // COGS from cogs vouchers (debit side)
        $cogs = (float) Voucher::whereNull('deleted_at')
            ->where('voucher_type', 'journal')
            ->whereHas('debitAccount', fn($q) => $q->where('account_type', 'cogs'))
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return $revenue - $cogs;
    }

    private function totalReceivables(string $asOf): float
    {
        return ChartOfAccounts::where('account_type', 'customer')
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($a) use ($asOf) {
                $dr = (float)$a->receivables
                    + (float) Voucher::where('ac_dr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                $cr = (float)$a->payables
                    + (float) Voucher::where('ac_cr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                return max(0, $dr - $cr);
            });
    }

    private function totalPayables(string $asOf): float
    {
        return ChartOfAccounts::where('account_type', 'vendor')
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($a) use ($asOf) {
                $dr = (float)$a->receivables
                    + (float) Voucher::where('ac_dr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                $cr = (float)$a->payables
                    + (float) Voucher::where('ac_cr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                return max(0, $cr - $dr);
            });
    }

    private function accountTypeBalance(string $type, string $asOf): float
    {
        return ChartOfAccounts::where('account_type', $type)
            ->whereNull('deleted_at')
            ->get()
            ->sum(function ($a) use ($asOf) {
                $dr = (float) Voucher::where('ac_dr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                $cr = (float) Voucher::where('ac_cr_sid', $a->id)->where('date', '<=', $asOf)->whereNull('deleted_at')->sum('amount');
                return $dr - $cr;
            });
    }
}