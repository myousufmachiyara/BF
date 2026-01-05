<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use App\Models\Voucher;
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
            'cash_flow'        => $this->cashFlow($from, $to),
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
        if (!$accountId) return collect();

        $rows = Voucher::whereBetween('date', [$from, $to])
            ->where(function ($q) use ($accountId) {
                $q->where('ac_dr_sid', $accountId)
                  ->orWhere('ac_cr_sid', $accountId);
            })
            ->orderBy('date')
            ->get()
            ->map(function ($v) use ($accountId) {
                $isDebit = $v->ac_dr_sid == $accountId;
                return [
                    $v->date,
                    "Voucher #{$v->id}",
                    ChartOfAccounts::find($isDebit ? $v->ac_cr_sid : $v->ac_dr_sid)->name ?? '',
                    $isDebit ? $v->amount : 0,
                    $isDebit ? 0 : $v->amount,
                    0
                ];
            });

        return $this->running($rows);
    }

    /* ================= TRIAL BALANCE ================= */
    private function trialBalance($from, $to)
    {
        $accounts = ChartOfAccounts::all();

        return $accounts->map(function ($a) use ($from, $to) {
            $debit = $credit = 0;

            switch ($a->account_type) {

                // CUSTOMER: Sales
                case 'customer':
                    $sales = SaleInvoice::where('account_id', $a->id)
                        ->whereBetween('date', [$from, $to])
                        ->with('items')
                        ->get()
                        ->sum(fn($s) => $s->items->sum(fn($i) => $i->sale_price * $i->quantity) - $s->discount);

                    $saleReturns = SaleReturn::where('account_id', $a->id)
                        ->whereBetween('return_date', [$from, $to])
                        ->with('items')
                        ->get()
                        ->sum(fn($r) => $r->items->sum(fn($i) => $i->price * $i->qty));

                    $receipts = Voucher::whereBetween('date', [$from, $to])
                        ->where('ac_cr_sid', $a->id)
                        ->sum('amount'); // Customer payments

                    $credit = $sales - $saleReturns - $receipts;
                    break;

                // VENDOR: Purchases
                case 'vendor':
                    $purchases = PurchaseInvoice::where('vendor_id', $a->id)
                        ->whereBetween('invoice_date', [$from, $to])
                        ->with('items')
                        ->get()
                        ->sum(fn($p) => $p->items->sum(fn($i) => $i->price * $i->quantity));

                    $purchaseReturns = PurchaseReturn::where('vendor_id', $a->id)
                        ->whereBetween('return_date', [$from, $to])
                        ->with('items')
                        ->get()
                        ->sum(fn($r) => $r->items->sum(fn($i) => $i->price * $i->quantity));

                    $payments = Voucher::whereBetween('date', [$from, $to])
                        ->where('ac_dr_sid', $a->id)
                        ->sum('amount'); // Vendor payments

                    $debit = $purchases - $purchaseReturns - $payments;
                    break;

                // ASSETS / LIABILITIES / EXPENSES / REVENUE
                default:
                    $vouchersDr = Voucher::where('ac_dr_sid', $a->id)
                        ->whereBetween('date', [$from, $to])
                        ->sum('amount');

                    $vouchersCr = Voucher::where('ac_cr_sid', $a->id)
                        ->whereBetween('date', [$from, $to])
                        ->sum('amount');

                    if (in_array($a->account_type, ['asset', 'expense'])) {
                        $debit += $vouchersDr;
                        $credit += $vouchersCr;
                    } else {
                        $debit += $vouchersDr;
                        $credit += $vouchersCr;
                    }
                    break;
            }

            return [
                $a->name,
                $a->account_type,
                $this->fmt($debit),
                $this->fmt($credit),
            ];
        });
    }

    /* ================= PROFIT & LOSS ================= */
    private function profitLoss($from, $to)
    {
        $trial = $this->trialBalance($from, $to);

        $revenue = $trial->filter(fn($r) => $r[1] === 'customer')->sum(fn($r)=> floatval(str_replace(',', '', $r[3])));
        $expense = $trial->filter(fn($r) => $r[1] === 'vendor' || $r[1]==='expense')->sum(fn($r)=> floatval(str_replace(',', '', $r[2])));

        return [
            ['Revenue', $this->fmt($revenue)],
            ['Expenses', $this->fmt($expense)],
            ['Net Profit', $this->fmt($revenue - $expense)]
        ];
    }

    /* ================= PARTY LEDGER ================= */
    private function partyLedger($from, $to, $accountId = null)
    {
        if (!$accountId) return collect();

        $account = ChartOfAccounts::find($accountId);
        if (!$account) return collect();

        $rows = collect();

        if ($account->account_type === 'customer') {
            // Sales
            $sales = SaleInvoice::where('account_id', $accountId)
                ->whereBetween('date', [$from, $to])
                ->with('items')
                ->get();

            foreach ($sales as $s) {
                $total = $s->items->sum(fn($i) => $i->sale_price * $i->quantity) - $s->discount;
                $rows->push([$s->date, $account->name, "Sale #{$s->id}", $total, 0, 0]);
            }

            // Sale Returns
            $returns = SaleReturn::where('account_id', $accountId)
                ->whereBetween('return_date', [$from, $to])
                ->with('items')
                ->get();

            foreach ($returns as $r) {
                $total = $r->items->sum(fn($i) => $i->price * $i->qty);
                $rows->push([$r->return_date, $account->name, "Sale Return #{$r->id}", 0, $total, 0]);
            }

            // Customer Payments (Voucher)
            $vouchers = Voucher::whereBetween('date', [$from, $to])
                ->where('ac_cr_sid', $accountId)
                ->get();

            foreach ($vouchers as $v) {
                $rows->push([$v->date, $account->name, "Payment Voucher #{$v->id}", 0, $v->amount, 0]);
            }

        } elseif ($account->account_type === 'vendor') {
            // Purchases
            $purchases = PurchaseInvoice::where('vendor_id', $accountId)
                ->whereBetween('invoice_date', [$from, $to])
                ->with('items')
                ->get();

            foreach ($purchases as $p) {
                $total = $p->items->sum(fn($i) => $i->price * $i->quantity);
                $rows->push([$p->invoice_date, $account->name, "Purchase #{$p->invoice_no}", $total, 0, 0]);
            }

            // Purchase Returns
            $returns = PurchaseReturn::where('vendor_id', $accountId)
                ->whereBetween('return_date', [$from, $to])
                ->with('items')
                ->get();

            foreach ($returns as $r) {
                $total = $r->items->sum(fn($i) => $i->price * $i->quantity);
                $rows->push([$r->return_date, $account->name, "Purchase Return #{$r->invoice_no}", 0, $total, 0]);
            }

            // Vendor Payments (Voucher)
            $vouchers = Voucher::whereBetween('date', [$from, $to])
                ->where('ac_dr_sid', $accountId)
                ->get();

            foreach ($vouchers as $v) {
                $rows->push([$v->date, $account->name, "Payment Voucher #{$v->id}", 0, $v->amount, 0]);
            }
        }

        // Sort by date and calculate running balance
        return $this->running($rows->sortBy('0')->values());
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

                // Include payments received from customer
                $payments = Voucher::whereBetween('date', [$from, $to])
                    ->where('ac_cr_sid', $a->id)
                    ->sum('amount');

                $balance = $saleTotal - $returnTotal - $payments;

                return [$a->name, $this->fmt($balance)];
            });
    }

    private function payables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'vendor')->get()
            ->map(function ($a) use ($from, $to) {

                // Purchases
                $purchases = PurchaseInvoice::where('vendor_id', $a->id)
                    ->whereBetween('invoice_date', [$from, $to])
                    ->with('items')
                    ->get();

                $purchaseTotal = $purchases->sum(fn($p) => $p->items->sum(fn($i) => $i->price * $i->quantity));

                // Purchase Returns
                $returns = PurchaseReturn::where('vendor_id', $a->id)
                    ->whereBetween('return_date', [$from, $to])
                    ->with('items')
                    ->get();

                $returnTotal = $returns->sum(fn($r) => $r->items->sum(fn($i) => $i->price * $i->quantity));

                // Payments made to vendor
                $payments = Voucher::whereBetween('date', [$from, $to])
                    ->where('ac_dr_sid', $a->id)
                    ->sum('amount');

                // Outstanding payable
                $balance = $purchaseTotal - $returnTotal - $payments;

                return [$a->name, $this->fmt($balance)];
            });
    }

    /* ================= BALANCE SHEET ================= */
    private function balanceSheet($from, $to)
    {
        $trial = $this->trialBalance($from, $to);

        $assets = collect();
        $liabilities = collect();

        foreach ($trial as $r) {
            $name = $r[0];
            $type = $r[1];
            $debit = floatval(str_replace(',', '', $r[2]));
            $credit = floatval(str_replace(',', '', $r[3]));

            // ASSETS
            if (in_array($type, ['asset', 'customer'])) {
                $assets->push([$name, number_format($debit - $credit, 2)]);
            }

            // LIABILITIES
            if (in_array($type, ['liability', 'vendor', 'equity'])) {
                $liabilities->push([$name, number_format($credit - $debit, 2)]);
            }
        }

        // Align rows
        $rows = [];
        $max = max($assets->count(), $liabilities->count());
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $assets[$i][0] ?? '',
                $assets[$i][1] ?? '',
                $liabilities[$i][0] ?? '',
                $liabilities[$i][1] ?? '',
            ];
        }

        return $rows;
    }

    /* ================= CASH BOOK ================= */
    private function cashBook($from, $to)
    {
        $cashIds = ChartOfAccounts::where('account_type','cash')->pluck('id')->toArray();

        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(function($q) use ($cashIds) {
                $q->whereIn('ac_dr_sid', $cashIds)
                ->orWhereIn('ac_cr_sid', $cashIds);
            })
            ->orderBy('date')
            ->get();

        $rows = $vouchers->map(function($v) use ($cashIds) {
            $debit = in_array($v->ac_dr_sid, $cashIds) ? $v->amount : 0;
            $credit = in_array($v->ac_cr_sid, $cashIds) ? $v->amount : 0;

            return [
                $v->date,
                ChartOfAccounts::find($v->ac_dr_sid)->name ?? '',
                ChartOfAccounts::find($v->ac_cr_sid)->name ?? '',
                $debit,
                $credit,
                0 // running balance placeholder
            ];
        });

        // Calculate running balance
        $bal = 0;
        return $rows->map(function($r) use (&$bal) {
            $bal += $r[3] - $r[4]; // debit - credit
            $r[5] = $this->fmt($bal);
            return $r;
        });
    }

    /* ================= BANK BOOK ================= */
    private function bankBook($from, $to)
    {
        $bankIds = ChartOfAccounts::where('account_type','bank')->pluck('id')->toArray();

        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(function($q) use ($bankIds) {
                $q->whereIn('ac_dr_sid', $bankIds)
                ->orWhereIn('ac_cr_sid', $bankIds);
            })
            ->orderBy('date')
            ->get();

        $rows = $vouchers->map(function($v) use ($bankIds) {
            $debit = in_array($v->ac_dr_sid, $bankIds) ? $v->amount : 0;
            $credit = in_array($v->ac_cr_sid, $bankIds) ? $v->amount : 0;

            return [
                $v->date,
                ChartOfAccounts::find($v->ac_dr_sid)->name ?? '',
                ChartOfAccounts::find($v->ac_cr_sid)->name ?? '',
                $debit,
                $credit,
                0 // running balance placeholder
            ];
        });

        // Calculate running balance
        $bal = 0;
        return $rows->map(function($r) use (&$bal) {
            $bal += $r[3] - $r[4]; // debit - credit
            $r[5] = $this->fmt($bal);
            return $r;
        });
    }

    /* ================= JOURNAL ================= */
    private function journalBook($from, $to)
    {
        return Voucher::whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(function($v){
                return [
                    $v->date,
                    ChartOfAccounts::find($v->ac_dr_sid)->name ?? '',
                    ChartOfAccounts::find($v->ac_cr_sid)->name ?? '',
                    $v->amount
                ];
            });
    }

    /* ================= EXPENSE ANALYSIS ================= */
    private function expenseAnalysis($from, $to)
    {
        return ChartOfAccounts::where('account_type','expense')->get()
            ->map(function($a) use ($from, $to){
                $amount = Voucher::where('ac_dr_sid',$a->id)
                    ->whereBetween('date',[$from,$to])
                    ->sum('amount');
                return [$a->name, $this->fmt($amount)];
            });
    }

    /* ================= CASH FLOW ================= */
    private function cashFlow($from, $to)
    {
        $cashIn  = Voucher::whereBetween('date',[$from,$to])
            ->whereIn('ac_cr_sid', ChartOfAccounts::where('account_type','cash')->pluck('id'))
            ->sum('amount');
        $cashOut = Voucher::whereBetween('date',[$from,$to])
            ->whereIn('ac_dr_sid', ChartOfAccounts::where('account_type','cash')->pluck('id'))
            ->sum('amount');

        return [
            ['Cash In', $this->fmt($cashIn)],
            ['Cash Out', $this->fmt($cashOut)],
            ['Net Cash Flow', $this->fmt($cashIn - $cashOut)]
        ];
    }
}
