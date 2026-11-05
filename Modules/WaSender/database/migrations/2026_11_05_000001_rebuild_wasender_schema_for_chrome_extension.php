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
        // 1. Drop obsolete wasender_devices table
        Schema::dropIfExists('wasender_devices');

        // 2. Ensure wasender_message_templates has has_random_code
        if (Schema::hasTable('wasender_message_templates') && ! Schema::hasColumn('wasender_message_templates', 'has_random_code')) {
            Schema::table('wasender_message_templates', function (Blueprint $table) {
                $table->boolean('has_random_code')->default(false)->after('content');
            });
        }

        // 3. Create wasender_extension_sessions table
        if (! Schema::hasTable('wasender_extension_sessions')) {
            Schema::create('wasender_extension_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('session_token_hash', 64)->unique();
                $table->string('device_name')->nullable();
                $table->string('device_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->dateTime('last_heartbeat_at')->nullable()->index();
                $table->dateTime('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // 4. Create wasender_daily_usages table
        if (! Schema::hasTable('wasender_daily_usages')) {
            Schema::create('wasender_daily_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('usage_date');
                $table->unsignedInteger('messages_sent')->default(0);
                $table->unsignedInteger('files_attached')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'usage_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wasender_daily_usages');
        Schema::dropIfExists('wasender_extension_sessions');

        if (Schema::hasTable('wasender_message_templates') && Schema::hasColumn('wasender_message_templates', 'has_random_code')) {
            Schema::table('wasender_message_templates', function (Blueprint $table) {
                $table->dropColumn('has_random_code');
            });
        }
    }
};
