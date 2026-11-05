<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('user A can invite user B to share a wallet with view or edit permission', function () {
    $userA = User::factory()->create([
        'username' => 'usera',
        'email' => 'usera@example.test',
    ]);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create([
        'username' => 'userb',
        'email' => 'userb@example.test',
    ]);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Kas Toko A',
        'current_balance' => 500000,
        'initial_balance' => 500000,
    ]);

    // User A invites User B with role 'edit'
    $response = $this->actingAs($userA)->post(route('keuangan.wallets.shares.store', $walletA->id), [
        'username' => 'userb',
        'role' => 'edit',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('keuangan_wallet_shares', [
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'edit',
        'status' => 'pending',
    ]);
});

test('user cannot invite themselves to share a wallet', function () {
    $userA = User::factory()->create([
        'username' => 'usera_self',
        'email' => 'usera_self@example.test',
    ]);
    $userA->assignRole('user_keuangan');

    $wallet = $userA->wallets()->create([
        'name' => 'Dompet Mandiri',
        'current_balance' => 100000,
        'initial_balance' => 100000,
    ]);

    $response = $this->actingAs($userA)->post(route('keuangan.wallets.shares.store', $wallet->id), [
        'username' => 'usera_self',
        'role' => 'view',
    ]);

    $response->assertSessionHasErrors('username');
});

test('user B must accept invitation before shared wallet is active and visible', function () {
    $userA = User::factory()->create(['username' => 'owner_a']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'receiver_b']);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Dompet Usaha',
        'current_balance' => 1000000,
        'initial_balance' => 1000000,
    ]);

    $share = WalletShare::create([
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'edit',
        'status' => 'pending',
        'is_active' => true,
    ]);

    // Check user B wallet index shows pending invitation
    $responseB = $this->actingAs($userB)->get(route('keuangan.wallets.index'));
    $responseB->assertOk();
    $responseB->assertInertia(fn ($page) => $page
        ->component('Keuangan::Wallets/Index', false)
        ->has('pendingShares', 1)
        ->has('sharedWallets', 0)
    );

    // User B accepts the invitation
    $acceptResponse = $this->actingAs($userB)->post(route('keuangan.wallets.shares.accept', $share->id));
    $acceptResponse->assertRedirect();
    $acceptResponse->assertSessionHas('success');

    $this->assertDatabaseHas('keuangan_wallet_shares', [
        'id' => $share->id,
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // Now user B sees it in sharedWallets
    $responseAfterAccept = $this->actingAs($userB)->get(route('keuangan.wallets.index'));
    $responseAfterAccept->assertOk();
    $responseAfterAccept->assertInertia(fn ($page) => $page
        ->component('Keuangan::Wallets/Index', false)
        ->has('pendingShares', 0)
        ->has('sharedWallets', 1)
    );
});

test('user B can toggle active status of accepted shared wallet', function () {
    $userA = User::factory()->create(['username' => 'toggle_a']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'toggle_b']);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Kas Toggle',
        'current_balance' => 200000,
        'initial_balance' => 200000,
    ]);

    $share = WalletShare::create([
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'view',
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // User B turns off the wallet
    $toggleOff = $this->actingAs($userB)->patch(route('keuangan.wallets.shares.toggle', $share->id));
    $toggleOff->assertRedirect();
    expect($share->fresh()->is_active)->toBeFalse();

    // User B turns on the wallet
    $toggleOn = $this->actingAs($userB)->patch(route('keuangan.wallets.shares.toggle', $share->id));
    $toggleOn->assertRedirect();
    expect($share->fresh()->is_active)->toBeTrue();
});

