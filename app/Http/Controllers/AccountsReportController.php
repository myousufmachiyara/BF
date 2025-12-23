<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\ChartOfAccounts;
use App\Models\PurchaseInvoice;
use App\Models\SaleInvoice;
use App\Models\Production;
use App\Models\ProductionReceiving;
use Carbon\Carbon;
use DB;

class AccountsReportController extends Controller
{
    public function accounts(Request $request)
    {
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->endOfMonth()->toDateString();
        $report = $request->report ?? 'general_ledger';
        $chartOfAccounts = ChartOfAccounts::get();
        $partyId  = $request->account_id;   // 👈 selected account from filter

        $reports = [
            'general_ledger' => $this->generalLedger($partyId, $from, $to),
            'trial_balance'    => $this->trialBalance($from, $to),
            'profit_loss'      => $this->profitLoss($from, $to),
            'balance_sheet'    => $this->balanceSheet($from, $to),
            'party_ledger'     => $this->partyLedger($from, $to, $partyId),   // 👈 pass it
            'receivables'      => $this->receivables($from, $to, $partyId),   // 👈 pass it
            'payables'         => $this->payables($from, $to, $partyId),      // 👈 pass it
            'cash_book'        => $this->cashBook($from, $to),
            'bank_book'        => $this->bankBook($from, $to),
            'journal_book'     => $this->journalBook($from, $to),
            'expense_analysis' => $this->expenseAnalysis($from, $to),
            'cash_flow'        => $this->cashFlow($from, $to),
        ];

        return view('reports.accounts_reports', compact('reports', 'from', 'to', 'report', 'chartOfAccounts'));
    }

    # --------------------------------------------------------
    # 🔹 Helpers
    # --------------------------------------------------------
    private function formatAmount($value)
    {
        return number_format($value, 2);
    }

    private function runningBalance($rows)
    {
        $balance = 0;
        foreach ($rows as &$row) {
            $balance += floatval(str_replace(',','',$row['debit'])) - floatval(str_replace(',','',$row['credit']));
            $row['balance'] = $this->formatAmount($balance);
        }
        unset($row);
        return $rows;
    }

    # --------------------------------------------------------
    # 🔹 Reports
    # --------------------------------------------------------

    private function generalLedger($accountId, $from, $to)
    {
        $vouchers = Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->where(function ($q) use ($accountId) {
                $q->where('ac_dr_sid', $accountId)
                ->orWhere('ac_cr_sid', $accountId);
            })
            ->orderBy('date')
            ->get();

        $rows = $vouchers->map(function ($v) use ($accountId) {
            $isDebit = $v->ac_dr_sid == $accountId;

            return [
                'date'        => $v->date,
                'description' => $v->remarks ?? ucfirst($v->voucher_type)." #{$v->id}",
                'debit'       => $isDebit ? $this->formatAmount($v->amount) : '0',
                'credit'      => !$isDebit ? $this->formatAmount($v->amount) : '0',
                'balance'     => 0,
            ];
        })->toArray();

        return $this->runningBalance($rows);
    }

    private function trialBalance($from, $to)
    {
        return DB::table('vouchers')
            ->join('chart_of_accounts as coa_dr', 'vouchers.ac_dr_sid', '=', 'coa_dr.id')
            ->join('chart_of_accounts as coa_cr', 'vouchers.ac_cr_sid', '=', 'coa_cr.id')
            ->whereBetween('vouchers.date', [$from,$to])
            ->select(
                'coa_dr.id as account_id',
                'coa_dr.name as account',
                'coa_dr.account_type',
                DB::raw('SUM(vouchers.amount) as debit'),
                DB::raw('0 as credit')
            )
            ->groupBy('coa_dr.id','coa_dr.name','coa_dr.account_type')

            ->unionAll(
                DB::table('vouchers')
                    ->join('chart_of_accounts as coa_cr2', 'vouchers.ac_cr_sid', '=', 'coa_cr2.id')
                    ->whereBetween('vouchers.date', [$from,$to])
                    ->select(
                        'coa_cr2.id as account_id',
                        'coa_cr2.name as account',
                        'coa_cr2.account_type',
                        DB::raw('0 as debit'),
                        DB::raw('SUM(vouchers.amount) as credit')
                    )
                    ->groupBy('coa_cr2.id','coa_cr2.name','coa_cr2.account_type')
            )
            ->get()
            ->map(fn($row)=> [
                'account'      => $row->account,
                'account_type' => $row->account_type,
                'debit'        => $this->formatAmount($row->debit),
                'credit'       => $this->formatAmount($row->credit),
            ]);
    }

