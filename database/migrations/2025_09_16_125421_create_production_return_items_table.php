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
        Schema::create('production_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_return_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('production_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->decimal('quantity', 15, 2);
            $table->decimal('price', 15, 2)->default(0); // if costing needs to be recorded
            $table->timestamps();

            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('production_return_id')->references('id')->on('production_returns')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('unit_id')->references('id')->on('measurement_units')->onDelete('cascade');
            $table->foreign('variation_id')->references('id')->on('product_variations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_return_items');
    }
};
