<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleItemCustomization;
use App\Models\PurchaseInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{
    /* ---------------------------------------------------------------
     | Shared stock calculation used by create() and edit()
     | Accounts for: purchases, purchase returns, sales,
     |               sale customizations, sale returns
     --------------------------------------------------------------- */
    private function getProductsWithStock(): \Illuminate\Support\Collection
    {
        return Product::orderBy('name', 'asc')
            ->withSum([
                'purchaseInvoices as total_purchased' => fn($q) =>
                    $q->whereHas('invoice', fn($q) => $q->whereNull('deleted_at'))
            ], 'quantity')
            ->withSum([
                'saleInvoices as total_sold' => fn($q) =>
                    $q->whereHas('invoice', fn($q) => $q->whereNull('deleted_at'))
            ], 'quantity')
            ->withCount([
                'saleInvoiceParts as total_customized' => fn($q) =>
                    $q->whereHas('saleInvoice', fn($q) => $q->whereNull('deleted_at'))
            ])
            ->withSum([
                'purchaseReturns as total_purchase_returned' => fn($q) =>
                    $q->whereHas('purchaseReturn', fn($q) => $q->whereNull('deleted_at'))
            ], 'quantity')
            ->withSum([
                'saleReturns as total_sale_returned' => fn($q) =>
                    $q->whereHas('saleReturn', fn($q) => $q->whereNull('deleted_at'))
            ], 'qty')
            ->get()
            ->map(function ($product) {
                $in  = ($product->total_purchased       ?? 0)
                     + ($product->total_sale_returned   ?? 0);
                $out = ($product->total_sold            ?? 0)
                     + ($product->total_customized      ?? 0)
                     + ($product->total_purchase_returned ?? 0);
                $product->real_time_stock = $in - $out;
                return $product;
            });
    }

    /* ---------------------------------------------------------------
     | Shared customer + payment account queries (role-aware)
     --------------------------------------------------------------- */
    private function getCustomers()
    {
        $q = ChartOfAccounts::where('account_type', 'customer');
        if (!auth()->user()->hasRole('superadmin')) {
            $q->where('visibility', 'public');
        }
        return $q->orderBy('name')->get();
    }

    private function getPaymentAccounts()
    {
        $q = ChartOfAccounts::whereIn('account_type', ['cash', 'bank']);
        if (!auth()->user()->hasRole('superadmin')) {
            $q->where('visibility', 'public');
        }
        return $q->orderBy('name')->get();
    }

    /* ================= INDEX ================= */
    // In SaleInvoiceController index()
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'account', 'receiptVouchers')->latest()->get();
        return view('sales.index', compact('invoices'));
    }   

    /* ================= CREATE ================= */
    public function create()
    {
        $products        = $this->getProductsWithStock();
        $customers       = $this->getCustomers();
        $paymentAccounts = $this->getPaymentAccounts();

        return view('sales.create', compact('products', 'customers', 'paymentAccounts'));
    }

    /* ================= STORE ================= */