    private function profitLoss($from, $to)
    {
        $trial = $this->trialBalance($from,$to);

        $revenue = $trial->filter(fn($r)=> $r['account_type'] === 'revenue')
                         ->sum(fn($r)=> floatval(str_replace(',','',$r['credit'])));

        $expenses = $trial->filter(fn($r)=> $r['account_type'] === 'expense')
                          ->sum(fn($r)=> floatval(str_replace(',','',$r['debit'])));

        return [
            ['particulars'=>'Revenue','amount'=>$this->formatAmount($revenue)],
            ['particulars'=>'Expenses','amount'=>$this->formatAmount($expenses)],
            ['particulars'=>'Net Profit','amount'=>$this->formatAmount($revenue - $expenses)],
        ];
    }

    private function balanceSheet($from, $to)
    {
        $trial = $this->trialBalance($from,$to);

        $assets = $trial->filter(fn($r)=> strtolower($r['account_type']) === 'asset')->values();
        $liabs  = $trial->filter(fn($r)=> strtolower($r['account_type']) === 'liability')->values();

        $rows = [];
        $max = max($assets->count(), $liabs->count());
        for ($i=0;$i<$max;$i++) {
            $rows[] = [
                'asset'     => $assets[$i]['account'] ?? '',
                'asset_amt' => $assets[$i]['debit'] ?? '',
                'liab'      => $liabs[$i]['account'] ?? '',
                'liab_amt'  => $liabs[$i]['credit'] ?? '',
            ];
        }
        return $rows;
    }

