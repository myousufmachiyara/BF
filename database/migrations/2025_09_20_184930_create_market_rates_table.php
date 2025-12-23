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
        Schema::create('market_rates', function (Blueprint $table) {
            $table->id();

            // Relations
            $table->foreignId('category_id')->constrained('product_categories')->onDelete('cascade');
            $table->foreignId('subcategory_id')->nullable()->constrained('product_subcategories')->onDelete('cascade');
            $table->foreignId('shape_id')->nullable()->constrained('attribute_values')->onDelete('cascade');
            $table->foreignId('size_id')->nullable()->constrained('attribute_values')->onDelete('cascade');

            // Rate
            $table->decimal('rate', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_rates');
    }
};
