<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code', 50)->nullable()->unique()->after('password');
            }
            if (! Schema::hasColumn('users', 'referred_by')) {
                $table->foreignId('referred_by')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill existing users with unique usernames and referral codes
        $existingUsers = DB::table('users')->get();
        foreach ($existingUsers as $u) {
            $username = $u->username;
            if (empty($username)) {
                $base = Str::slug(explode('@', $u->email)[0] ?: 'user', '_');
                $candidate = $base;
                $counter = 1;
                while (DB::table('users')->where('username', $candidate)->where('id', '!=', $u->id)->exists()) {
                    $candidate = $base.$counter;
                    $counter++;
                }
                $username = $candidate;
            }

            $referralCode = $u->referral_code;
            if (empty($referralCode)) {
                do {
                    $referralCode = strtoupper(Str::random(8));
                } while (DB::table('users')->where('referral_code', $referralCode)->where('id', '!=', $u->id)->exists());
            }

            DB::table('users')->where('id', $u->id)->update([
                'username' => $username,
                'referral_code' => $referralCode,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'referred_by')) {
                $table->dropForeign(['referred_by']);
                $table->dropColumn('referred_by');
            }
            if (Schema::hasColumn('users', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }
};
