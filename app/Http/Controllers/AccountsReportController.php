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
            'profit_loss'      => $this->profitLoss($from, $to),
            'balance_sheet'    => $this->balanceSheet($from, $to),
            'party_ledger'     => $this->partyLedger($from, $to, $accountId),
            'receivables'      => $this->receivables($from, $to),
            'payables'         => $this->payables($from, $to),
            'cash_book'        => $this->cashBook($from, $to),
            'bank_book'        => $this->bankBook($from, $to),
            'journal_book'     => $this->journalBook($from, $to), // New
            'expense_analysis' => $this->expenseAnalysis($from, $to),
            'cash_flow'        => $this->cashFlow($from, $to),    // New
        ];

        return view('reports.accounts_reports', compact('reports', 'from', 'to', 'chartOfAccounts'));
    }

    private function fmt($v) { return number_format($v, 2); }

    /* ================= REFINED LEDGER LOGIC ================= */
    private function getAccountBalance($accountId, $from, $to, $asOfDate = null)
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return ['debit' => 0, 'credit' => 0];

        // These columns in your DB represent the Opening Balances
        $openingDr = (float) $account->receivables; 
        $openingCr = (float) $account->payables;

        $targetDate = $asOfDate ?? $to;

        // Sum activity from the vouchers table
        $vDr = Voucher::where('ac_dr_sid', $accountId)
                    ->where('date', '<=', $targetDate)
                    ->sum('amount');

        $vCr = Voucher::where('ac_cr_sid', $accountId)
                    ->where('date', '<=', $targetDate)
                    ->sum('amount');

        // Return totals
        return [
            'debit'  => $openingDr + $vDr,
            'credit' => $openingCr + $vCr
        ];
    }

    /* ================= PROFIT & LOSS ================= */
    private function profitLoss($from, $to)
    {
        // 1. REVENUE
        $revenue = ChartOfAccounts::where('account_type', 'revenue')->get()
            ->map(function($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to);
                return [$a->name, $bal['credit'] - $bal['debit']];
            })->filter(fn($r) => $r[1] != 0);

        // 2. COGS
        $cogs = ChartOfAccounts::whereIn('account_type', ['cogs', 'cost_of_sales'])->get()
            ->map(function($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to);
                return [$a->name, $bal['debit'] - $bal['credit']];
            })->filter(fn($r) => $r[1] != 0);

        // 3. EXPENSES
        $expenses = ChartOfAccounts::where('account_type', 'expenses')->get()
            ->map(function($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to);
                return [$a->name, $bal['debit'] - $bal['credit']];
            })->filter(fn($r) => $r[1] != 0);

        $totalRev  = $revenue->sum(fn($r) => $r[1]);
        $totalCogs = $cogs->sum(fn($r) => $r[1]);
        $grossProfit = $totalRev - $totalCogs;
        $totalExp  = $expenses->sum(fn($r) => $r[1]);
        $netProfit = $grossProfit - $totalExp;

        // We return a flat collection so your existing Blade @foreach loop doesn't break
        $data = collect([['REVENUE', '']]);
        $data = $data->concat($revenue);
        $data->push(['Total Revenue', $totalRev]);

        $data->push(['LESS: COST OF GOODS SOLD', '']);
        $data = $data->concat($cogs);
        $data->push(['GROSS PROFIT', $grossProfit]);

        $data->push(['OPERATING EXPENSES', '']);
        $data = $data->concat($expenses);
        $data->push(['NET PROFIT/LOSS', $netProfit]);

        return $data;
    }

    /* ================= PARTY LEDGER ================= */
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
        return ChartOfAccounts::where('account_type', 'customer')->get()
            ->map(function ($a) use ($to) {
                $bal = $this->getAccountBalance($a->id, null, $to); // Pass null for $from
                $total = $bal['debit'] - $bal['credit'];
                return [$a->name, $this->fmt($total)];
            })->filter(fn($r) => (float)str_replace(',', '', $r[1]) != 0);
    }

    /* ================= RECEIVABLES ================= */

    private function payables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'vendor')->get()
            ->map(function ($a) use ($to) {
                $bal = $this->getAccountBalance($a->id, null, $to);
                // Vendors are Credit-natured: Credit - Debit = Amount Owed
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

    /* ================= GENERAL LEDGER ================= */
    private function generalLedger($accountId, $from, $to)
    {
        if (!$accountId) return collect();

        $account = ChartOfAccounts::find($accountId);
        if (!$account) return collect();

        // 1. Calculate Opening Balance (COA Initial + Vouchers before 'from' date)
        $opData = $this->getAccountBalance($accountId, null, null, Carbon::parse($from)->subDay()->toDateString());
        
        // Determine starting balance based on account nature
        $isDebitNature = in_array($account->account_type, ['asset', 'expense', 'customer', 'cash', 'bank']);
        $runningBal = $isDebitNature ? ($opData['debit'] - $opData['credit']) : ($opData['credit'] - $opData['debit']);

        $rows = collect();
        
        // Add Opening Balance Row
        $rows->push([
            $from,
            $account->name,
            "Opening Balance",
            $this->fmt($opData['debit']),
            $this->fmt($opData['credit']),
            $this->fmt($runningBal)
        ]);

        // 2. Get Voucher movements for the period
        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->where('ac_dr_sid', $accountId)->orWhere('ac_cr_sid', $accountId))
            ->orderBy('date')
            ->get();

        foreach ($vouchers as $v) {
            $dr = ($v->ac_dr_sid == $accountId) ? $v->amount : 0;
            $cr = ($v->ac_cr_sid == $accountId) ? $v->amount : 0;
            
            if ($isDebitNature) {
                $runningBal += ($dr - $cr);
            } else {
                $runningBal += ($cr - $dr);
            }

            $rows->push([
                $v->date,
                $account->name,
                "Voucher #{$v->id}",
                $this->fmt($dr),
                $this->fmt($cr),
                $this->fmt($runningBal)
            ]);
        }

        return $rows;
    }

    /* ================= EXPENSE ANALYSIS ================= */
    private function expenseAnalysis($from, $to)
    {
        // Changed 'expense' to 'expenses' to match your HTML select
        return ChartOfAccounts::where('account_type', 'expenses')
            ->get()
            ->map(function ($a) use ($from, $to) {
                $bal = $this->getAccountBalance($a->id, $from, $to);
                $total = $bal['debit'] - $bal['credit'];
                return [$a->name, $this->fmt($total)];
            })->filter(fn($r) => (float)str_replace(',', '', $r[1]) != 0);
    }

    /* ================= JOURNAL / DAY BOOK ================= */
    private function journalBook($from, $to)
    {
        return Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(function ($v) {
                return [
                    $v->date,
                    $v->debitAccount->name ?? 'N/A',
                    $v->creditAccount->name ?? 'N/A',
                    $this->fmt($v->amount)
                ];
            });
    }

    /* ================= CASH FLOW (Simplified) ================= */
    private function cashFlow($from, $to)
    {
        // Cash Flow = Cash/Bank Inflows - Outflows
        $cashBankIds = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->pluck('id');

        $inflow = Voucher::whereIn('ac_dr_sid', $cashBankIds)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        $outflow = Voucher::whereIn('ac_cr_sid', $cashBankIds)
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return [
            ['Total Cash Inflow (Receipts)', $this->fmt($inflow)],
            ['Total Cash Outflow (Payments)', $this->fmt($outflow)],
            ['Net Increase/Decrease in Cash', $this->fmt($inflow - $outflow)]
        ];
    }

    /* ================= BALANCE SHEET ================= */
    private function balanceSheet($from, $to)
    {
        $trial = $this->trialBalance($from, $to);
        $assets = collect();
        $liabilities = collect();

        foreach ($trial as $r) {
            $type = $r[1];
            $debit = (float)str_replace(',', '', $r[2]);
            $credit = (float)str_replace(',', '', $r[3]);
            
            if (in_array($type, ['asset', 'customer', 'cash', 'bank'])) {
                $val = $debit - $credit;
                if ($val != 0) $assets->push([$r[0], $this->fmt($val)]);
            } elseif (in_array($type, ['liability', 'vendor', 'equity'])) {
                $val = $credit - $debit;
                if ($val != 0) $liabilities->push([$r[0], $this->fmt($val)]);
            }
        }

        $max = max($assets->count(), $liabilities->count());
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $assets[$i][0] ?? '', $assets[$i][1] ?? '',
                $liabilities[$i][0] ?? '', $liabilities[$i][1] ?? ''
            ];
        }
        return $rows;
    }
}