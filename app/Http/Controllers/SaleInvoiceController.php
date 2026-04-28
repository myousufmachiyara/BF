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
     | FIXED: Uses same raw DB logic as InventoryReportController
     | so create/edit form stock matches stock-in-hand report exactly.
     | Customization qty = parent invoice item qty (not row count).
     --------------------------------------------------------------- */
    private function getProductsWithStock(): \Illuminate\Support\Collection
    {
        // ── Total purchased per product ──────────────────────────────
        $purchased = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->whereNull('purchase_invoices.deleted_at')
            ->groupBy('purchase_invoice_items.item_id')
            ->select(
                'purchase_invoice_items.item_id as product_id',
                DB::raw('SUM(purchase_invoice_items.quantity) as qty')
            )
            ->pluck('qty', 'product_id');

        // ── Total sold per product ────────────────────────────────────
        $sold = DB::table('sale_invoice_items')
            ->join('sale_invoices', 'sale_invoice_items.sale_invoice_id', '=', 'sale_invoices.id')
            ->whereNull('sale_invoices.deleted_at')
            ->groupBy('sale_invoice_items.product_id')
            ->select(
                'sale_invoice_items.product_id',
                DB::raw('SUM(sale_invoice_items.quantity) as qty')
            )
            ->pluck('qty', 'product_id');

        // ── Total used as customization part per product ──────────────
        // KEY FIX: SUM(sale_invoice_items.quantity) not COUNT(*)
        // because each customization part is consumed at parent item qty.
        // e.g. Chair qty=3 with Wheel customization → 3 Wheels consumed.
        $customized = DB::table('sale_item_customization')
            ->join('sale_invoices', 'sale_item_customization.sale_invoice_id', '=', 'sale_invoices.id')
            ->join('sale_invoice_items', 'sale_invoice_items.id', '=', 'sale_item_customization.sale_invoice_items_id')
            ->whereNull('sale_invoices.deleted_at')
            ->groupBy('sale_item_customization.item_id')
            ->select(
                'sale_item_customization.item_id as product_id',
                DB::raw('SUM(sale_invoice_items.quantity) as qty')
            )
            ->pluck('qty', 'product_id');

        // ── Total purchase returned per product (stock goes out) ──────
        $purchaseReturned = DB::table('purchase_return_items')
            ->join('purchase_returns', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->whereNull('purchase_returns.deleted_at')
            ->groupBy('purchase_return_items.item_id')
            ->select(
                'purchase_return_items.item_id as product_id',
                DB::raw('SUM(purchase_return_items.quantity) as qty')
            )
            ->pluck('qty', 'product_id');

        // ── Total sale returned per product (stock comes back) ────────
        $saleReturned = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_return_items.sale_return_id', '=', 'sale_returns.id')
            ->whereNull('sale_returns.deleted_at')
            ->groupBy('sale_return_items.product_id')
            ->select(
                'sale_return_items.product_id',
                DB::raw('SUM(sale_return_items.qty) as qty')   // note: qty not quantity
            )
            ->pluck('qty', 'product_id');

        return Product::orderBy('name', 'asc')
            ->whereNull('deleted_at')
            ->get()
            ->map(function ($product) use ($purchased, $sold, $customized, $purchaseReturned, $saleReturned) {
                $in  = ($purchased[$product->id]        ?? 0)
                     + ($saleReturned[$product->id]     ?? 0);

                $out = ($sold[$product->id]             ?? 0)
                     + ($customized[$product->id]       ?? 0)
                     + ($purchaseReturned[$product->id] ?? 0);

                $product->real_time_stock = $in - $out;
                return $product;
            });
    }

    /* ---------------------------------------------------------------
     | Shared customer + payment account queries (role-aware)
     --------------------------------------------------------------- */
    private function getCustomers($selectedId = null)
    {
        $q = ChartOfAccounts::where('account_type', 'customer');
        if (!auth()->user()->hasRole('superadmin')) {
            $q->where(function ($query) use ($selectedId) {
                $query->where('visibility', 'public');
                if ($selectedId) {
                    $query->orWhere('id', $selectedId);
                }
            });
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
    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'account', 'receiptVouchers')
            ->latest()
            ->get();
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

        DB::beginTransaction();
        try {
            // ── Invoice number ──────────────────────────────────────────
            $lastInvoice = SaleInvoice::withTrashed()->orderBy('id', 'desc')->first();
            $invoiceNo   = str_pad(
                $lastInvoice ? intval($lastInvoice->invoice_no) + 1 : 1,
                6, '0', STR_PAD_LEFT
            );

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

            // ── Create items + customizations ───────────────────────────
            $totalBill = 0;

            foreach ($validated['items'] as $idx => $item) {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                ]);

                $totalBill += $item['sale_price'] * $item['quantity'];

                foreach ($item['customizations'] ?? [] as $customItemId) {
                    SaleItemCustomization::create([
                        'sale_invoice_id'       => $invoice->id,
                        'sale_invoice_items_id' => $invoiceItem->id,
                        'item_id'               => $customItemId,
                    ]);
                }
            }

            $netTotal = $totalBill - ($validated['discount'] ?? 0);

            // ── Sales Revenue voucher ───────────────────────────────────
            $salesAccount = ChartOfAccounts::withTrashed()
                ->where('account_type', 'revenue')
                ->first();

            if (!$salesAccount) {
                throw new \Exception('Sales Revenue account not found in COA.');
            }

            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id,
                'amount'       => $netTotal,
                'remarks'      => "Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);

            // ── Payment receipt voucher ─────────────────────────────────
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // ── COGS voucher ────────────────────────────────────────────
            $this->recordCogsVoucher(
                $validated['items'],
                $validated['date'],
                $invoiceNo,
                $invoice->id
            );

            DB::commit();
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Sale Invoice saved successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleInvoice][Store] ROLLED BACK', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);
            return back()->withInput()->with('error', 'Error saving invoice: ' . $e->getMessage());
        }
    }

    /* ================= EDIT ================= */
    public function edit($id)
    {
        $invoice  = SaleInvoice::with(['items.customizations', 'account'])->findOrFail($id);
        $products = $this->getProductsWithStock();

        // ── Add back main product quantities ─────────────────────────
        // Without this, existing items on the invoice appear as "over-stock"
        $mainQtyMap = $invoice->items->keyBy('product_id');

        // ── Add back customization part quantities ────────────────────
        // Each part is consumed at the PARENT item's quantity.
        // e.g. Chair qty=3 with Wheel → Wheel consumed 3, so add 3 back.
        $customQtyMap = [];
        foreach ($invoice->items as $item) {
            foreach ($item->customizations as $customization) {
                $partId = $customization->item_id;
                $customQtyMap[$partId] = ($customQtyMap[$partId] ?? 0) + $item->quantity;
            }
        }

        $products = $products->map(function ($product) use ($mainQtyMap, $customQtyMap) {
            if ($mainQtyMap->has($product->id)) {
                $product->real_time_stock += $mainQtyMap[$product->id]->quantity;
            }
            if (isset($customQtyMap[$product->id])) {
                $product->real_time_stock += $customQtyMap[$product->id];
            }
            return $product;
        });

        $amountReceived = Voucher::where('reference', $invoice->id)
            ->where('voucher_type', 'receipt')
            ->whereNull('deleted_at')
            ->sum('amount');

        // Pass current account_id so private customers still appear in dropdown
        $customers       = $this->getCustomers($invoice->account_id);
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
            // ── Look up system accounts FIRST, fail fast if missing ───
            $salesAccount     = ChartOfAccounts::withTrashed()->where('account_type', 'revenue')->first();
            $inventoryAccount = ChartOfAccounts::withTrashed()->where('name', 'Stock in Hand')->first();
            $cogsAccount      = ChartOfAccounts::withTrashed()->where('account_type', 'cogs')->first();

            if (!$salesAccount) {
                throw new \Exception('Sales Revenue account (account_type=revenue) not found in COA.');
            }

            $invoice   = SaleInvoice::findOrFail($id);
            $invoiceNo = $invoice->invoice_no;

            $invoice->update([
                'date'       => $validated['date'],
                'account_id' => $validated['account_id'],
                'type'       => $validated['type'],
                'discount'   => $validated['discount'] ?? 0,
                'remarks'    => $validated['remarks'] ?? null,
            ]);

            // ── Clear and re-insert items + customizations ────────────
            SaleItemCustomization::where('sale_invoice_id', $invoice->id)->delete();
            $invoice->items()->delete();

            $totalBill = 0;
            $totalCost = 0;

            foreach ($validated['items'] as $item) {
                $invoiceItem = SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id'      => $item['product_id'],
                    'sale_price'      => $item['sale_price'],
                    'quantity'        => $item['quantity'],
                ]);

                $totalBill += $item['sale_price'] * $item['quantity'];

                // Unit cost = main product + all its customization parts
                $unitCost = $this->calcLandedCost($item['product_id']);

                foreach ($item['customizations'] ?? [] as $customId) {
                    SaleItemCustomization::create([
                        'sale_invoice_id'       => $invoice->id,
                        'sale_invoice_items_id' => $invoiceItem->id,
                        'item_id'               => $customId,
                    ]);
                    $unitCost += $this->calcLandedCost($customId);
                }

                $totalCost += $unitCost * $item['quantity'];
            }

            $netTotal = max(0, $totalBill - ($validated['discount'] ?? 0));
            $invoice->update(['net_amount' => $netTotal]);

            // ── Delete old journal vouchers, keep receipts ────────────
            Voucher::where('reference', $invoice->id)
                ->where('voucher_type', 'journal')
                ->delete();

            // ── Re-create Sales Revenue voucher ──────────────────────
            Voucher::create([
                'voucher_type' => 'journal',
                'date'         => $validated['date'],
                'ac_dr_sid'    => $validated['account_id'],
                'ac_cr_sid'    => $salesAccount->id,
                'amount'       => $netTotal,
                'remarks'      => "Updated: Sales Invoice #{$invoiceNo}",
                'reference'    => $invoice->id,
            ]);

            // ── Re-create COGS voucher ────────────────────────────────
            if ($inventoryAccount && $cogsAccount && $totalCost > 0) {
                Voucher::create([
                    'voucher_type' => 'journal',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $cogsAccount->id,
                    'ac_cr_sid'    => $inventoryAccount->id,
                    'amount'       => $totalCost,
                    'remarks'      => "Updated: COGS for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // ── Optional new payment receipt ──────────────────────────
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'remarks'      => "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            // ── Update existing receipt vouchers ─────────────────────────
            if (!empty($request->existing_receipts)) {
                $this->updateExistingReceipts(
                    $request->existing_receipts,
                    $validated['account_id']
                );
            }

            // ── Optional new payment receipt ──────────────────────────────
            if ($request->filled('payment_account_id') && $request->amount_received > 0) {
                Voucher::create([
                    'voucher_type' => 'receipt',
                    'date'         => $request->payment_date ?? $validated['date'],
                    'ac_dr_sid'    => $validated['payment_account_id'],
                    'ac_cr_sid'    => $validated['account_id'],
                    'amount'       => $validated['amount_received'],
                    'remarks'      => $request->payment_remarks
                                        ? $request->payment_remarks
                                        : "Payment received for Invoice #{$invoiceNo}",
                    'reference'    => $invoice->id,
                ]);
            }

            DB::commit();

            foreach ($validated['items'] as $item) {
                \Cache::forget("landed_cost_prod_{$item['product_id']}");
            }

            return redirect()->route('sale_invoices.index')
                ->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Update failed: ' . $e->getMessage(), [
                'invoice_id' => $id,
                'trace'      => $e->getTraceAsString(),
            ]);
            return back()->withInput()
                ->with('error', 'Update failed: ' . $e->getMessage());
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
            return redirect()->route('sale_invoices.index')
                ->with('success', 'Invoice deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[SaleInvoice] Delete failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to delete invoice: ' . $e->getMessage());
        }
    }

    /* ================= SHOW ================= */
    public function show($id)
    {
        $invoice = SaleInvoice::with('items.product', 'account')->findOrFail($id);
        return response()->json($invoice);
    }

    /* ================= PRINT ================= */
    public function print($id)
    {
        $invoice = SaleInvoice::with(['account', 'items.product'])->findOrFail($id);

        $amountReceived = Voucher::where('reference', $invoice->id)
            ->where('voucher_type', 'receipt')
            ->whereNull('deleted_at')
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
                        <tr>
                            <td width="30%"><b>Customer</b></td>
                            <td width="70%">' . ($invoice->account->name ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Invoice No</b></td>
                            <td width="70%">' . $invoice->invoice_no . '</td>
                        </tr>
                        <tr>
                            <td width="30%"><b>Date</b></td>
                            <td width="70%">' . \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') . '</td>
                        </tr>
                    </table>
                </td>
                <td width="60%"></td>
            </tr>
        </table>';
        $pdf->writeHTML($infoHtml, true, false, false, false, '');

        $html = '
        <table border="1" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="font-weight:bold;background-color:#f5f5f5;">
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

        $discount   = $invoice->discount ?? 0;
        $netTotal   = $subTotal - $discount;
        $balanceDue = $netTotal - $amountReceived;

        $html .= '
        <tr>
            <td colspan="2" align="right"><b>Total Qty</b></td>
            <td><b>' . number_format($totalQty, 2) . '</b></td>
            <td align="right"><b>Sub Total</b></td>
            <td><b>' . number_format($subTotal, 2) . '</b></td>
        </tr>';

        if ($discount > 0) {
            $html .= '
            <tr>
                <td colspan="4" align="right"><b>Less: Discount</b></td>
                <td>' . number_format($discount, 2) . '</td>
            </tr>';
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
            $pdf->writeHTML(
                '<b>Remarks:</b><br>' . nl2br($invoice->remarks),
                true, false, false, false, ''
            );
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
     | Private: Calculate landed cost for one product (cached 24hr)
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
        $inventoryAccount = ChartOfAccounts::withTrashed()->where('name', 'Stock in Hand')->first();
        $cogsAccount      = ChartOfAccounts::withTrashed()->where('account_type', 'cogs')->first();

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
            'remarks'      => "COGS (Landed Cost) for Invoice #{$invoiceNo}",
            'reference'    => $invoiceId,
        ]);
    }

    /* ================= BULK REGENERATE VOUCHERS ================= */
    public function bulkRegenerateVouchers(Request $request)
    {
        $request->validate([
            'invoice_ids'   => 'required|array|min:1',
            'invoice_ids.*' => 'exists:sale_invoices,id',
        ]);

        $salesAccount     = ChartOfAccounts::withTrashed()->where('account_type', 'revenue')->first();
        $inventoryAccount = ChartOfAccounts::withTrashed()->where('name', 'Stock in Hand')->first();
        $cogsAccount      = ChartOfAccounts::withTrashed()->where('account_type', 'cogs')->first();

        if (!$salesAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Sales Revenue account not found in Chart of Accounts.',
            ], 422);
        }

        $invoices  = SaleInvoice::with('items.customizations')->whereIn('id', $request->invoice_ids)->get();
        $succeeded = 0;
        $failed    = [];

        foreach ($invoices as $invoice) {
            DB::beginTransaction();
            try {
                Voucher::where('reference', $invoice->id)
                    ->where('voucher_type', 'journal')
                    ->delete();

                $totalBill = $invoice->items->sum(fn($i) => $i->sale_price * $i->quantity);
                $netTotal  = max(0, $totalBill - ($invoice->discount ?? 0));

                // Sales Revenue voucher
                Voucher::create([
                    'voucher_type' => 'journal',
                    'date'         => $invoice->date,
                    'ac_dr_sid'    => $invoice->account_id,
                    'ac_cr_sid'    => $salesAccount->id,
                    'amount'       => $netTotal,
                    'remarks'      => "Sales Invoice #{$invoice->invoice_no}",
                    'reference'    => $invoice->id,
                ]);

                // COGS voucher
                if ($inventoryAccount && $cogsAccount) {
                    $totalCost = 0;
                    foreach ($invoice->items as $item) {
                        $unitCost = $this->calcLandedCost($item->product_id);
                        foreach ($item->customizations as $customization) {
                            $unitCost += $this->calcLandedCost($customization->item_id);
                        }
                        $totalCost += $unitCost * $item->quantity;
                    }

                    if ($totalCost > 0) {
                        Voucher::create([
                            'voucher_type' => 'journal',
                            'date'         => $invoice->date,
                            'ac_dr_sid'    => $cogsAccount->id,
                            'ac_cr_sid'    => $inventoryAccount->id,
                            'amount'       => $totalCost,
                            'remarks'      => "COGS (Landed Cost) for Invoice #{$invoice->invoice_no}",
                            'reference'    => $invoice->id,
                        ]);
                    }
                }

                DB::commit();
                $succeeded++;

            } catch (\Throwable $e) {
                DB::rollBack();
                $failed[] = "Invoice #{$invoice->invoice_no}: " . $e->getMessage();
                Log::error('[BulkRegenerate] Failed for invoice #' . $invoice->invoice_no, [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "✓ Vouchers regenerated for {$succeeded} invoice(s).";
        if (count($failed)) {
            $message .= ' ' . count($failed) . ' failed: ' . implode('; ', $failed);
        }

        return response()->json([
            'success'   => count($failed) === 0,
            'message'   => $message,
            'succeeded' => $succeeded,
            'failed'    => count($failed),
            'errors'    => $failed,
        ]);
    }

    /* ================= DELETE SINGLE RECEIPT ================= */
    public function deleteReceipt($id)
    {
        try {
            $voucher = Voucher::where('id', $id)
                ->where('voucher_type', 'receipt')
                ->firstOrFail();

            $voucher->delete();

            return response()->json(['success' => true, 'message' => 'Receipt deleted.']);

        } catch (\Exception $e) {
            Log::error('[SaleInvoice] Delete receipt failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /* ================= UPDATE EXISTING RECEIPTS (called inside update()) ================= */
    private function updateExistingReceipts(array $receipts, int $invoiceAccountId): void
    {
        foreach ($receipts as $voucherId => $data) {
            $voucher = Voucher::where('id', $voucherId)
                ->where('voucher_type', 'receipt')
                ->first();

            if (!$voucher) continue;

            $voucher->update([
                'date'      => $data['date']               ?? $voucher->date,
                'ac_dr_sid' => $data['payment_account_id'] ?? $voucher->ac_dr_sid,
                'ac_cr_sid' => $invoiceAccountId,
                'amount'    => $data['amount']             ?? $voucher->amount,
                'remarks'   => $data['remarks']            ?? $voucher->remarks,
            ]);
        }
    }
}