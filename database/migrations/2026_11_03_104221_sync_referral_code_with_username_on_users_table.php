<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $username = $user->username;
            if (empty($username)) {
                $base = Str::slug(explode('@', $user->email)[0] ?: 'user', '_');
                $candidate = $base;
                $counter = 1;
                while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                    $candidate = $base.$counter;
                    $counter++;
                }
                $username = strtolower($candidate);
            } else {
                $username = strtolower($username);
            }

            DB::table('users')->where('id', $user->id)->update([
                'username' => $username,
                'referral_code' => $username,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback
    }
};
