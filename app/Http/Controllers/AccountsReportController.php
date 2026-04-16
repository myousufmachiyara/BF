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
        $from = $request->from_date ?? '2026-01-01'; // or dynamic financial year start
        $to   = $request->to_date   ?? Carbon::now()->toDateString();
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

        return view('reports.accounts_reports', compact('reports', 'from', 'to', 'chartOfAccounts'));
    }

    private function fmt($v) { return number_format((float)$v, 2); }

    /**
     * Core balance calculator.
     * Reads opening balance from COA columns + sums non-deleted vouchers up to $asOfDate.
     */
    private function getAccountBalance($accountId, $asOfDate)
    {
        $account = ChartOfAccounts::find($accountId);
        if (!$account) return ['debit' => 0, 'credit' => 0];

        $openingDr = (float) $account->receivables;
        $openingCr = (float) $account->payables;

        $vDr = Voucher::where('ac_dr_sid', $accountId)
                    ->where('date', '<=', $asOfDate)
                    ->sum('amount');

        $vCr = Voucher::where('ac_cr_sid', $accountId)
                    ->where('date', '<=', $asOfDate)
                    ->sum('amount');

        return [
            'debit'  => $openingDr + (float)$vDr,
            'credit' => $openingCr + (float)$vCr,
        ];
    }

    private function isDebitNature($accountType)
    {
        return in_array($accountType, ['asset', 'customer', 'cash', 'bank', 'expense', 'expenses', 'cogs']);
    }

    /* ================= GENERAL LEDGER ================= */
    private function generalLedger($accountId, $from, $to)
    {
        if (!$accountId) return collect();

        $account = ChartOfAccounts::find($accountId);
        if (!$account) return collect();

        $dayBefore  = Carbon::parse($from)->subDay()->format('Y-m-d');
        $isDebitNat = $this->isDebitNature($account->account_type);

        $opBal      = $this->getAccountBalance($accountId, $dayBefore);
        $runningBal = $isDebitNat
            ? ($opBal['debit'] - $opBal['credit'])
            : ($opBal['credit'] - $opBal['debit']);

        $rows = collect();
        $rows->push([
            $from,
            $account->name,
            'Opening Balance',
            '',
            '',
            $this->fmt($runningBal),
        ]);

        // FIX #2: Explicitly wrap the orWhere in a grouped closure
        // This prevents any future scope injection from breaking the OR logic
        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(function ($q) use ($accountId) {
                $q->where('ac_dr_sid', $accountId)
                ->orWhere('ac_cr_sid', $accountId);
            })
            ->orderBy('date')
            ->orderBy('id')       // secondary sort for stable ordering same-day
            ->get();

        foreach ($vouchers as $v) {
            $dr = ($v->ac_dr_sid == $accountId) ? (float)$v->amount : 0;
            $cr = ($v->ac_cr_sid == $accountId) ? (float)$v->amount : 0;

            $runningBal += $isDebitNat ? ($dr - $cr) : ($cr - $dr);

            $rows->push([
                $v->date,
                $account->name,
                "Voucher #{$v->id}" . ($v->remarks ? " — {$v->remarks}" : ''),
                $this->fmt($dr),
                $this->fmt($cr),
                $this->fmt($runningBal),
            ]);
        }

        return $rows;
    }

    /* ================= PARTY LEDGER ================= */
    private function partyLedger($from, $to, $accountId = null)
    {
        if (!$accountId) return collect();

        $account = ChartOfAccounts::find($accountId);
        if (!$account) return collect();

        $dayBefore  = Carbon::parse($from)->subDay()->format('Y-m-d');
        $isDebitNat = $this->isDebitNature($account->account_type);

        $opBal = $this->getAccountBalance($accountId, $dayBefore);
        $runningBal = $isDebitNat
            ? ($opBal['debit'] - $opBal['credit'])
            : ($opBal['credit'] - $opBal['debit']);

        $rows = collect();
        $rows->push([$from, $account->name, 'Opening Balance', 0, 0, $this->fmt($runningBal)]);

        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->where('ac_dr_sid', $accountId)
                                ->orWhere('ac_cr_sid', $accountId))
            ->orderBy('date')
            ->get();

        foreach ($vouchers as $v) {
            $dr = ($v->ac_dr_sid == $accountId) ? (float)$v->amount : 0;
            $cr = ($v->ac_cr_sid == $accountId) ? (float)$v->amount : 0;

            $runningBal += $isDebitNat ? ($dr - $cr) : ($cr - $dr);

            $rows->push([
                $v->date,
                $account->name,
                "Voucher #{$v->id} — " . ($dr > 0 ? 'Debit' : 'Credit'),
                $dr,
                $cr,
                $this->fmt($runningBal),
            ]);
        }

        return $rows;
    }

    /* ================= RECEIVABLES ================= */
    private function receivables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'customer')
            ->get()
            ->map(function ($a) use ($to) {
                $bal   = $this->getAccountBalance($a->id, $to);
                $total = $bal['debit'] - $bal['credit'];
                return [$a->name, $this->fmt($total), $total];
            })
            ->filter(fn($r) => $r[2] > 0)          // only show positive (amounts owed TO you)
            ->map(fn($r)    => [$r[0], $r[1]]);
    }

    /* ================= PAYABLES ================= */
    private function payables($from, $to)
    {
        return ChartOfAccounts::where('account_type', 'vendor')
            ->get()
            ->map(function ($a) use ($to) {
                $bal   = $this->getAccountBalance($a->id, $to);
                $total = $bal['credit'] - $bal['debit'];
                return [$a->name, $this->fmt($total), $total];
            })
            ->filter(fn($r) => $r[2] > 0)
            ->map(fn($r)    => [$r[0], $r[1]]);
    }

    /* ================= TRIAL BALANCE ================= */
    private function trialBalance($from, $to)
    {
        return ChartOfAccounts::all()->map(function ($a) use ($to) {
            $bal = $this->getAccountBalance($a->id, $to);

            if ($this->isDebitNature($a->account_type)) {
                $diff   = $bal['debit'] - $bal['credit'];
                $debit  = $diff > 0 ? $diff : 0;
                $credit = $diff < 0 ? abs($diff) : 0;
            } else {
                $diff   = $bal['credit'] - $bal['debit'];
                $credit = $diff > 0 ? $diff : 0;
                $debit  = $diff < 0 ? abs($diff) : 0;
            }

            return [$a->name, $a->account_type, $this->fmt($debit), $this->fmt($credit)];
        });
    }

    /* ================= PROFIT & LOSS ================= */
    private function profitLoss($from, $to)
    {
        $mapAccounts = function ($accounts, $isDebit) use ($from, $to) {
            return collect($accounts)->map(function ($a) use ($from, $to, $isDebit) {
                // For P&L, only activity within the period (not opening balances)
                $vDr = (float) Voucher::where('ac_dr_sid', $a->id)
                                      ->whereBetween('date', [$from, $to])
                                      ->sum('amount');
                $vCr = (float) Voucher::where('ac_cr_sid', $a->id)
                                      ->whereBetween('date', [$from, $to])
                                      ->sum('amount');
                $val = $isDebit ? ($vDr - $vCr) : ($vCr - $vDr);
                return [$a->name, $val];
            })->filter(fn($r) => $r[1] != 0);
        };

        $revenue  = $mapAccounts(ChartOfAccounts::where('account_type', 'revenue')->get(), false);
        $cogs     = $mapAccounts(ChartOfAccounts::whereIn('account_type', ['cogs', 'cost_of_sales'])->get(), true);
        $expenses = $mapAccounts(ChartOfAccounts::whereIn('account_type', ['expenses', 'expense'])->get(), true);

        $totalRev    = $revenue->sum(fn($r) => $r[1]);
        $totalCogs   = $cogs->sum(fn($r) => $r[1]);
        $grossProfit = $totalRev - $totalCogs;
        $totalExp    = $expenses->sum(fn($r) => $r[1]);
        $netProfit   = $grossProfit - $totalExp;

        return collect([['REVENUE', '']])
            ->concat($revenue)
            ->push(['Total Revenue', $totalRev])
            ->push(['LESS: COST OF GOODS SOLD', ''])
            ->concat($cogs)
            ->push(['GROSS PROFIT', $grossProfit])
            ->push(['OPERATING EXPENSES', ''])
            ->concat($expenses)
            ->push(['NET PROFIT/LOSS', $netProfit]);
    }

    /* ================= CASH BOOK ================= */
    private function cashBook($from, $to)
    {
        return $this->bookHelper(
            ChartOfAccounts::where('account_type', 'cash')->pluck('id'),
            $from, $to
        );
    }

    /* ================= BANK BOOK ================= */
    private function bankBook($from, $to)
    {
        return $this->bookHelper(
            ChartOfAccounts::where('account_type', 'bank')->pluck('id'),
            $from, $to
        );
    }

    private function bookHelper($ids, $from, $to)
    {
        $idArray  = $ids->toArray();
        $vouchers = Voucher::whereBetween('date', [$from, $to])
            ->where(function ($q) use ($idArray) {
                $q->whereIn('ac_dr_sid', $idArray)
                ->orWhereIn('ac_cr_sid', $idArray);
            })
            ->orderBy('date')
            ->orderBy('id')
            ->with(['debitAccount', 'creditAccount'])  // eager load — kills N+1
            ->get();

        $bal = 0;
        return $vouchers->map(function ($v) use ($idArray, &$bal) {
            $dr  = in_array($v->ac_dr_sid, $idArray) ? (float)$v->amount : 0;
            $cr  = in_array($v->ac_cr_sid, $idArray) ? (float)$v->amount : 0;
            $bal += ($dr - $cr);

            return [
                $v->date,
                $v->debitAccount->name  ?? 'N/A',
                $v->creditAccount->name ?? 'N/A',
                $this->fmt($dr),
                $this->fmt($cr),
                $this->fmt($bal),
            ];
        });
    }

    /* ================= JOURNAL / DAY BOOK ================= */
    private function journalBook($from, $to)
    {
        return Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(fn($v) => [
                $v->date,
                $v->debitAccount->name  ?? 'N/A',
                $v->creditAccount->name ?? 'N/A',
                $this->fmt($v->amount),
            ]);
    }

    /* ================= EXPENSE ANALYSIS ================= */
    private function expenseAnalysis($from, $to)
    {
        return ChartOfAccounts::whereIn('account_type', ['expenses', 'expense'])
            ->get()
            ->map(function ($a) use ($from, $to) {
                $vDr   = (float) Voucher::where('ac_dr_sid', $a->id)->whereBetween('date', [$from, $to])->sum('amount');
                $vCr   = (float) Voucher::where('ac_cr_sid', $a->id)->whereBetween('date', [$from, $to])->sum('amount');
                $total = $vDr - $vCr;
                return [$a->name, $this->fmt($total), $total];
            })
            ->filter(fn($r) => $r[2] != 0)
            ->map(fn($r) => [$r[0], $r[1]]);
    }

    /* ================= CASH FLOW ================= */
    private function cashFlow($from, $to)
    {
        $cashBankIds = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->pluck('id');

        $inflow  = (float) Voucher::whereIn('ac_dr_sid', $cashBankIds)->whereBetween('date', [$from, $to])->sum('amount');
        $outflow = (float) Voucher::whereIn('ac_cr_sid', $cashBankIds)->whereBetween('date', [$from, $to])->sum('amount');

        return [
            ['Total Cash Inflow (Receipts)',      $this->fmt($inflow)],
            ['Total Cash Outflow (Payments)',     $this->fmt($outflow)],
            ['Net Increase/Decrease in Cash',    $this->fmt($inflow - $outflow)],
        ];
    }

    /* ================= BALANCE SHEET ================= */
    private function balanceSheet($from, $to)
    {
        $assets      = collect();
        $liabilities = collect();

        foreach ($this->trialBalance($from, $to) as $r) {
            $type   = $r[1];
            $debit  = (float) str_replace(',', '', $r[2]);
            $credit = (float) str_replace(',', '', $r[3]);

            if (in_array($type, ['asset', 'customer', 'cash', 'bank'])) {
                $val = $debit - $credit;
                if ($val != 0) $assets->push([$r[0], $this->fmt($val)]);
            } elseif (in_array($type, ['liability', 'vendor', 'equity'])) {
                $val = $credit - $debit;
                if ($val != 0) $liabilities->push([$r[0], $this->fmt($val)]);
            }
        }

        $max  = max($assets->count(), $liabilities->count());
        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                $assets[$i][0]      ?? '',
                $assets[$i][1]      ?? '',
                $liabilities[$i][0] ?? '',
                $liabilities[$i][1] ?? '',
            ];
        }
        return $rows;
    }
}