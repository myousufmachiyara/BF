<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post_dated_cheques', function (Blueprint $table) {
            $table->id();
            $table->string('cheque_number')->unique();
            $table->date('cheque_date');
            $table->decimal('amount', 15, 2);
            $table->string('bank_name');
            
            // Updated line: Link to Chart of Accounts
            $table->foreignId('coa_id')->constrained('chart_of_accounts')->onDelete('cascade');
            
            $table->enum('status', ['received', 'deposited', 'cleared', 'bounced', 'cancelled'])->default('received');
            $table->date('deposited_at')->nullable();
            $table->date('cleared_at')->nullable();
            $table->text('remarks')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void { Schema::dropIfExists('post_dated_cheques'); }
};