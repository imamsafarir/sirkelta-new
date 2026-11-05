<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Keuangan\Models\Transaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('mutating request with same idempotency key is not duplicated and returns cached response', function () {
    $user = User::factory()->create(['username' => 'idempotent_user']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Operasional',
        'current_balance' => 1000000,
        'initial_balance' => 1000000,
    ]);

    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'wallet_id' => $wallet->id,
        'type' => 'expense',
        'amount' => 50000,
        'category' => 'Operasional',
        'description' => 'Pembelian ATK Kantor',
        'transaction_date' => now()->toDateString(),
    ];

    // Request 1: First submission (must create transaction)
    $response1 = $this->actingAs($user)
        ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
        ->post(route('keuangan.transactions.store'), $payload);

    $response1->assertRedirect();
    expect(Transaction::where('wallet_id', $wallet->id)->count())->toBe(1);
    expect((float) $wallet->fresh()->current_balance)->toBe(950000.0);

    // Request 2: Duplicate retry with exact same idempotency key (simulating network retry / panic click)
    $response2 = $this->actingAs($user)
        ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
        ->post(route('keuangan.transactions.store'), $payload);

    $response2->assertRedirect();
    $response2->assertHeader('X-Idempotent-Replay', 'true');

    // VERIFY: Transaction is NOT duplicated, balance remains 950000!
    expect(Transaction::where('wallet_id', $wallet->id)->count())->toBe(1);
    expect((float) $wallet->fresh()->current_balance)->toBe(950000.0);
});

test('mutating requests with different idempotency keys are both processed', function () {
    $user = User::factory()->create(['username' => 'idempotent_user_2']);
    $user->assignRole('user_keuangan');

    $wallet = $user->wallets()->create([
        'name' => 'Dompet Tabungan',
        'current_balance' => 500000,
        'initial_balance' => 500000,
    ]);

    $payload = [
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'amount' => 100000,
        'category' => 'Gaji',
        'description' => 'Bonus',
        'transaction_date' => now()->toDateString(),
    ];

    // Request 1 with key A
    $this->actingAs($user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->post(route('keuangan.transactions.store'), $payload)
        ->assertRedirect();

    expect(Transaction::where('wallet_id', $wallet->id)->count())->toBe(1);

    // Request 2 with key B (a fresh intentional transaction)
    $this->actingAs($user)
        ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
        ->post(route('keuangan.transactions.store'), $payload)
        ->assertRedirect();

    expect(Transaction::where('wallet_id', $wallet->id)->count())->toBe(2);
    expect((float) $wallet->fresh()->current_balance)->toBe(700000.0);
});
