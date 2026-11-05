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
        Schema::create('keuangan_wallet_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('keuangan_wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('view'); // 'view' or 'edit'
            $table->string('status', 20)->default('pending'); // 'pending', 'accepted', 'rejected'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['wallet_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['wallet_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keuangan_wallet_shares');
    }
};