test('user B with view permission cannot add transactions to shared wallet', function () {
    $userA = User::factory()->create(['username' => 'view_a']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'view_b']);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Kas View Only',
        'current_balance' => 300000,
        'initial_balance' => 300000,
    ]);

    WalletShare::create([
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'view',
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // User B tries to record an expense into wallet A
    $response = $this->actingAs($userB)->post(route('keuangan.transactions.store'), [
        'wallet_id' => $walletA->id,
        'type' => 'expense',
        'amount' => 50000,
        'category' => 'Operasional',
        'description' => 'Beli ATK',
        'transaction_date' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors('wallet_id');
    expect(Transaction::where('wallet_id', $walletA->id)->count())->toBe(0);
});

test('user B with edit permission can add income and expense to shared wallet', function () {
    $userA = User::factory()->create(['username' => 'edit_a']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'edit_b']);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Kas Toko Bersama',
        'current_balance' => 300000,
        'initial_balance' => 300000,
    ]);

    WalletShare::create([
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'edit',
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // User B records income
    $responseIncome = $this->actingAs($userB)->post(route('keuangan.transactions.store'), [
        'wallet_id' => $walletA->id,
        'type' => 'income',
        'amount' => 150000,
        'category' => 'Penjualan',
        'description' => 'Penjualan Kasir B',
        'transaction_date' => now()->toDateString(),
    ]);

    $responseIncome->assertRedirect();
    $responseIncome->assertSessionHas('success');

    expect((float) $walletA->fresh()->current_balance)->toBe(450000.0);

    // Verify transaction has user_id = userB
    $tx = Transaction::where('wallet_id', $walletA->id)->latest('id')->first();
    expect($tx->user_id)->toBe($userB->id);
    expect((float) $tx->amount)->toBe(150000.0);
});

test('strict rule: transactions recorded by user A can only be edited or deleted by user A, not user B', function () {
    $userA = User::factory()->create(['username' => 'boss_a']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'staff_b']);
    $userB->assignRole('user_keuangan');

    $walletA = $userA->wallets()->create([
        'name' => 'Kas Operasional',
        'current_balance' => 1000000,
        'initial_balance' => 1000000,
    ]);

    WalletShare::create([
        'wallet_id' => $walletA->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'edit',
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // User A records a transaction
    $txA = Transaction::create([
        'user_id' => $userA->id,
        'wallet_id' => $walletA->id,
        'type' => 'income',
        'amount' => 500000,
        'category' => 'Modal',
        'description' => 'Setoran Modal Pemilik A',
        'transaction_date' => now()->toDateString(),
    ]);

    // 1. User B tries to UPDATE user A's transaction -> Forbidden 403
    $updateAttempt = $this->actingAs($userB)->put(route('keuangan.transactions.update', $txA->id), [
        'wallet_id' => $walletA->id,
        'type' => 'income',
        'amount' => 999999,
        'transaction_date' => now()->toDateString(),
    ]);
    $updateAttempt->assertForbidden();

    // 2. User B tries to DELETE user A's transaction -> Forbidden 403
    $deleteAttempt = $this->actingAs($userB)->delete(route('keuangan.transactions.destroy', $txA->id));
    $deleteAttempt->assertForbidden();

    // 3. User A updates their own transaction -> Success
    $userAUpdate = $this->actingAs($userA)->put(route('keuangan.transactions.update', $txA->id), [
        'wallet_id' => $walletA->id,
        'type' => 'income',
        'amount' => 600000,
        'category' => 'Modal Baru',
        'description' => 'Diubah oleh Pemilik A',
        'transaction_date' => now()->toDateString(),
    ]);
    $userAUpdate->assertRedirect();
    expect((float) $txA->fresh()->amount)->toBe(600000.0);
});

test('user B cannot update or delete user A wallet', function () {
    $userA = User::factory()->create(['username' => 'wallet_owner']);
    $userA->assignRole('user_keuangan');

    $userB = User::factory()->create(['username' => 'wallet_guest']);
    $userB->assignRole('user_keuangan');

    $wallet = $userA->wallets()->create([
        'name' => 'Dompet Rahasia',
        'current_balance' => 500000,
        'initial_balance' => 500000,
    ]);

    WalletShare::create([
        'wallet_id' => $wallet->id,
        'user_id' => $userB->id,
        'shared_by' => $userA->id,
        'role' => 'edit',
        'status' => 'accepted',
        'is_active' => true,
    ]);

    // User B tries to update wallet name
    $updateResponse = $this->actingAs($userB)->put(route('keuangan.wallets.update', $wallet->id), [
        'name' => 'Dompet Dibajak',
    ]);
    $updateResponse->assertForbidden();

    // User B tries to delete wallet
    $deleteResponse = $this->actingAs($userB)->delete(route('keuangan.wallets.destroy', $wallet->id));
    $deleteResponse->assertForbidden();
});
