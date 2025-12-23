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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->date('invoice_date');
            $table->string('payment_terms')->nullable();
            $table->string('bill_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('convance_charges', 15, 2)->default(0);
            $table->decimal('labour_charges', 15, 2)->default(0);
            $table->decimal('bill_discount', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