public function store(Request $request)
{
    Log::info('[SaleInvoice][Store] Request received', [
        'user_id' => Auth::id(),
        'input'   => $request->except(['_token']),
    ]);

    $validated = $request->validate([
        'date'                     => 'required|date',
        'account_id'               => 'required|exists:chart_of_accounts,id',
        'type'                     => 'required|in:cash,credit',
        'discount'                 => 'nullable|numeric|min:0',
        'remarks'                  => 'nullable|string',
        'items'                    => 'required|array|min:1',
        'items.*.product_id'       => 'required|exists:products,id',
        'items.*.sale_price'       => 'required|numeric|min:0',
        'items.*.quantity'         => 'required|numeric|min:1',
        'items.*.customizations'   => 'nullable|array',
        'items.*.customizations.*' => 'exists:products,id',
        'payment_account_id'       => 'nullable|exists:chart_of_accounts,id',
        'amount_received'          => 'nullable|numeric|min:0',
    ]);

    Log::info('[SaleInvoice][Store] Validation passed', [
        'items_count' => count($validated['items']),
        'items'       => $validated['items'],
    ]);

    DB::beginTransaction();
    try {
        // ── Invoice number ──────────────────────────────────────────
        $lastInvoice = SaleInvoice::withTrashed()->orderBy('id', 'desc')->first();
        $invoiceNo   = str_pad($lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1, 6, '0', STR_PAD_LEFT);
        Log::info('[SaleInvoice][Store] Invoice number generated', ['invoice_no' => $invoiceNo]);

        // ── Create header ───────────────────────────────────────────
        $invoice = SaleInvoice::create([
            'invoice_no' => $invoiceNo,
            'date'       => $validated['date'],
            'account_id' => $validated['account_id'],
            'type'       => $validated['type'],
            'discount'   => $validated['discount'] ?? 0,
            'remarks'    => $validated['remarks'] ?? null,
            'created_by' => Auth::id(),
        ]);
        Log::info('[SaleInvoice][Store] Invoice header created', ['invoice_id' => $invoice->id]);

        // ── Create items ────────────────────────────────────────────
        $totalBill = 0;

        foreach ($validated['items'] as $idx => $item) {
            Log::info("[SaleInvoice][Store] Processing item #{$idx}", ['item' => $item]);

            try {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                    // Remove 'discount' => 0 if that column doesn't exist in your table
                ]);
                Log::info("[SaleInvoice][Store] Item #{$idx} created", ['item_id' => $invoiceItem->id]);
            } catch (\Throwable $itemEx) {
                Log::error("[SaleInvoice][Store] Failed to create item #{$idx}", [
                    'item'  => $item,
                    'error' => $itemEx->getMessage(),
                    'line'  => $itemEx->getLine(),
                    'file'  => $itemEx->getFile(),
                ]);
                throw $itemEx; // re-throw to trigger rollback
            }

            $totalBill += $item['sale_price'] * $item['quantity'];

            // ── Customizations ──────────────────────────────────────
            $customizations = $item['customizations'] ?? [];
            Log::info("[SaleInvoice][Store] Item #{$idx} customizations", ['customizations' => $customizations]);

            foreach ($customizations as $cidx => $customItemId) {
                try {
                    SaleItemCustomization::create([
                        'sale_invoice_id'       => $invoice->id,
                        'sale_invoice_items_id' => $invoiceItem->id,
                        'item_id'               => $customItemId,
                    ]);
                    Log::info("[SaleInvoice][Store] Customization #{$cidx} created for item #{$idx}", [
                        'custom_item_id' => $customItemId,
                    ]);
                } catch (\Throwable $custEx) {
                    Log::error("[SaleInvoice][Store] Failed to create customization #{$cidx} for item #{$idx}", [
                        'custom_item_id' => $customItemId,
                        'error'          => $custEx->getMessage(),
                        'line'           => $custEx->getLine(),
                        'file'           => $custEx->getFile(),
                    ]);
                    throw $custEx;
                }
            }
        }

        $netTotal = $totalBill - ($validated['discount'] ?? 0);
        Log::info('[SaleInvoice][Store] Items loop complete', [
            'total_bill' => $totalBill,
            'net_total'  => $netTotal,
        ]);

        // ── Sales Revenue voucher ───────────────────────────────────
        $salesAccount = ChartOfAccounts::where('name', 'Sales Revenue')
            ->orWhere('account_type', 'revenue')
            ->first();

        Log::info('[SaleInvoice][Store] Sales account lookup', [
            'found'      => $salesAccount ? true : false,
            'account_id' => $salesAccount?->id,
            'name'       => $salesAccount?->name,
        ]);

        if (!$salesAccount) {
            throw new \Exception('Sales Revenue account not found in COA. Check account_type = revenue or name = "Sales Revenue".');
        }

        try {
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id,
                'amount'       => $netTotal,
                'narration'    => "Sales Invoice #{$invoiceNo}",
                'remarks'      => "Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);
            Log::info('[SaleInvoice][Store] Sales revenue voucher created');
        } catch (\Throwable $vEx) {
            Log::error('[SaleInvoice][Store] Failed to create sales revenue voucher', [
                'error' => $vEx->getMessage(),
                'line'  => $vEx->getLine(),
                'file'  => $vEx->getFile(),
            ]);
            throw $vEx;
        }

        // ── Payment receipt voucher ─────────────────────────────────
        if ($request->filled('payment_account_id') && $request->amount_received > 0) {
            Log::info('[SaleInvoice][Store] Creating payment receipt voucher', [
                'payment_account_id' => $validated['payment_account_id'],
                'amount_received'    => $validated['amount_received'],
            ]);
            try {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'narration'    => "Payment received for Invoice #{$invoiceNo}",
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
                Log::info('[SaleInvoice][Store] Payment receipt voucher created');
            } catch (\Throwable $pvEx) {
                Log::error('[SaleInvoice][Store] Failed to create payment voucher', [
                    'error' => $pvEx->getMessage(),
                    'line'  => $pvEx->getLine(),
                    'file'  => $pvEx->getFile(),
                ]);
                throw $pvEx;
            }
        } else {
            Log::info('[SaleInvoice][Store] No payment receipt — skipped', [
                'payment_account_id_filled' => $request->filled('payment_account_id'),
                'amount_received'           => $request->amount_received,
            ]);
        }

        // ── COGS voucher ────────────────────────────────────────────
        Log::info('[SaleInvoice][Store] Recording COGS voucher');
        $this->recordCogsVoucher($validated['items'], $validated['date'], $invoiceNo, $invoice->id);

        DB::commit();
        Log::info('[SaleInvoice][Store] Transaction committed successfully', ['invoice_id' => $invoice->id]);

        return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice saved successfully.');

    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('[SaleInvoice][Store] TRANSACTION ROLLED BACK', [
            'error'   => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
            'trace'   => $e->getTraceAsString(),
        ]);
        return back()->withInput()->with('error', 'Error saving invoice: ' . $e->getMessage());
    }
}

    /* ================= EDIT ================= */
    public function edit($id)
    {
        $invoice  = SaleInvoice::with(['items.customizations', 'account'])->findOrFail($id);
        $products = $this->getProductsWithStock();

        // Add back this invoice's own quantities so the edit form
        // doesn't falsely flag existing rows as over-stock
        $invoiceQtyMap = $invoice->items->keyBy('product_id');
        $products = $products->map(function ($product) use ($invoiceQtyMap) {
            if ($invoiceQtyMap->has($product->id)) {
                $product->real_time_stock += $invoiceQtyMap[$product->id]->quantity;
            }
            return $product;
        });

        // Amount already received across all payment vouchers for this invoice
        $amountReceived = Voucher::where('reference', $invoice->id)
            ->where('voucher_type', 'receipt')
            ->sum('amount');

        $customers       = $this->getCustomers();
        $paymentAccounts = $this->getPaymentAccounts();

        return view('sales.edit', compact(
            'invoice', 'products', 'customers', 'paymentAccounts', 'amountReceived'
        ));
    }

    /* ================= UPDATE ================= */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'                     => 'required|date',
            'account_id'               => 'required|exists:chart_of_accounts,id',
            'type'                     => 'required|in:cash,credit',
            'discount'                 => 'nullable|numeric|min:0',
            'remarks'                  => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.sale_price'       => 'required|numeric|min:0',
            'items.*.quantity'         => 'required|numeric|min:0.01',
            'items.*.customizations'   => 'nullable|array',
            'items.*.customizations.*' => 'exists:products,id',
            'payment_account_id'       => 'nullable|exists:chart_of_accounts,id',
            'amount_received'          => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $invoice   = SaleInvoice::findOrFail($id);
            $invoiceNo = $invoice->invoice_no;

            $invoice->update([
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $validated['remarks'],
            ]);

            // Clear items and customizations, re-insert fresh
            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();

            $totalBill = 0;
            $totalCost = 0;

            $salesAccount     = ChartOfAccounts::where('account_type', 'revenue')->first();
            $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();
            $cogsAccount      = ChartOfAccounts::where('account_type', 'cogs')->first();

            foreach ($validated['items'] as $item) {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                ]);

                $totalBill += $item['sale_price'] * $item['quantity'];

                $itemCost = $this->calcLandedCost($item['product_id']);

                foreach ($item['customizations'] ?? [] as $customId) {
                    SaleItemCustomization::create([
                        'sale_invoice_id'       => $invoice->id,
                        'sale_invoice_items_id' => $invoiceItem->id,
                        'item_id'               => $customId,
                    ]);
                    $itemCost += $this->calcLandedCost($customId);
                }

                $totalCost += $itemCost * $item['quantity'];
            }

            $netTotal = $totalBill - ($validated['discount'] ?? 0);
            $invoice->update(['net_amount' => $netTotal]);

            // Delete journal/cogs vouchers for this invoice, keep old receipts
            Voucher::where('reference', $invoice->id)
                ->where('voucher_type', 'journal')
                ->delete();

            // Re-create sales revenue voucher
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id ?? null,
                'amount'       => $netTotal,
                'narration'    => "Sales Invoice #{$invoiceNo}",
                'remarks'      => "Updated: Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);

            // Re-create COGS voucher
            if ($inventoryAccount && $cogsAccount && $totalCost > 0) {
                Voucher::create([
                    'voucher_type' => 'journal',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $cogsAccount->id,
                    'ac_cr_sid'    => $inventoryAccount->id,
                    'amount'       => $totalCost,
                    'narration'    => "COGS for Invoice #{$invoiceNo}",
                    'remarks'      => "Updated: COGS for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // Add new payment receipt if provided
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'narration'    => "Payment for Invoice #{$invoiceNo}",
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            DB::commit();

            foreach ($validated['items'] as $item) {
                \Cache::forget("landed_cost_prod_{$item['product_id']}");
            }

            return redirect()->route('sale_invoices.index')->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Update failed: ' . $e->getMessage());
            return back()->with('error', 'Update failed: ' . $e->getMessage())->withInput();
        }
    }

    /* ================= DESTROY ================= */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $invoice = SaleInvoice::findOrFail($id);

            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();
            Voucher::where('reference', $invoice->id)->delete();
            $invoice->delete();

            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Invoice deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Delete failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete invoice: ' . $e->getMessage());
        }
    }

    /* ================= SHOW ================= */
    public function show($id)
    {
        $invoice = SaleInvoice::with('items.product', 'items.variation', 'account')->findOrFail($id);
        return response()->json($invoice);
    }

    /* ================= PRINT ================= */
    public function print($id)
    {
        $invoice = SaleInvoice::with(['account', 'items.product'])->findOrFail($id);

        $amountReceived = Voucher::where('reference', $invoice->id)
            ->where('voucher_type', 'receipt')
            ->sum('amount');

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Bilwani Furnitures');
        $pdf->SetTitle('SALE-' . $invoice->invoice_no);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $logoPath = public_path('assets/img/bf_logo.jpg');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 12, 8, 40);
        }

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetXY(120, 12);
        $pdf->Cell(80, 8, 'Sale Invoice', 0, 1, 'R');
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);

        $infoHtml = '
        <table cellpadding="3" cellspacing="0" width="100%">
            <tr>
                <td width="40%">
                    <table border="1" cellpadding="4" cellspacing="0" style="font-size:10px;">
                        <tr><td width="30%"><b>Customer</b></td><td width="70%">' . ($invoice->account->name ?? '-') . '</td></tr>
                        <tr><td width="30%"><b>Invoice No</b></td><td width="70%">' . $invoice->invoice_no . '</td></tr>
                        <tr><td width="30%"><b>Date</b></td><td width="70%">' . \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') . '</td></tr>
                    </table>
                </td>
                <td width="60%"></td>
            </tr>
        </table>';
        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold; background-color:#f5f5f5;">
                <th width="6%">#</th>
                <th width="54%">Item</th>
                <th width="10%">Qty</th>
                <th width="15%">Price</th>
                <th width="15%">Total</th>
            </tr>';

        $count = $totalQty = $subTotal = 0;
        foreach ($invoice->items as $item) {
            $count++;
            $lineTotal  = $item->sale_price * $item->quantity;
            $totalQty  += $item->quantity;
            $subTotal  += $lineTotal;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td style="text-align:left">' . ($item->product->name ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . '</td>
                <td>' . number_format($item->sale_price, 2) . '</td>
                <td>' . number_format($lineTotal, 2) . '</td>
            </tr>';
        }

        $discount    = $invoice->discount ?? 0;
        $netTotal    = $subTotal - $discount;
        $balanceDue  = $netTotal - $amountReceived;

        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total Qty</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td align="right"><b>Sub Total</b></td>
            <td><b>' . number_format($subTotal, 2) . '</b></td>
        </tr>';

        if ($discount > 0) {
            $html .= '<tr><td colspan="4" align="right"><b>Less: Discount</b></td><td>' . number_format($discount, 2) . '</td></tr>';
        }

        $html .= '
        <tr style="background-color:#f5f5f5;">
            <td colspan="4" align="right"><b>Net Payable</b></td>
            <td><b>' . number_format($netTotal, 2) . '</b></td>
        </tr>
        <tr>
            <td colspan="4" align="right"><b>Amount Received</b></td>
            <td style="color:green;">' . number_format($amountReceived, 2) . '</td>
        </tr>
        <tr style="background-color:#f5f5f5;">
            <td colspan="4" align="right"><b>Remaining Balance</b></td>
            <td style="color:red;"><b>' . number_format($balanceDue, 2) . '</b></td>
        </tr>
        </table>';

        $pdf->writeHTML($html, true, false, false, false, '');

        if (!empty($invoice->remarks)) {
            $pdf->Ln(5);
            $pdf->writeHTML('<b>Remarks:</b><br>' . nl2br($invoice->remarks), true, false, false, false, '');
        }

        if ($pdf->GetY() > 250) $pdf->AddPage();

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(28, $y, 88, $y);
        $pdf->Line(130, $y, 190, $y);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(28, $y + 2);
        $pdf->Cell(60, 10, 'Customer Signature', 0, 0, 'C');
        $pdf->SetXY(130, $y + 2);
        $pdf->Cell(60, 10, 'Authorized Signature', 0, 0, 'C');

        return $pdf->Output('Invoice_' . $invoice->invoice_no . '.pdf', 'I');
    }

    /* ---------------------------------------------------------------
     | Private: Calculate landed cost for one product
     --------------------------------------------------------------- */
    private function calcLandedCost(int $productId): float
    {
        return \Cache::remember("landed_cost_prod_{$productId}", 86400, function () use ($productId) {
            $purchase = PurchaseInvoiceItem::where('item_id', $productId)
                ->whereHas('invoice', fn($q) => $q->whereNull('deleted_at'))
                ->latest()
                ->first();

            if (!$purchase) return 0.0;

            $unitPrice       = (float) $purchase->price;
            $totalQtyInBatch = PurchaseInvoiceItem::where('purchase_invoice_id', $purchase->purchase_invoice_id)
                ->sum('quantity');
            $biltyCharge     = (float) ($purchase->invoice->bilty_charges ?? 0);
            $biltyPerUnit    = $biltyCharge / max($totalQtyInBatch, 1);

            return $unitPrice + $biltyPerUnit;
        });
    }

    /* ---------------------------------------------------------------
     | Private: Record COGS voucher on store()
     --------------------------------------------------------------- */
    private function recordCogsVoucher(array $items, string $date, string $invoiceNo, int $invoiceId): void
    {
        $inventoryAccount = ChartOfAccounts::where('name', 'Stock in Hand')->first();
        $cogsAccount      = ChartOfAccounts::where('account_type', 'cogs')->first();

        if (!$inventoryAccount || !$cogsAccount) {
            Log::warning('[SaleInvoice] COGS voucher skipped — account missing.');
            return;
        }

        $totalCost = 0;
        foreach ($items as $item) {
            $unitCost = $this->calcLandedCost($item['product_id']);
            foreach ($item['customizations'] ?? [] as $customId) {
                $unitCost += $this->calcLandedCost($customId);
            }
            $totalCost += $unitCost * $item['quantity'];
        }

        if ($totalCost <= 0) return;

        Voucher::create([
            'voucher_type' => 'journal',
            'date'         => $date,
            'ac_dr_sid'    => $cogsAccount->id,
            'ac_cr_sid'    => $inventoryAccount->id,
            'amount'       => $totalCost,
            'narration'    => "COGS for Invoice #{$invoiceNo}",
            'remarks'      => "COGS (Landed Cost) for Invoice #{$invoiceNo}",
            'reference'    => $invoiceId,
        ]);
    }
}