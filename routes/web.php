<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\{
    DashboardController,
    SubHeadOfAccController,
    COAController,
    SaleInvoiceController,
    PurchaseInvoiceController,
    PurchaseReturnController,
    ProductController,
    UserController,
    RoleController,
    ProductCategoryController,
    VoucherController,
    InventoryReportController,
    PurchaseReportController,
    SalesReportController,
    AccountsReportController,
    SaleReturnController,
    PermissionController,
    PurchaseBiltyController,
    PDCController,
};

Auth::routes();
Route::post('/clear-cache', function () {
    Artisan::call('cache:clear');
        
    return response()->json(['status' => 'success', 'message' => 'Cache Cleared']);
})->name('admin.clear-cache');

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::put('/users/{id}/change-password', [UserController::class, 'changePassword'])->name('users.changePassword');
    Route::put('/users/{id}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggleActive');

    // Product Helpers
    Route::get('/products/details', [ProductController::class, 'details'])->name('products.receiving');

    //Purchase Helper
    Route::get('/product/{product}/invoices', [PurchaseInvoiceController::class, 'getProductInvoices']);
    Route::patch('purchase_invoices/{id}/restore', [PurchaseInvoiceController::class, 'restore'])->name('purchase_invoices.restore');
    
    //Bilty Helper
    Route::get('/get-purchase-items/{id}', [App\Http\Controllers\PurchaseBiltyController::class, 'getInvoiceItems']);

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
    
        // Purchases
        'purchase_invoices' => ['controller' => PurchaseInvoiceController::class, 'permission' => 'purchase_invoices'],
        'purchase_return' => ['controller' => PurchaseReturnController::class, 'permission' => 'purchase_return'],
        'purchase_bilty' => ['controller' => PurchaseBiltyController::class, 'permission' => 'purchase_bilty'],

        // Sales
        'sale_invoices' => ['controller' => SaleInvoiceController::class, 'permission' => 'sale_invoices'],
        'sale_return' => ['controller' => SaleReturnController::class, 'permission' => 'sale_return'],

        // Vouchers
        'vouchers' => ['controller' => VoucherController::class, 'permission' => 'vouchers'],
        'pdc' => ['controller' => PDCController::class, 'permission' => 'pdc'],
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
        Route::get('sale', [SalesReportController::class, 'saleReports'])->name('sale');
        Route::get('accounts', [AccountsReportController::class, 'accounts'])->name('accounts');
        Route::get('/print-profit/{id}', [SalesReportController::class, 'printProfitReport'])->name('print-profit');
    });
});
Auth::routes();
// Add lifecycle routes for PDC after or before the loop
Route::prefix('pdc')->group(function () {
    Route::patch('/{id}/deposit', [PDCController::class, 'deposit'])
        ->middleware("check.permission:pdc.edit")
        ->name("pdc.deposit");
        
    Route::patch('/{id}/clear', [PDCController::class, 'clear'])
        ->middleware("check.permission:pdc.edit")
        ->name("pdc.clear");
        
    Route::patch('/{id}/bounce', [PDCController::class, 'bounce'])
        ->middleware("check.permission:pdc.edit")
        ->name("pdc.bounce");
});
Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
