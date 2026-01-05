<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use Carbon\Carbon;
use DB;

class AccountsReportController extends Controller
{
    public function accounts(Request $request)
    {
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->endOfMonth()->toDateString();
        $chartOfAccounts = ChartOfAccounts::orderBy('name')->get();
        $accountId = $request->account_id;

        $reports = [
            'general_ledger'   => $this->generalLedger($accountId, $from, $to),
            'trial_balance'    => $this->trialBalance($from, $to),
            'profit_loss'      => $this->profitLoss($from, $to),
            'balance_sheet'    => $this->balanceSheet($from, $to),
            'party_ledger'     => $this->partyLedger($from, $to, $accountId),
            'receivables'      => $this->receivables($from, $to),
            'payables'         => $this->payables($from, $to),
            'cash_book'        => $this->cashBook($from, $to),
            'bank_book'        => $this->bankBook($from, $to),
            'journal_book'     => $this->journalBook($from, $to),
            'expense_analysis' => $this->expenseAnalysis($from, $to),
            'cash_flow'        => $this->cashFlow(),
        ];

        return view('reports.accounts_reports', compact(
            'reports','from','to','chartOfAccounts'
        ));
    }

    /* ================= HELPERS ================= */

    private function fmt($v) { return number_format($v, 2); }

    private function running($rows)
    {
        $bal = 0;
        return collect($rows)->map(function($r) use (&$bal){
            $bal += $r[3] - $r[4]; // Debit - Credit
            $r[5] = $this->fmt($bal);
            return $r;
        });
    }

    /* ================= GENERAL LEDGER ================= */
    private function generalLedger($accountId, $from, $to)
    {
        if (!$accountId) return [];

        $rows = collect();

        // Sales
        $sales = SaleInvoice::whereBetween('date', [$from, $to])
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->with('items', 'account')
            ->get();

        foreach ($sales as $s) {
            $total = $s->items->sum(fn($i)=> $i->sale_price * $i->quantity) - $s->discount;
            $rows->push([
                $s->date,
                "Sale #$s->invoice_no",
                $s->account->name ?? '',
                0,
                $total,
                0
            ]);
        }

        // Sale Returns
        $saleReturns = SaleReturn::whereBetween('return_date', [$from, $to])
            ->when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->with('items', 'account')
            ->get();

        foreach ($saleReturns as $r) {
            $total = $r->items->sum(fn($i)=> $i->price * $i->qty);
            $rows->push([
                $r->return_date,
                "Sale Return #$r->invoice_no",
                $r->account->name ?? '',
                $total,
                0,
                0
            ]);
        }

        // Purchases
        $purchases = PurchaseInvoice::whereBetween('invoice_date', [$from, $to])
            ->when($accountId, fn($q) => $q->where('vendor_id', $accountId))
            ->with('items', 'vendor')
            ->get();

        foreach ($purchases as $p) {
            $total = $p->items->sum(fn($i)=> $i->price * $i->quantity);
            $rows->push([
                $p->invoice_date,
                "Purchase #$p->invoice_no",
                $p->vendor->name ?? '',
                $total,
                0,
                0
            ]);
        }

        // Purchase Returns
        $purchaseReturns = PurchaseReturn::whereBetween('return_date', [$from, $to])
            ->when($accountId, fn($q) => $q->where('vendor_id', $accountId))
            ->with('items', 'vendor')
            ->get();

        foreach ($purchaseReturns as $r) {
            $total = $r->items->sum(fn($i)=> $i->price * $i->quantity);
            $rows->push([
                $r->return_date,
                "Purchase Return #$r->invoice_no",
                $r->vendor->name ?? '',
                0,
                $total,
                0
            ]);
        }

        return $this->running($rows->sortBy(0)->values());
    }

    /* ================= TRIAL BALANCE ================= */
    private function trialBalance($from, $to)
    {
        $accounts = ChartOfAccounts::all();

        return $accounts->map(function ($a) use ($from, $to) {
            $debit = $credit = 0;

            // Customers: Sales
            if ($a->account_type === 'customer') {
                $sales = SaleInvoice::where('account_id', $a->id)
                    ->whereBetween('date', [$from, $to])
                    ->with('items')
                    ->get();
                $saleTotal = $sales->sum(fn($s)=> $s->items->sum(fn($i)=> $i->sale_price * $i->quantity) - $s->discount);

                $saleReturns = SaleReturn::where('account_id', $a->id)
                    ->whereBetween('return_date', [$from, $to])
                    ->with('items')
                    ->get();
                $returnTotal = $saleReturns->sum(fn($r)=> $r->items->sum(fn($i)=> $i->price * $i->qty));

                $credit = $saleTotal - $returnTotal;
            }

            // Vendors: Purchases
            if ($a->account_type === 'vendor') {
                $purchases = PurchaseInvoice::where('vendor_id', $a->id)
                    ->whereBetween('invoice_date', [$from, $to])
                    ->with('items')
                    ->get();
                $purchaseTotal = $purchases->sum(fn($p)=> $p->items->sum(fn($i)=> $i->price * $i->quantity));

                $purchaseReturns = PurchaseReturn::where('vendor_id', $a->id)
                    ->whereBetween('return_date', [$from, $to])
                    ->with('items')
                    ->get();
                $returnTotal = $purchaseReturns->sum(fn($r)=> $r->items->sum(fn($i)=> $i->price * $i->quantity));

                $debit = $purchaseTotal - $returnTotal;
            }

            return [
                $a->name,
                $a->account_type,
                $this->fmt($debit),
                $this->fmt($credit)
            ];
        });
    }

