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
        Schema::table('referral_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('referral_logs', 'status')) {
                $table->string('status', 20)->default('valid')->after('applied_app');
            }
            if (! Schema::hasColumn('referral_logs', 'fraud_reason')) {
                $table->string('fraud_reason', 255)->nullable()->after('status');
            }
            if (! Schema::hasColumn('referral_logs', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('fraud_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('referral_logs', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_logs', function (Blueprint $table) {
            if (Schema::hasColumn('referral_logs', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('referral_logs', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('referral_logs', 'fraud_reason')) {
                $table->dropColumn('fraud_reason');
            }
            if (Schema::hasColumn('referral_logs', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
