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
        Schema::create('keuangan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('keuangan_wallets')->cascadeOnDelete();
            $table->foreignId('target_wallet_id')->nullable()->constrained('keuangan_wallets')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['income', 'expense', 'debt_payable', 'debt_receivable', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('category', 100)->nullable();
            $table->string('party_name', 255)->nullable();
            $table->date('due_date')->nullable();
            $table->enum('debt_status', ['unpaid', 'partially_paid', 'paid'])->nullable();
            $table->text('description')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_transactions');
    }
};
