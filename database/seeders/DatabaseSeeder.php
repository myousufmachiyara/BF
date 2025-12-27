<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\MeasurementUnit;
use App\Models\ProductCategory;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = now();

        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com', // optional, keep if you want for notifications
                'password' => Hash::make('12345678'),
            ]
        );

        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdmin);

        // 📌 Functional Modules (CRUD-style permissions)
        $modules = [
            // User Management
            'user_roles',
            'users',

            // Accounts
            'coa',
            'shoa',

            // Products
            'products',
            'product_categories',

            // Purchases
            'purchase_invoices',
            'purchase_return',

            // Sales
            'sale_invoices',
            'sale_return',

            // Vouchers
            'vouchers',

        ];

        $actions = ['index', 'create', 'edit', 'delete', 'print'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "$module.$action",
                ]);
            }
        }

        // 📊 Report permissions (only view access, no CRUD)
        $reports = ['inventory', 'purchase', 'production', 'sales', 'accounts'];

        foreach ($reports as $report) {
            Permission::firstOrCreate([
                'name' => "reports.$report",
            ]);
        }

        // Assign all permissions to Superadmin
        $superAdmin->syncPermissions(Permission::all());

        // ---------------------
        // 1️⃣ Heads of Accounts
        // ---------------------        
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets'],
            ['id' => 2, 'name' => 'Liabilities'],
            ['id' => 3, 'name' => 'Expenses'],
            ['id' => 4, 'name' => 'Revenue'],
            ['id' => 5, 'name' => 'Equity'],
        ]);

        // ---------------------
        // 2️⃣ Sub Heads
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => "Current Assets"],      
            ['id' => 2, 'hoa_id' => 1, 'name' => "Inventory"],           
            ['id' => 3, 'hoa_id' => 2, 'name' => "Current Liabilities"], 
            ['id' => 4, 'hoa_id' => 2, 'name' => "Long-Term Liabilities"], 
            ['id' => 5, 'hoa_id' => 4, 'name' => "Sales"],               
            ['id' => 6, 'hoa_id' => 3, 'name' => "Expenses"],            
            ['id' => 7, 'hoa_id' => 5, 'name' => "Equity"],              
        ]);

        // ---------------------
        // 3️⃣ Chart of Accounts
        // ---------------------
        ChartOfAccounts::insert([
            // Assets
            ['id'=>1, 'shoa_id'=>1, 'account_code'=>'101001','name'=>"Cash", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Asset",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>2, 'shoa_id'=>1, 'account_code'=>'101002','name'=>"Bank", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Asset",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>3, 'shoa_id'=>1, 'account_code'=>'101003','name'=>"Accounts Receivable", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Customer Accounts",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>5, 'shoa_id'=>2, 'account_code'=>'102001','name'=>"Raw Material Inventory", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>7, 'shoa_id'=>2, 'account_code'=>'102002','name'=>"WIP Inventory", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Work-in-Progress Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>8, 'shoa_id'=>2, 'account_code'=>'102003','name'=>"Finished Goods Inventory", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Finished Goods Inventory",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Liabilities
            ['id'=>4, 'shoa_id'=>3, 'account_code'=>'201001','name'=>"Accounts Payable", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Supplier Accounts",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>9, 'shoa_id'=>4, 'account_code'=>'202001','name'=>"Long-Term Loans", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Long-Term Liabilities",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Expenses
            ['id'=>6, 'shoa_id'=>6, 'account_code'=>'301001','name'=>"Expense Account", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Expense",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>11,'shoa_id'=>6,'account_code'=>'301003','name'=>"Labor / Wages Expense", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Labor Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Revenue
            ['id'=>13,'shoa_id'=>5,'account_code'=>'401001','name'=>"Sales", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Production Sales",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>14,'shoa_id'=>5,'account_code'=>'401002','name'=>"Other Income", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Other Income",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

            // Equity
            ['id'=>15,'shoa_id'=>7,'account_code'=>'501001','name'=>"Equity", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Owner Equity",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>16,'shoa_id'=>7,'account_code'=>'501002','name'=>"Capital", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Capital Account",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>17,'shoa_id'=>7,'account_code'=>'501003','name'=>"Retained Earnings", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Retained Earnings",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
        ]);


        // 📏 Measurement Units
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Pieces', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Carton', 'shortcode' => 'ct'],

        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Chair',   'code' => 'chair', 'created_at' => now(), 'updated_at' => now()],
            
        ]);

        
    }
}
