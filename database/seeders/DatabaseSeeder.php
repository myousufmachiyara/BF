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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $userId = 1; // ID for created_by / updated_by

        // 🔑 Create Super Admin User
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('abc123+'),
            ]
        );

        $superAdmin = Role::firstOrCreate(['name' => 'superadmin']);
        $admin->assignRole($superAdmin);

        // 📌 Modules & Permissions
        $modules = [
            'user_roles','users','coa','shoa','products','product_categories',
            'purchase_invoices','purchase_return','purchase_bilty','sale_invoices','sale_return','vouchers','pdc'
        ];
        $actions = ['index', 'create', 'edit', 'delete', 'print'];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "$module.$action"]);
            }
        }

        // 📊 Report permissions
        $reports = ['inventory', 'purchase', 'sales', 'accounts'];
        foreach ($reports as $report) {
            Permission::firstOrCreate(['name' => "reports.$report"]);
        }

        // Assign all permissions to superadmin
        $superAdmin->syncPermissions(Permission::all());

        // ---------------------
        // HEADS OF ACCOUNTS
        // ---------------------
        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // SUB HEADS
        // ---------------------
        SubHeadOfAccounts::insert([
            ['id' => 1, 'hoa_id' => 1, 'name' => 'Cash', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'hoa_id' => 1, 'name' => 'Bank', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'hoa_id' => 1, 'name' => 'Accounts Receivable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'hoa_id' => 1, 'name' => 'Inventory', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'hoa_id' => 2, 'name' => 'Accounts Payable', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'hoa_id' => 2, 'name' => 'Loans', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'hoa_id' => 3, 'name' => 'Owner Capital', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'hoa_id' => 4, 'name' => 'Sales', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'hoa_id' => 5, 'name' => 'Purchases', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10,'hoa_id' => 5, 'name' => 'Salaries', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11,'hoa_id' => 5, 'name' => 'Rent', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12,'hoa_id' => 5, 'name' => 'Utilities', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ---------------------
        // CHART OF ACCOUNTS
        // ---------------------
        $coaData = [
            ['account_code' => '104001', 'shoa_id' => 4, 'name' => 'Stock in Hand', 'account_type' => 'asset', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '103001', 'shoa_id' => 3, 'name' => 'Customer 01', 'account_type' => 'customer', 'receivables' => 12000.00, 'payables' => 0.00],
            ['account_code' => '205001', 'shoa_id' => 5, 'name' => 'Vendor 01', 'account_type' => 'vendor', 'receivables' => 0.00, 'payables' => 7500.00],
            ['account_code' => '101001', 'shoa_id' => 1, 'name' => 'Shop Cash', 'account_type' => 'cash', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '102001', 'shoa_id' => 2, 'name' => 'Meezan Yousuf', 'account_type' => 'bank', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '307001', 'shoa_id' => 7, 'name' => 'Owners Equity', 'account_type' => 'equity', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '408001', 'shoa_id' => 8, 'name' => 'Sales Revenue', 'account_type' => 'revenue', 'receivables' => 0.00, 'payables' => 0.00],
            ['account_code' => '509001', 'shoa_id' => 9, 'name' => 'Cost of Goods Sold', 'account_type' => 'cogs', 'receivables' => 0.00, 'payables' => 0.00],
        ];

        foreach ($coaData as $data) {
            ChartOfAccounts::create(array_merge($data, [
                'opening_date' => '2026-01-19',
                'credit_limit' => 0.00,
                'remarks'      => null,
                'address'      => null,
                'phone_no'     => null,
                'created_by'   => $userId,
                'updated_by'   => $userId,
            ]));
        }

        // ---------------------
        // Measurement Units
        // ---------------------
        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Pieces', 'shortcode' => 'pcs'],
            ['id' => 2, 'name' => 'Carton', 'shortcode' => 'ct'],
            ['id' => 3, 'name' => 'Set', 'shortcode' => 'set'],
            ['id' => 4, 'name' => 'Pair', 'shortcode' => 'pair'],
            ['id' => 5, 'name' => 'Yards', 'shortcode' => 'yrds'],
        ]);

        // ---------------------
        // Product Categories
        // ---------------------
        ProductCategory::insert([
            ['id' => 1, 'name' => 'Complete Chair', 'code' => 'complete-chair', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Visitor Chair', 'code' => 'visitor-chair', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Kit', 'code' => 'kit', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Part', 'code' => 'part', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Stool', 'code' => 'stool', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Cafe Chair', 'code' => 'cafe-chair', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Office Table', 'code' => 'office-table', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Dining Table', 'code' => 'dining-table', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'name' => 'Dining Chair', 'code' => 'dining-chair', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10,'name' => 'Gaming Chair', 'code' => 'gaming-chair', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->command->info("Database seeded successfully!");
    }
}
