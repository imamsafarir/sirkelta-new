<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Modules\Keuangan\Jobs\ProcessBulkTransactionDestroyJob;
use Modules\Keuangan\Models\Transaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('dashboard analytics is cached and invalidated on new transaction', function () {
    $user = User::factory()->create(['username' => 'cache_test_user']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Caching',
        'current_balance' => 500000,
        'initial_balance' => 500000,
    ]);

    $cacheKey = "keuangan_dashboard_analytics_{$user->id}";
    Cache::forget($cacheKey);

    // Initial visit: caches the analytics
    $response = $this->actingAs($user)->get(route('keuangan.dashboard'));
    $response->assertOk();
    expect(Cache::has($cacheKey))->toBeTrue();

    // Storing a transaction must bust the cache
    $this->actingAs($user)->post(route('keuangan.transactions.store'), [
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'amount' => 100000,
        'category' => 'Gaji',
        'transaction_date' => now()->toDateString(),
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
    expect((float) $wallet->fresh()->current_balance)->toBe(600000.0);
});

test('bulk deletion with <= 50 transactions is processed immediately with reconciled balance', function () {
    $user = User::factory()->create(['username' => 'bulk_sync_user']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Bulk Sync',
        'current_balance' => 1000000,
        'initial_balance' => 1000000,
    ]);

    $tx1 = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'expense',
        'amount' => 50000,
        'transaction_date' => now()->toDateString(),
    ]);

    $tx2 = Transaction::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'amount' => 150000,
        'transaction_date' => now()->toDateString(),
    ]);

    // Initial balance adjusted for tx1 & tx2: 1000000 - 50000 + 150000 = 1100000
    $wallet->update(['current_balance' => 1100000]);

    $response = $this->actingAs($user)->post(route('keuangan.transactions.bulk-destroy'), [
        'ids' => [$tx1->id, $tx2->id],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Deleting tx1 (expense 50000) should increase balance by 50000
    // Deleting tx2 (income 150000) should decrease balance by 150000
    // Net adjustment: 1100000 + 50000 - 150000 = 1000000
    expect((float) $wallet->fresh()->current_balance)->toBe(1000000.0);
    expect(Transaction::whereIn('id', [$tx1->id, $tx2->id])->count())->toBe(0);
});

test('bulk deletion with > 50 transactions is pushed to background queue', function () {
    Queue::fake();

    $user = User::factory()->create(['username' => 'bulk_queue_user']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Bulk Queue',
        'current_balance' => 2000000,
        'initial_balance' => 2000000,
    ]);

    $ids = [];
    for ($i = 0; $i < 55; $i++) {
        $tx = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
        ]);
        $ids[] = $tx->id;
    }

    $response = $this->actingAs($user)->post(route('keuangan.transactions.bulk-destroy'), [
        'ids' => $ids,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    Queue::assertPushed(ProcessBulkTransactionDestroyJob::class, function ($job) use ($user) {
        return $job->userId === $user->id && count($job->transactionIds) === 55;
    });
});

test('ProcessBulkTransactionDestroyJob reconciles balances and removes transactions', function () {
    $user = User::factory()->create(['username' => 'queue_worker_user']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Worker Test',
        'current_balance' => 500000,
        'initial_balance' => 500000,
    ]);

    $ids = [];
    for ($i = 0; $i < 60; $i++) {
        $tx = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'expense',
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
        ]);
        $ids[] = $tx->id;
    }

    // Run the job synchronously
    $job = new ProcessBulkTransactionDestroyJob($ids, $user->id, false);
    $job->handle();

    // 60 expenses of 1000 deleted -> balance increases by 60,000
    expect((float) $wallet->fresh()->current_balance)->toBe(560000.0);
    expect(Transaction::whereIn('id', $ids)->count())->toBe(0);
});

test('rate limiter throttles excessive authentication attempts gracefully', function () {
    // 10 attempts allowed per minute
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('login.attempt'), [
            'username' => 'nonexistent_user',
            'password' => 'wrong_password',
        ]);
    }

    // 11th web attempt must be throttled with redirect and error message in session
    $response = $this->post(route('login.attempt'), [
        'username' => 'nonexistent_user',
        'password' => 'wrong_password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Terlalu banyak percobaan autentikasi. Demi keamanan, mohon tunggu 1 menit sebelum mencoba kembali.');

    // JSON attempt returns 429
    $jsonResponse = $this->postJson(route('login.attempt'), [
        'username' => 'nonexistent_user',
        'password' => 'wrong_password',
    ]);

    $jsonResponse->assertStatus(429);
    $jsonResponse->assertJsonFragment([
        'message' => 'Terlalu banyak percobaan autentikasi. Demi keamanan, mohon tunggu 1 menit sebelum mencoba kembali.',
    ]);
});
