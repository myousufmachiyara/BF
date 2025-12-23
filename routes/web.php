<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    SaleInvoiceController,
    ProductionController,
    PurchaseInvoiceController,
    PurchaseReturnController,
    ProductController,
    UserController,
    RoleController,
    AttributeController,
    ProductCategoryController,
    ProductionReceivingController,
    VoucherController,
    InventoryReportController,
    PurchaseReportController,
    ProductionReportController,
    SalesReportController,
    AccountsReportController,
    SummaryReportController,
    POSController,
    SaleReturnController,
    PermissionController,
    LocationController,
    StockTransferController,
    ProductionReturnController,
    ProductSubcategoryController,
    MarketRateController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('market_rates', MarketRateController::class);

    Route::put('/users/{id}/change-password', [UserController::class, 'changePassword'])->name('users.changePassword');
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggleActive');

    // Product Helpers
    Route::get('/products/details', [ProductController::class, 'details'])->name('products.receiving');
    Route::get('/products/barcode-selection', [ProductController::class, 'barcodeSelection'])->name('products.barcode.selection');
    Route::post('/products/generate-multiple-barcodes', [ProductController::class, 'generateMultipleBarcodes'])->name('products.generateBarcodes');
    Route::get('/get-product-by-code/{barcode}', [ProductController::class, 'getByBarcode'])->name('product.byBarcode');
    Route::get('/product/{product}/variations', [ProductController::class, 'getVariations'])->name('product.variations');
    Route::get('/product/{product}/productions', [ProductionController::class, 'getProductProductions'])->name('product.productions');
    Route::post('/products/bulk-upload', [ProductController::class, 'bulkUploadStore'])->name('products.bulk-upload.store');
    Route::get('/products/bulk-upload/template', [ProductController::class, 'bulkUploadTemplate'])->name('products.bulk-upload.template');
    Route::get('/get-subcategories/{category_id}', [ProductCategoryController::class, 'getSubcategories'])->name('products.getSubcategories');

    //Purchase Helper
    Route::get('/product/{product}/invoices', [PurchaseInvoiceController::class, 'getProductInvoices']);

    // Production Summary
    Route::get('/production-summary/{id}', [ProductionController::class, 'summary'])->name('production.summary');
    Route::get('/production-gatepass/{id}', [ProductionController::class, 'printGatepass'])->name('production.gatepass');
    // Common Modules
    $modules = [
        // User Management
        'roles' => ['controller' => RoleController::class, 'permission' => 'user_roles'],
        'permissions' => ['controller' => PermissionController::class, 'permission' => 'role_permissions'],
        'users' => ['controller' => UserController::class, 'permission' => 'users'],

        // Accounts
        'coa' => ['controller' => COAController::class, 'permission' => 'coa'],
        'shoa' => ['controller' => SubHeadOfAccController::class, 'permission' => 'shoa'],

        // Products
        'products' => ['controller' => ProductController::class, 'permission' => 'products'],
        'product_categories' => ['controller' => ProductCategoryController::class, 'permission' => 'product_categories'],
        'product_subcategories' => ['controller' => ProductSubcategoryController::class, 'permission' => 'product_subcategories'],
        'attributes' => ['controller' => AttributeController::class, 'permission' => 'attributes'],

        // Stock Management
        'locations' => ['controller' => LocationController::class, 'permission' => 'locations'],
        'stock_transfer' => ['controller' => StockTransferController::class, 'permission' => 'stock_transfer'],

        // Purchases
        'purchase_invoices' => ['controller' => PurchaseInvoiceController::class, 'permission' => 'purchase_invoices'],
        'purchase_return' => ['controller' => PurchaseReturnController::class, 'permission' => 'purchase_return'],

        // Sales
        'sale_invoices' => ['controller' => SaleInvoiceController::class, 'permission' => 'sale_invoices'],
        'sale_return' => ['controller' => SaleReturnController::class, 'permission' => 'sale_return'],

        // Vouchers
        'payment_vouchers' => ['controller' => PaymentVoucherController::class, 'permission' => 'payment_vouchers'],
        'vouchers' => ['controller' => VoucherController::class, 'permission' => 'vouchers'],

        // Production
        'production' => ['controller' => ProductionController::class, 'permission' => 'production'],
        'production_receiving' => ['controller' => ProductionReceivingController::class, 'permission' => 'production_receiving'],
        'production_return' => ['controller' => ProductionReturnController::class, 'permission' => 'production_return'],

        // POS (optional)
        'pos_system' => ['controller' => POSController::class, 'permission' => 'pos_system'],
    ];

    foreach ($modules as $uri => $config) {
        $controller = $config['controller'];
        $permission = $config['permission'];

        // Determine route parameter
        $param = $uri === 'roles' ? '{role}' : '{id}';

        if ($uri === 'vouchers') {
            // Voucher routes with type in all relevant actions
            Route::prefix("$uri/{type}")->group(function () use ($controller, $permission) {
                Route::get('/', [$controller, 'index'])->middleware("check.permission:$permission.index")->name("vouchers.index");
                Route::get('/create', [$controller, 'create'])->middleware("check.permission:$permission.create")->name("vouchers.create");
                Route::post('/', [$controller, 'store'])->middleware("check.permission:$permission.create")->name("vouchers.store");

                Route::get('/{id}', [$controller, 'show'])->middleware("check.permission:$permission.index")->name("vouchers.show");
                Route::get('/{id}/edit', [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("vouchers.edit");
                Route::put('/{id}', [$controller, 'update'])->middleware("check.permission:$permission.edit")->name("vouchers.update");
                Route::delete('/{id}', [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("vouchers.destroy");
                Route::get('/{id}/print', [$controller, 'print'])->middleware("check.permission:$permission.print")->name('vouchers.print');
            });

            continue;
        }

        // Index & Create
        Route::get("$uri", [$controller, 'index'])->middleware("check.permission:$permission.index")->name("$uri.index");
        Route::get("$uri/create", [$controller, 'create'])->middleware("check.permission:$permission.create")->name("$uri.create");
        Route::post("$uri", [$controller, 'store'])->middleware("check.permission:$permission.create")->name("$uri.store");

        // Show, Edit, Update, Delete, Print
        Route::get("$uri/$param", [$controller, 'show'])->middleware("check.permission:$permission.index")->name("$uri.show");
        Route::get("$uri/$param/edit", [$controller, 'edit'])->middleware("check.permission:$permission.edit")->name("$uri.edit");
        Route::put("$uri/$param", [$controller, 'update'])->middleware("check.permission:$permission.edit")->name("$uri.update");
        Route::delete("$uri/$param", [$controller, 'destroy'])->middleware("check.permission:$permission.delete")->name("$uri.destroy");
        Route::get("$uri/$param/print", [$controller, 'print'])->middleware("check.permission:$permission.print")->name("$uri.print");
    }

    // Reports (readonly)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory', [InventoryReportController::class, 'inventoryReports'])->name('inventory');
        Route::get('purchase', [PurchaseReportController::class, 'purchaseReports'])->name('purchase');
        Route::get('production', [ProductionReportController::class, 'productionReports'])->name('production');
        Route::get('sale', [SalesReportController::class, 'saleReports'])->name('sale');
        Route::get('accounts', [AccountsReportController::class, 'accounts'])->name('accounts');
    });
});
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
