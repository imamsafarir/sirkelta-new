<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('keuangan_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('keuangan_wallets', 'icon')) {
                $table->string('icon', 50)->default('wallet')->after('name');
            }
            if (! Schema::hasColumn('keuangan_wallets', 'category_label')) {
                $table->string('category_label', 100)->nullable()->after('icon');
            }
            if (! Schema::hasColumn('keuangan_wallets', 'initial_balance')) {
                $table->decimal('initial_balance', 15, 2)->default(0.00)->after('account_number');
            }
            if (! Schema::hasColumn('keuangan_wallets', 'current_balance')) {
                $table->decimal('current_balance', 15, 2)->default(0.00)->after('initial_balance');
            }
        });

        // If legacy balance column exists, sync current_balance and initial_balance
        if (Schema::hasColumn('keuangan_wallets', 'balance')) {
            DB::statement('UPDATE keuangan_wallets SET current_balance = balance, initial_balance = balance WHERE current_balance = 0');
        }

        // If legacy type column exists, populate category_label
        if (Schema::hasColumn('keuangan_wallets', 'type')) {
            DB::statement("UPDATE keuangan_wallets SET category_label = UPPER(type) WHERE category_label IS NULL OR category_label = ''");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keuangan_wallets', function (Blueprint $table) {
            // Non-destructive reverse
        });
    }
};
