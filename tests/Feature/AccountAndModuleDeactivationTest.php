<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\WalletShare;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('user can deactivate keuangan module with exact confirmation text HAPUS KEUANGAN', function () {
    $user = User::factory()->create([
        'username' => 'testuser_keuangan',
        'monthly_budget' => 5000000,
        'last_module' => 'keuangan',
    ]);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Kasir',
        'current_balance' => 1000000,
        'initial_balance' => 1000000,
    ]);

    $transaction = Transaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => 'income',
        'amount' => 500000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Penjualan',
    ]);

    $share = WalletShare::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'shared_by' => $user->id,
        'role' => 'view',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->post(route('profile.modules.deactivate'), [
        'module' => 'keuangan',
        'confirmation_text' => 'HAPUS KEUANGAN',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();

    // Verify role is removed
    expect($user->hasRole('user_keuangan'))->toBeFalse();
    expect($user->monthly_budget)->toBeNull();

    // Verify all module data is wiped
    $this->assertDatabaseMissing('keuangan_wallets', ['id' => $wallet->id]);
    $this->assertDatabaseMissing('keuangan_transactions', ['id' => $transaction->id]);
    $this->assertDatabaseMissing('keuangan_wallet_shares', ['id' => $share->id]);
});

test('deactivating module fails when confirmation text is mismatched', function () {
    $user = User::factory()->create([
        'username' => 'testuser_mismatch',
    ]);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Aman',
        'current_balance' => 200000,
        'initial_balance' => 200000,
    ]);

    $response = $this->actingAs($user)->post(route('profile.modules.deactivate'), [
        'module' => 'keuangan',
        'confirmation_text' => 'HAPUS SEMUA', // wrong text
    ]);

    $response->assertSessionHasErrors('confirmation_text');

    $user->refresh();
    expect($user->hasRole('user_keuangan'))->toBeTrue();
    $this->assertDatabaseHas('keuangan_wallets', ['id' => $wallet->id]);
});

test('user can deactivate wasender module with exact confirmation text HAPUS WASENDER', function () {
    $user = User::factory()->create([
        'username' => 'testuser_wasender',
        'last_module' => 'wasender',
    ]);
    $user->assignRole('user_wasender');

    $response = $this->actingAs($user)->post(route('profile.modules.deactivate'), [
        'module' => 'wasender',
        'confirmation_text' => 'HAPUS WASENDER',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect($user->hasRole('user_wasender'))->toBeFalse();
});

test('user cannot delete account with invalid current password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'current_password' => 'wrongpassword',
        'confirmation_text' => 'HAPUS AKUN SAYA',
    ]);

    $response->assertSessionHasErrors('current_password');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    expect(Auth::check())->toBeTrue();
});

test('user cannot delete account with incorrect confirmation text', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'current_password' => 'secret123',
        'confirmation_text' => 'HAPUS AKUN', // incomplete
    ]);

    $response->assertSessionHasErrors('confirmation_text');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    expect(Auth::check())->toBeTrue();
});

test('user can permanently delete their account with valid password and confirmation text', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Tabungan',
        'current_balance' => 750000,
        'initial_balance' => 750000,
    ]);

    $transaction = Transaction::create([
        'wallet_id' => $wallet->id,
        'user_id' => $user->id,
        'type' => 'expense',
        'amount' => 50000,
        'transaction_date' => now()->toDateString(),
        'description' => 'Makan',
    ]);

    $response = $this->actingAs($user)->delete(route('profile.destroy'), [
        'current_password' => 'password123',
        'confirmation_text' => 'HAPUS AKUN SAYA',
    ]);

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('success');

    expect(Auth::check())->toBeFalse();

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('keuangan_wallets', ['id' => $wallet->id]);
    $this->assertDatabaseMissing('keuangan_transactions', ['id' => $transaction->id]);
});

test('sole superadmin cannot delete their own account', function () {
    // The seeder creates a superadmin
    $superadmin = User::role('superadmin')->first();

    $response = $this->actingAs($superadmin)->delete(route('profile.destroy'), [
        'current_password' => 'password',
        'confirmation_text' => 'HAPUS AKUN SAYA',
    ]);

    $response->assertSessionHasErrors('account');
    $this->assertDatabaseHas('users', ['id' => $superadmin->id]);
});