    private function partyLedger($from, $to, $partyId = null)
    {
        $rows = collect();

        // 1️⃣ Purchases (Debit)
        $rows = $rows->merge(
            PurchaseInvoice::when($partyId, fn($q) => $q->where('vendor_id', $partyId))
                ->whereBetween('invoice_date', [$from, $to])
                ->with(['items','vendor'])
                ->get()
                ->map(function($p){
                    $total = $p->items->sum(fn($i) => $i->quantity * $i->price);
                    return [
                        'date'    => $p->invoice_date,
                        'party'   => $p->vendor->name ?? 'N/A',
                        'voucher' => "Purchase #{$p->id}",
                        'debit'   => $this->formatAmount($total),
                        'credit'  => '0',
                        'balance' => 0,
                    ];
                })
        );

        // 2️⃣ Purchase Returns (Credit)
        $rows = $rows->merge(
            \App\Models\PurchaseReturn::when($partyId, fn($q) => $q->where('vendor_id', $partyId))
                ->whereBetween('return_date', [$from, $to])
                ->with(['items','vendor'])
                ->get()
                ->map(function($pr){
                    $total = $pr->items->sum(fn($i) => $i->quantity * $i->price);
                    return [
                        'date'    => $pr->return_date,
                        'party'   => $pr->vendor->name ?? 'N/A',
                        'voucher' => "Purchase Return #{$pr->id}",
                        'debit'   => '0',
                        'credit'  => $this->formatAmount($total),
                        'balance' => 0,
                    ];
                })
        );

        // 3️⃣b Production (if Sale Leather → Credit)
        $rows = $rows->merge(
            Production::when($partyId, fn($q) => $q->where('vendor_id', $partyId))
                ->whereBetween('order_date', [$from, $to])
                ->where('production_type', 'sale_leather')
                ->with(['details','vendor'])
                ->get()
                ->map(function($prod){
                    $total = $prod->details->sum(fn($d) => $d->qty * $d->rate);

                    return [
                        'date'    => $prod->order_date,
                        'party'   => $prod->vendor->name ?? 'N/A',
                        'voucher' => "Sale Leather in Production #{$prod->id}",
                        'debit'   => '0',
                        'credit'  => $this->formatAmount($total),
                        'balance' => 0,
                    ];
                })
        );

        // 3️⃣ Production Receiving (Debit) - Finished Goods
        $rows = $rows->merge(
            \App\Models\ProductionReceiving::when($partyId, fn($q) =>
                $q->whereHas('production', fn($sub) => $sub->where('vendor_id', $partyId))
            )
            ->whereBetween('rec_date', [$from, $to])
            ->with(['details', 'production.vendor'])
            ->get()
            ->map(function($rec){
                $total = $rec->details->sum(fn($d) => $d->received_qty * $d->manufacturing_cost);

                return [
                    'date'    => $rec->rec_date,
                    'party'   => $rec->production->vendor->name ?? 'N/A',
                    'voucher' => "Production Receiving #{$rec->id}",
                    'debit'   => $this->formatAmount($total),
                    'credit'  => '0',
                    'balance' => 0,
                ];
            })
        );


        // 4️⃣ Sales (Credit)
        $rows = $rows->merge(
            SaleInvoice::when($partyId, fn($q) => $q->where('account_id', $partyId))
                ->whereBetween('date', [$from, $to])
                ->with(['items','account'])
                ->get()
                ->map(function($s){
                    $total = $s->items->sum(fn($i) => $i->quantity * $i->price);
                    return [
                        'date'    => $s->date,
                        'party'   => $s->customer->name ?? 'N/A',
                        'voucher' => "Sale #{$s->id}",
                        'debit'   => '0',
                        'credit'  => $this->formatAmount($total),
                        'balance' => 0,
                    ];
                })
        );

        // 5️⃣ Sale Returns (Debit)
        $rows = $rows->merge(
            \App\Models\SaleReturn::when($partyId, fn($q) => $q->where('account_id', $partyId))
                ->whereBetween('return_date', [$from, $to])
                ->with(['items','customer'])
                ->get()
                ->map(function($sr){
                    $total = $sr->items->sum(fn($i) => $i->quantity * $i->price);
                    return [
                        'date'    => $sr->date,
                        'party'   => $sr->customer->name ?? 'N/A',
                        'voucher' => "Sale Return #{$sr->id}",
                        'debit'   => $this->formatAmount($total),
                        'credit'  => '0',
                        'balance' => 0,
                    ];
                })
        );

        // 6️⃣ Payments / Receipts / Journals
        $rows = $rows->merge(
            Voucher::when($partyId, fn($q) =>
                $q->where(function($sub) use ($partyId) {
                    $sub->where('ac_dr_sid', $partyId)
                        ->orWhere('ac_cr_sid', $partyId);
                })
            )
            ->whereBetween('date', [$from, $to])
            ->whereDoesntHave('production') // 🚀 Exclude vouchers already linked to production
            ->with(['debitAccount', 'creditAccount'])
            ->get()
            ->map(function($v) use ($partyId) {
                $isDebitParty = $v->ac_dr_sid == $partyId;

                // ✅ Special case: Payments to Production Vendor should go to CREDIT
                if ($v->voucher_type === 'payment' && $isDebitParty) {
                    return [
                        'date'    => $v->date,
                        'party'   => $v->debitAccount->name ?? 'N/A',
                        'voucher' => "Payment #{$v->id}",
                        'debit'   => '0',
                        'credit'  => $this->formatAmount($v->amount), // 🚀 force CREDIT
                        'balance' => 0,
                    ];
                }

                return [
                    'date'    => $v->date,
                    'party'   => $isDebitParty
                                ? ($v->debitAccount->name ?? 'N/A')
                                : ($v->creditAccount->name ?? 'N/A'),
                    'voucher' => ucfirst($v->voucher_type)." #{$v->id}",
                    'debit'   => $isDebitParty ? $this->formatAmount($v->amount) : '0',
                    'credit'  => $isDebitParty ? '0' : $this->formatAmount($v->amount),
                    'balance' => 0,
                ];
            })

        );

        return collect($this->runningBalance(
            $rows->sortBy('date')->values()->toArray()
        ));
    }

