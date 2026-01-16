<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
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
            'receivables'      => $this->receivables($from, $to),
            'payables'         => $this->payables($from, $to),
            'cash_book'        => $this->cashBook($from, $to),
            'bank_book'        => $this->bankBook($from, $to),
            'expense_analysis' => $this->expenseAnalysis($from, $to),
            // Other reports can follow the same pattern...
        ];

        return view('reports.accounts_reports', compact('reports', 'from', 'to', 'chartOfAccounts'));
    }

    private function fmt($v) { return number_format($v, 2); }

    /* ================= LEDGER LOGIC (The Core Fix) ================= */
    private function getAccountBalance($accountId, $from, $to, $asOfDate = null)
    {
        $account = ChartOfAccounts::find($accountId);
        
        // 1. Get Initial Balance from the Chart of Accounts table
        // Adjust 'opening_balance' to whatever your column name is
        $initialBal = $account->opening_balance ?? 0; 

        // 2. Get Voucher movements
        $queryDr = Voucher::where('ac_dr_sid', $accountId);
        $queryCr = Voucher::where('ac_cr_sid', $accountId);

        if ($asOfDate) {
            $queryDr->where('date', '<=', $asOfDate);
            $queryCr->where('date', '<=', $asOfDate);
        } else {
            $queryDr->whereBetween('date', [$from, $to]);
            $queryCr->whereBetween('date', [$from, $to]);
        }

        $vDr = $queryDr->sum('amount');
        $vCr = $queryCr->sum('amount');

        // 3. Logic: For Assets/Customers, Initial Balance is usually a Debit.
        // For Liabilities/Vendors, Initial Balance is usually a Credit.
        if (in_array($account->account_type, ['asset', 'customer', 'cash', 'bank'])) {
            return [
                'debit'  => $initialBal + $vDr,
                'credit' => $vCr
            ];
        } else {
            return [
                'debit'  => $vDr,
                'credit' => $initialBal + $vCr
            ];
        }
    }

    /* ================= PARTY LEDGER (The Fix) ================= */
    private function partyLedger($from, $to, $accountId = null)
    {
        if (!$accountId) return collect();
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return collect();

        // 1. Calculate Opening Balance BEFORE the 'from' date
        // This includes Initial Balance from COA + Vouchers before 'from'
        $opData = $this->getAccountBalance($accountId, null, null, Carbon::parse($from)->subDay()->toDateString());
        
        $runningBal = 0;
        if (in_array($account->account_type, ['customer', 'asset'])) {
            $runningBal = $opData['debit'] - $opData['credit'];
        } else {
            // For Vendors/Payables, we usually show balance as Credit - Debit
            $runningBal = $opData['credit'] - $opData['debit'];
        }

        // 2. Create the "Opening Balance" row for the UI
        $rows = collect();
        $rows->push([
            $from,
            $account->name,
            "Opening Balance",
            0, 
            0, 
            $this->fmt($runningBal)
        ]);

        // 3. Get Vouchers for the selected period
        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(function ($q) use ($accountId) {
                $q->where('ac_dr_sid', $accountId)
                ->orWhere('ac_cr_sid', $accountId);
            })
            ->orderBy('date')
            ->get();

        // 4. Map movements and update running balance
        $movements = $vouchers->map(function ($v) use ($accountId, $account, &$runningBal) {
            $isDr = $v->ac_dr_sid == $accountId;
            $drAmount = $isDr ? $v->amount : 0;
            $crAmount = $isDr ? 0 : $v->amount;

            // Update running balance based on account type
            if (in_array($account->account_type, ['customer', 'asset'])) {
                $runningBal += ($drAmount - $crAmount);
            } else {
                $runningBal += ($crAmount - $drAmount);
            }

            return [
                $v->date,
                $account->name,
                "Voucher #{$v->id} - " . ($isDr ? "Debit" : "Credit"),
                $drAmount,
                $crAmount,
                $this->fmt($runningBal)
            ];
        });

        return $rows->concat($movements);
    }

    /* ================= RECEIVABLES ================= */
    private function receivables($from, $to)
    {
        // Strictly filter by 'customer' account type
        return ChartOfAccounts::where('account_type', 'customer')->get()
            ->map(function ($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to, $to);
                // Customers: Balance = Total Dr - Total Cr
                $total = $bal['debit'] - $bal['credit'];
                return [$a->name, $this->fmt($total)];
            })->filter(fn($r) => (float)str_replace(',', '', $r[1]) != 0);
    }

    /* ================= PAYABLES ================= */
    private function payables($from, $to)
    {
        // Strictly filter by 'vendor' account type. 
        // Your "Opening Stock" account should be type 'equity', so it will be ignored here.
        return ChartOfAccounts::where('account_type', 'vendor')->get()
            ->map(function ($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to, $to);
                // Vendors: Balance = Total Cr - Total Dr
                $total = $bal['credit'] - $bal['debit'];
                return [$a->name, $this->fmt($total)];
            })->filter(fn($r) => (float)str_replace(',', '', $r[1]) != 0);
    }

    /* ================= TRIAL BALANCE ================= */
    private function trialBalance($from, $to)
    {
        return ChartOfAccounts::all()->map(function ($a) use ($from, $to) {
            $bal = $this->getAccountBalance($a->id, $from, $to, $to);
            
            $debit = 0;
            $credit = 0;

            // Natural Balances
            if (in_array($a->account_type, ['asset', 'expense', 'customer', 'cash', 'bank'])) {
                $diff = $bal['debit'] - $bal['credit'];
                $debit = $diff > 0 ? $diff : 0;
                $credit = $diff < 0 ? abs($diff) : 0;
            } else {
                $diff = $bal['credit'] - $bal['debit'];
                $credit = $diff > 0 ? $diff : 0;
                $debit = $diff < 0 ? abs($diff) : 0;
            }

            return [$a->name, $a->account_type, $this->fmt($debit), $this->fmt($credit)];
        });
    }

    /* ================= CASH BOOK ================= */
    private function cashBook($from, $to)
    {
        $cashIds = ChartOfAccounts::where('account_type','cash')->pluck('id');
        return $this->bookHelper($cashIds, $from, $to);
    }

    /* ================= BANK BOOK ================= */
    private function bankBook($from, $to)
    {
        $bankIds = ChartOfAccounts::where('account_type','bank')->pluck('id');
        return $this->bookHelper($bankIds, $from, $to);
    }

    private function bookHelper($ids, $from, $to)
    {
        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->whereIn('ac_dr_sid', $ids)->orWhereIn('ac_cr_sid', $ids))
            ->orderBy('date')->get();

        $bal = 0;
        return $vouchers->map(function($v) use ($ids, &$bal) {
            $dr = in_array($v->ac_dr_sid, $ids->toArray()) ? $v->amount : 0;
            $cr = in_array($v->ac_cr_sid, $ids->toArray()) ? $v->amount : 0;
            $bal += ($dr - $cr);
            return [
                $v->date,
                ChartOfAccounts::find($v->ac_dr_sid)->name ?? '',
                ChartOfAccounts::find($v->ac_cr_sid)->name ?? '',
                $this->fmt($dr),
                $this->fmt($cr),
                $this->fmt($bal)
            ];
        });
    }
    
    // ... generalLedger and expenseAnalysis remain similar but should use the $this->fmt() helper.
}