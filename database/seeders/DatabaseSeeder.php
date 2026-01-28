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
        // $coaData = [
        //     ['account_code' => 'A001', 'name' => 'Cash in Hand', 'account_type' => 'cash', 'shoa_id' => 1, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'A002', 'name' => 'Bank ABC', 'account_type' => 'bank', 'shoa_id' => 2, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'A003', 'name' => 'Customer A', 'account_type' => 'customer', 'shoa_id' => 3, 'receivables' => 1000, 'payables' => 0],
        //     ['account_code' => 'A004', 'name' => 'Inventory - Raw Material', 'account_type' => 'asset', 'shoa_id' => 4, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'L001', 'name' => 'Vendor X', 'account_type' => 'vendor', 'shoa_id' => 5, 'receivables' => 0, 'payables' => 500],
        //     ['account_code' => 'L002', 'name' => 'Bank Loan', 'account_type' => 'liability', 'shoa_id' => 6, 'receivables' => 0, 'payables' => 10000],
        //     ['account_code' => 'E001', 'name' => 'Owner Capital', 'account_type' => 'equity', 'shoa_id' => 7, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'R001', 'name' => 'Sales Income', 'account_type' => 'revenue', 'shoa_id' => 8, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'EX001', 'name' => 'Purchase of Goods', 'account_type' => 'expenses', 'shoa_id' => 9, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'EX002', 'name' => 'Salary Expense', 'account_type' => 'expenses', 'shoa_id' => 10, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'EX003', 'name' => 'Rent Expense', 'account_type' => 'expenses', 'shoa_id' => 11, 'receivables' => 0, 'payables' => 0],
        //     ['account_code' => 'EX004', 'name' => 'Utility Expense', 'account_type' => 'expenses', 'shoa_id' => 12, 'receivables' => 0, 'payables' => 0],
        // ];

        // foreach ($coaData as $data) {
        //     ChartOfAccounts::create(array_merge($data, [
        //         'opening_date' => $now,
        //         'credit_limit' => 0,
        //         'remarks' => null,
        //         'address' => null,
        //         'phone_no' => null,
        //         'created_by' => $userId,
        //         'updated_by' => $userId,
        //     ]));
        // }

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