    private function receivables($from,$to,$partyId=null)
    {
        return $this->partyLedger($from,$to,$partyId)
            ->filter(fn($r)=> floatval(str_replace(',','',$r['credit'])) > 0);
    }

    private function payables($from,$to,$partyId=null)
    {
        return $this->partyLedger($from,$to,$partyId)
            ->filter(fn($r)=> floatval(str_replace(',','',$r['debit'])) > 0);
    }

    private function cashBook($from, $to)
    {
        $cashAccounts = ChartOfAccounts::where('account_type','cash')->pluck('id');

        $rows = Voucher::whereBetween('date', [$from,$to])
            ->where(function($q) use ($cashAccounts) {
                $q->whereIn('ac_dr_sid',$cashAccounts)
                ->orWhereIn('ac_cr_sid',$cashAccounts);
            })
            ->with(['debitAccount','creditAccount'])
            ->get()
            ->map(function($v) use ($cashAccounts){
                $isDebit = $cashAccounts->contains($v->ac_dr_sid);

                return [
                    'date'        => $v->date,
                    'particulars' => "Voucher #{$v->id}",
                    'debit'       => $isDebit ? $this->formatAmount($v->amount) : '0',
                    'credit'      => !$isDebit ? $this->formatAmount($v->amount) : '0',
                    'balance'     => 0,
                ];
            })
            ->toArray();

        return $this->runningBalance($rows);
    }

    private function bankBook($from, $to)
    {
        $bankAccounts = ChartOfAccounts::where('account_type','bank')->pluck('id');

        $rows = Voucher::whereBetween('date', [$from,$to])
            ->where(function($q) use ($bankAccounts) {
                $q->whereIn('ac_dr_sid',$bankAccounts)
                ->orWhereIn('ac_cr_sid',$bankAccounts);
            })
            ->with(['debitAccount','creditAccount'])
            ->get()
            ->map(function($v) use ($bankAccounts){
                $isDebit = $bankAccounts->contains($v->ac_dr_sid);

                return [
                    'date'        => $v->date,
                    'bank'        => "Voucher #{$v->id}",
                    'debit'       => $isDebit ? $this->formatAmount($v->amount) : '0',
                    'credit'      => !$isDebit ? $this->formatAmount($v->amount) : '0',
                    'balance'     => 0,
                ];
            })
            ->toArray();

        return $this->runningBalance($rows);
    }

    private function journalBook($from,$to)
    {
        return Voucher::with(['debitAccount','creditAccount'])
            ->whereBetween('date', [$from,$to])
            ->get()
            ->map(fn($v)=>[
                'date'=>$v->date,'voucher'=>"Voucher #{$v->id}",
                'dr_account'=>$v->debitAccount->name ?? '','cr_account'=>$v->creditAccount->name ?? '',
                'amount'=>$this->formatAmount($v->debit ?: $v->credit),
            ]);
    }

    private function expenseAnalysis($from,$to)
    {
        return $this->trialBalance($from,$to)
            ->filter(fn($r)=> $r['account_type'] === 'expense')
            ->map(fn($r)=>[
                'expense_head'=>$r['account'],
                'amount'=>$r['debit'],
            ]);
    }

    private function cashFlow($from,$to)
    {
        return [
            ['activity'=>'Operating','inflows'=>'1000.00','outflows'=>'700.00','net flow'=>'300.00'],
            ['activity'=>'Investing','inflows'=>'500.00','outflows'=>'200.00','net flow'=>'300.00'],
            ['activity'=>'Financing','inflows'=>'0.00','outflows'=>'100.00','net flow'=>'-100.00'],
        ];
    }
}
