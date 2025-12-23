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
        Schema::create('production_receiving_details', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->unsignedBigInteger('production_receiving_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id')->nullable();

            // Costing
            $table->decimal('manufacturing_cost', 10, 2)->default(0); // manual or fixed cost per unit
            $table->decimal('received_qty', 10, 2); // total received quantity

            // Utility
            $table->text('remarks')->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('production_receiving_id')->references('id')->on('production_receivings')->onDelete('cascade'); 
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('product_variations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_receiving_details');
    }
};
