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
        Schema::create('purchase_bilty', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->unsignedBigInteger('vendor_id');
            $table->date('bilty_date');
            $table->string('ref_no')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('bilty_amount', 15, 2);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('purchase_id')->references('id')->on('purchase_invoices')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_bilty');
    }
};
