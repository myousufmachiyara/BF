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
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BarcodeSequence;
use App\Models\ProductSubcategory;
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
            'product_subcategories',
            'attributes',

            // Stock Management
            'locations',
            'stock_transfer',

            // Purchases
            'purchase_invoices',
            'purchase_return',

            // Sales
            'sale_invoices',
            'sale_return',

            // Vouchers
            'payment_vouchers',
            'vouchers',

            // Production
            'production',
            'production_receiving',
            'production_return',

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
            ['id'=>10,'shoa_id'=>6,'account_code'=>'301002','name'=>"Raw Material Expense", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Raw Material Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>11,'shoa_id'=>6,'account_code'=>'301003','name'=>"Labor / Wages Expense", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Labor Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],
            ['id'=>12,'shoa_id'=>6,'account_code'=>'301004','name'=>"Production Overheads", 'receivables'=>0,'payables'=>0,'opening_date'=>'2025-01-01','remarks'=>"Overhead Cost",'address'=>"",'phone_no'=>"",'created_by'=>1,'updated_by'=>1,'created_at'=>$now,'updated_at'=>$now],

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
            ['id' => 1, 'name' => 'Carat', 'shortcode' => 'ct'],
            ['id' => 2, 'name' => 'Milligram', 'shortcode' => 'mg'],
            ['id' => 3, 'name' => 'Kilogram', 'shortcode' => 'kg'],
            ['id' => 4, 'name' => 'Gram', 'shortcode' => 'g'],
            ['id' => 5, 'name' => 'Tola', 'shortcode' => 'tola'],
            ['id' => 6, 'name' => 'Karat', 'shortcode' => 'K'],
            ['id' => 7, 'name' => 'Millimeter', 'shortcode' => 'mm'],
            ['id' => 8, 'name' => 'Pieces', 'shortcode' => 'pcs'],

        ]);

        // 📦 Product Categories
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Diamond',   'code' => 'DIAM', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Gold',      'code' => 'GOLD', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Stone',     'code' => 'STON', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Chain',     'code' => 'CHAI', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Ring',      'code' => 'RING', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Earing',    'code' => 'EARI', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Pendent',   'code' => 'PEND', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'Bracelet',  'code' => 'BRAC', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'Bangel',    'code' => 'BANG', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'name' => 'Necklace', 'code' => 'NECK', 'created_at' => now(), 'updated_at' => now()],
        ]);

        ProductSubcategory::insert([
            // 🔹 Diamond
            ['category_id' => 1, 'name' => 'Diamond I',     'code' => 'DIAM-I',     'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond SI',    'code' => 'DIAM-SI',    'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 1, 'name' => 'Diamond SI-VS', 'code' => 'DIAM-SI-VS', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Gold
            ['category_id' => 2, 'name' => 'WG 14 CR.', 'code' => 'GOLD-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'WG 18 CR.', 'code' => 'GOLD-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 14 CR.', 'code' => 'GOLD-PG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'PG 18 CR.', 'code' => 'GOLD-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 14 CR.', 'code' => 'GOLD-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 2, 'name' => 'YG 18 CR.', 'code' => 'GOLD-YG18', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Stone
            ['category_id' => 3, 'name' => 'Stone 1', 'code' => 'STON-1', 'created_at' => now(), 'updated_at' => now()],

            // 🔹 Chain
            ['category_id' => 4, 'name' => 'WG 18 Cr', 'code' => 'CHAI-WG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 18 Cr', 'code' => 'CHAI-YG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 18 Cr', 'code' => 'CHAI-PG18', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'WG 14 Cr', 'code' => 'CHAI-WG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'YG 14 Cr', 'code' => 'CHAI-YG14', 'created_at' => now(), 'updated_at' => now()],
            ['category_id' => 4, 'name' => 'PG 14 Cr', 'code' => 'CHAI-PG14', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $shape = Attribute::create([
            'name' => 'Shape',
            'slug' => Str::slug('Shape'),
        ]);

        $shapes = [
            'Round', 'Princess', 'Emerald', 'Asscher', 'Marquise',
            'Oval', 'Radiant', 'Pear', 'Cushion', 'Heart',
            'Baguette', 'Trillion', 'Rose Cut', 'Old Mine Cut', 'Cabochon'
        ];

        foreach ($shapes as $s) {
            AttributeValue::create([
                'attribute_id' => $shape->id,
                'value' => $s,
            ]);
        }

        // 2️⃣ Size Attribute
        $size = Attribute::create([
            'name' => 'Size',
            'slug' => Str::slug('Size'),
        ]);

        $sizes = [
            // Carat / mm for diamonds
            '0.01 ct (1.0 mm)', '0.05 ct (2.5 mm)', '0.10 ct (3.0 mm)',
            '0.25 ct (4.0 mm)', '0.50 ct (5.0 mm)', '0.75 ct (5.8 mm)',
            '1.00 ct (6.5 mm)', '1.50 ct (7.5 mm)', '2.00 ct (8.2 mm)', '3.00 ct (9.5 mm)',

            // Rings (UAE/GCC sizes)
            'Size 10', 'Size 12', 'Size 14', 'Size 16', 'Size 18', 'Size 20', 'Size 22', 'Size 24', 'Size 26', 'Size 28',

            // Chains / Bracelets (length)
            '14 cm', '16 cm', '18 cm', '20 cm', '22 cm', '24 cm',

            // Bangles (diameter mm)
            '55 mm', '57 mm', '60 mm', '62 mm', '65 mm', '67 mm', '70 mm',
        ];

        foreach ($sizes as $sz) {
            AttributeValue::create([
                'attribute_id' => $size->id,
                'value' => $sz,
            ]);
        }

        $sequences = [
            ['prefix' => 'GLOBAL', 'next_number' => 1],
            ['prefix' => 'FG', 'next_number' => 1],
            ['prefix' => 'RAW', 'next_number' => 1],
            ['prefix' => 'SRV', 'next_number' => 1],
            ['prefix' => 'PRD', 'next_number' => 1],
            ['prefix' => 'VAR', 'next_number' => 1],
        ];

        foreach ($sequences as $seq) {
            BarcodeSequence::firstOrCreate(
                ['prefix' => $seq['prefix']],
                ['next_number' => $seq['next_number']]
            );
        }
    }
}
