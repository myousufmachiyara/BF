<?php

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\DB;

if (!function_exists('generateGlobalBarcode')) {
    
    function generateGlobalBarcode($prefix = 'PRD-')
    {
        return DB::transaction(function () use ($prefix) {
            $sequence = DB::table('barcode_sequences')
                ->where('prefix', 'GLOBAL')
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                DB::table('barcode_sequences')->insert([
                    'prefix' => 'GLOBAL',
                    'next_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = DB::table('barcode_sequences')
                    ->where('prefix', 'GLOBAL')
                    ->first();
            }

            $next = $sequence->next_number;

            DB::table('barcode_sequences')
                ->where('prefix', 'GLOBAL')
                ->update([
                    'next_number' => $next + 1,
                    'updated_at' => now(),
                ]);

            return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
        });
    }
}