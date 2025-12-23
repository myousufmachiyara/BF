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
        Schema::create('production_receivings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id')->nullable();
            $table->unsignedBigInteger('vendor_id');
            $table->date('rec_date');
            $table->string('grn_no')->unique();
            $table->decimal('convance_charges', 10, 2)->default(0);
            $table->decimal('bill_discount', 10, 2)->default(0);
            $table->unsignedBigInteger('received_by');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('vendor_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('received_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_receivings');
    }
};
