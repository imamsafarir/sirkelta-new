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
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module', 50); // 'keuangan', 'wasender'
            $table->integer('duration_months')->default(1);
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('discount_applied', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->string('payment_method', 50)->nullable(); // 'manual_bank', 'manual_qris', 'midtrans'
            $table->string('payment_proof_path', 255)->nullable();
            $table->string('status', 30)->default('pending'); // 'pending', 'paid', 'rejected', 'expired', 'cancelled'
            $table->text('rejection_reason')->nullable();
            $table->string('midtrans_snap_token', 255)->nullable();
            $table->string('midtrans_transaction_id', 255)->nullable();
            $table->string('midtrans_payment_type', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['module', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
