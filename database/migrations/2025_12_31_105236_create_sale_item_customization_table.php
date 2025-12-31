<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_item_customization', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_invoice_id');
            $table->unsignedBigInteger('sale_invoice_items_id');
            $table->unsignedBigInteger('item_id');
            $table->timestamps();

            $table->foreign('sale_invoice_id')->references('id')->on('sale_invoices');
            $table->foreign('sale_invoice_items_id')->references('id')->on('sale_invoice_items');
            $table->foreign('item_id')->references('id')->on('products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_item_customization');
    }
};
