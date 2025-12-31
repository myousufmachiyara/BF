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
        Schema::create('purchase_bilty_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bilty_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 15, 2);
            $table->unsignedBigInteger('unit');

            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->foreign('unit')->references('id')->on('measurement_units')->onDelete('cascade');
            $table->foreign('bilty_id')->references('id')->on('purchase_bilty')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_bilty_details');
    }
};
