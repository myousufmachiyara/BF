<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseBiltyController extends Controller
{
    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        return view('purchase-bilty.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        $purchaseInvoices = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_no']);

        return view('purchase-bilty.create', compact('products', 'vendors','units', 'purchaseInvoices'));
    }
}
