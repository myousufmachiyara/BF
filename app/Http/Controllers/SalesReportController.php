<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleReturn;
use Carbon\Carbon;
use App\Models\ChartOfAccounts;

class SalesReportController extends Controller
{
    public function saleReports(Request $request)
    {
        $tab = $request->get('tab', 'SR'); // default Sales Register

        // Default date range (last 30 days)
        $from = $request->get('from_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));
        $customerId = $request->get('customer_id'); // new filter

        $sales        = collect();
        $returns      = collect();
        $customerWise = collect();

        // --- SALES REGISTER (SR) ---
        if ($tab === 'SR') {
            $sales = SaleInvoice::with('account')
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    return (object)[
                        'date'      => $sale->date,
                        'invoice'   => $sale->id,
                        'customer'  => $sale->account->name ?? '',
                        'total'     => $sale->total_amount ?? 0,
                    ];
                });
        }

        // --- SALES RETURN (SRET) ---
        if ($tab === 'SRET') {
            $returns = SaleReturn::with('account')
                ->whereBetween('return_date', [$from, $to])
                ->get()
                ->map(function ($ret) {
                    return (object)[
                        'date'      => $ret->date,
                        'invoice'   => $ret->id,
                        'customer'  => $ret->account->name ?? '',
                        'total'     => $ret->total_amount ?? 0,
                    ];
                });
        }

        // --- CUSTOMER WISE (CW) ---
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
                        'total'    => $rows->sum('total_amount'),
                        'count'    => $rows->count(),
                    ];
                })
                ->values();
        }

        // Get list of customers for dropdown
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
