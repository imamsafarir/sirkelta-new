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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module', 50); // 'keuangan', 'wasender'
            $table->string('plan', 50)->default('pro');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('duration_months')->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('discount_applied', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('active'); // active, expired, cancelled
            $table->timestamps();

            $table->index(['user_id', 'module', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
