<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function saleReports(Request $request)
    {
        $tab = $request->get('tab', 'SR');

        $from = $request->get('from_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('to_date', Carbon::now()->toDateString());

        $customerId = $request->get('customer_id');

        $sales        = collect();
        $returns      = collect();
        $customerWise = collect();

        /* ================= SALES REGISTER ================= */
        if ($tab === 'SR') {
            $sales = SaleInvoice::with('account')
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    return (object)[
                        'date'     => $sale->date,
                        'invoice'  => $sale->invoice_no ?? $sale->id,
                        'customer' => $sale->account->name ?? '',
                        'total'    => $sale->total_amount ?? 0,
                    ];
                });
        }

        /* ================= SALES RETURN ================= */
        if ($tab === 'SRET') {
            $returns = SaleReturn::with('account')
                ->whereBetween('return_date', [$from, $to])
                ->get()
                ->map(function ($ret) {
                    return (object)[
                        'date'     => $ret->return_date,
                        'invoice'  => $ret->invoice_no ?? $ret->id,
                        'customer' => $ret->account->name ?? '',
                        'total'    => $ret->total_amount ?? 0,
                    ];
                });
        }

        /* ================= CUSTOMER WISE ================= */
        if ($tab === 'CW') {
            $query = SaleInvoice::with('account')
                ->whereBetween('date', [$from, $to]);

            if ($customerId) {
                $query->where('account_id', $customerId);
            }

            $customerWise = $query->get()
                ->groupBy('account_id')
                ->map(function ($rows) {
                    return (object)[
                        'customer' => $rows->first()->account->name ?? '',
                        'count'    => $rows->count(),
                        'total'    => $rows->sum('total_amount'),
                    ];
                })
                ->values();
        }

        $customers = ChartOfAccounts::where('account_type', 'customer')->get();

        return view('reports.sales_reports', compact(
            'tab',
            'from',
            'to',
            'sales',
            'returns',
            'customerWise',
            'customers',
            'customerId'
        ));
    }
}