    /* ================= PROFIT & LOSS ================= */
    private function profitLoss($from, $to)
    {
        $trial = $this->trialBalance($from, $to);

        $revenue = $trial->where('1','customer')->sum(fn($r)=> floatval($r[3])); // Credit
        $expense = $trial->where('1','vendor')->sum(fn($r)=> floatval($r[2])); // Debit

        return [
            ['Revenue', $this->fmt($revenue)],
            ['Expenses', $this->fmt($expense)],
            ['Net Profit', $this->fmt($revenue - $expense)]
        ];
    }

    /* ================= PARTY LEDGER ================= */
    private function partyLedger($from, $to, $accountId = null)
    {
        $rows = collect();

        // Sales
        $sales = SaleInvoice::whereBetween('date', [$from, $to])
            ->when($accountId, fn($q)=>$q->where('account_id', $accountId))
            ->with('items', 'account')
            ->get();

        foreach ($sales as $s) {
            $total = $s->items->sum(fn($i)=> $i->sale_price * $i->quantity) - $s->discount;
            $rows->push([$s->date, $s->account->name ?? '', "Sale #$s->invoice_no", 0, $total, 0]);
        }

        // Sale Returns
        $saleReturns = SaleReturn::whereBetween('return_date', [$from, $to])
            ->when($accountId, fn($q)=>$q->where('account_id', $accountId))
            ->with('items', 'account')
            ->get();

        foreach ($saleReturns as $r) {
            $total = $r->items->sum(fn($i)=> $i->price * $i->qty);
            $rows->push([$r->return_date, $r->account->name ?? '', "Sale Return #$r->invoice_no", $total, 0, 0]);
        }

        // Purchases
        $purchases = PurchaseInvoice::whereBetween('invoice_date', [$from, $to])
            ->when($accountId, fn($q)=>$q->where('vendor_id', $accountId))
            ->with('items', 'vendor')
            ->get();

        foreach ($purchases as $p) {
            $total = $p->items->sum(fn($i)=> $i->price * $i->quantity);
            $rows->push([$p->invoice_date, $p->vendor->name ?? '', "Purchase #$p->invoice_no", $total, 0, 0]);
        }

        // Purchase Returns
        $purchaseReturns = PurchaseReturn::whereBetween('return_date', [$from, $to])
            ->when($accountId, fn($q)=>$q->where('vendor_id', $accountId))
            ->with('items', 'vendor')
            ->get();

        foreach ($purchaseReturns as $r) {
            $total = $r->items->sum(fn($i)=> $i->price * $i->quantity);
            $rows->push([$r->return_date, $r->vendor->name ?? '', "Purchase Return #$r->invoice_no", 0, $total, 0]);
        }

        return $this->running($rows->sortBy(0)->values());
    }

    /* ================= RECEIVABLES / PAYABLES ================= */
    private function receivables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'customer')->get()
            ->map(function ($a) use ($from, $to) {
                $sales = SaleInvoice::where('account_id', $a->id)
                    ->whereBetween('date', [$from, $to])
                    ->with('items')
                    ->get();
                $saleTotal = $sales->sum(fn($s)=> $s->items->sum(fn($i)=> $i->sale_price * $i->quantity) - $s->discount);

                $returns = SaleReturn::where('account_id', $a->id)
                    ->whereBetween('return_date', [$from, $to])
                    ->with('items')
                    ->get();
                $returnTotal = $returns->sum(fn($r)=> $r->items->sum(fn($i)=> $i->price * $i->qty));

                return [$a->name, $this->fmt($saleTotal - $returnTotal)];
            });
    }

    private function payables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'vendor')->get()
            ->map(function ($a) use ($from, $to) {
                $purchases = PurchaseInvoice::where('vendor_id', $a->id)
                    ->whereBetween('invoice_date', [$from, $to])
                    ->with('items')
                    ->get();
                $purchaseTotal = $purchases->sum(fn($p)=> $p->items->sum(fn($i)=> $i->price * $i->quantity));

                $returns = PurchaseReturn::where('vendor_id', $a->id)
                    ->whereBetween('return_date', [$from, $to])
                    ->with('items')
                    ->get();
                $returnTotal = $returns->sum(fn($r)=> $r->items->sum(fn($i)=> $i->price * $i->quantity));

                return [$a->name, $this->fmt($purchaseTotal - $returnTotal)];
            });
    }

    /* ================= BALANCE SHEET ================= */
    private function balanceSheet($from, $to)
    {
        $trial = $this->trialBalance($from, $to);

        $assets = $trial->filter(fn($r)=> strtolower($r[1])==='asset')->values();
        $liabs  = $trial->filter(fn($r)=> strtolower($r[1])==='liability')->values();

        $rows = [];
        $max = max($assets->count(), $liabs->count());

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $assets[$i][0] ?? '',
                $assets[$i][2] ?? '',
                $liabs[$i][0] ?? '',
                $liabs[$i][3] ?? ''
            ];
        }

        return $rows;
    }

    /* ================= CASH / BANK ================= */
    private function cashBook($from, $to){ return []; }
    private function bankBook($from, $to){ return []; }

    /* ================= JOURNAL ================= */
    private function journalBook($from, $to)
    {
        return []; // optional: you can add voucher-based journal later
    }

    private function expenseAnalysis($from, $to){ return []; }
    private function cashFlow(){ return []; }
}
