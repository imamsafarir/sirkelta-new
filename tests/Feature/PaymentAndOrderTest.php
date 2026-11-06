<?php

use App\Models\PaymentOrder;
use App\Models\ReferralLog;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('superadmin can update payment method settings', function () {
    Storage::fake('public');

    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();

    $qrisFile = UploadedFile::fake()->image('qris.png', 300, 300);

    $response = $this->actingAs($superadmin)->post(route('admin.website.payment.update'), [
        'payment_manual_enabled' => 1,
        'payment_bank_name' => 'Bank Mandiri',
        'payment_bank_account_number' => '1230004567890',
        'payment_bank_account_holder' => 'PT Sirkelta Digital',
        'payment_qris_merchant_name' => 'SIRKELTA QRIS',
        'qris_image' => $qrisFile,
        'payment_midtrans_enabled' => 1,
        'payment_midtrans_mode' => 'sandbox',
        'payment_midtrans_client_key' => 'SB-Mid-client-sample-key',
        'payment_midtrans_server_key' => 'SB-Mid-server-sample-key',
        'payment_midtrans_merchant_id' => 'G12345678',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(WebsiteSetting::get('payment_bank_name'))->toBe('Bank Mandiri')
        ->and(WebsiteSetting::get('payment_bank_account_number'))->toBe('1230004567890')
        ->and(WebsiteSetting::get('payment_midtrans_enabled'))->toBe('1')
        ->and(WebsiteSetting::get('payment_midtrans_mode'))->toBe('sandbox')
        ->and(WebsiteSetting::get('payment_midtrans_client_key'))->toBe('SB-Mid-client-sample-key')
        ->and(WebsiteSetting::get('payment_midtrans_server_key'))->toBe('SB-Mid-server-sample-key')
        ->and(WebsiteSetting::get('payment_midtrans_merchant_id'))->toBe('G12345678');
});

test('superadmin can update payment settings with stringified booleans from formdata', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();

    $response = $this->actingAs($superadmin)->post(route('admin.website.payment.update'), [
        'payment_manual_enabled' => 'false',
        'payment_midtrans_enabled' => 'true',
        'payment_midtrans_mode' => 'production',
        'payment_midtrans_client_key' => 'Mid-client-live-key',
        'payment_midtrans_server_key' => 'Mid-server-live-key',
        'payment_midtrans_merchant_id' => 'M987654321',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(WebsiteSetting::get('payment_manual_enabled'))->toBe('0')
        ->and(WebsiteSetting::get('payment_midtrans_enabled'))->toBe('1')
        ->and(WebsiteSetting::get('payment_midtrans_mode'))->toBe('production')
        ->and(WebsiteSetting::get('payment_midtrans_client_key'))->toBe('Mid-client-live-key')
        ->and(WebsiteSetting::get('payment_midtrans_server_key'))->toBe('Mid-server-live-key')
        ->and(WebsiteSetting::get('payment_midtrans_merchant_id'))->toBe('M987654321');
});

test('non-superadmin cannot update payment settings or view payment management', function () {
    $regularUser = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $responseUpdate = $this->actingAs($regularUser)->post(route('admin.website.payment.update'), [
        'payment_manual_enabled' => 1,
        'payment_midtrans_enabled' => 0,
        'payment_midtrans_mode' => 'sandbox',
    ]);
    $responseUpdate->assertForbidden();

    $responseIndex = $this->actingAs($regularUser)->get(route('admin.payments.index'));
    $responseIndex->assertForbidden();
});

test('user can submit a manual payment order for subscription', function () {
    Storage::fake('public');

    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $proof = UploadedFile::fake()->image('struk_transfer.jpg', 400, 600);

    $response = $this->actingAs($user)->post(route('profile.payment.order'), [
        'module' => 'keuangan',
        'duration_months' => 3,
        'payment_method' => 'manual_bank',
        'payment_proof' => $proof,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order = PaymentOrder::where('user_id', $user->id)->latest()->first();
    expect($order)->not->toBeNull()
        ->and($order->module)->toBe('keuangan')
        ->and($order->duration_months)->toBe(3)
        ->and($order->payment_method)->toBe('manual_bank')
        ->and($order->status)->toBe('pending')
        ->and($order->payment_proof_path)->not->toBeNull();
});

test('user order with 100% referral discount is immediately auto-approved', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    // Give user 100.000 referral discount
    ReferralLog::create([
        'referrer_id' => $user->id,
        'referee_id' => User::factory()->create()->id,
        'reward_discount' => 100000,
        'is_redeemed' => false,
        'status' => 'valid',
    ]);

    // 1 Month Sirkelta Keuangan is 75.000
    $response = $this->actingAs($user)->post(route('profile.payment.order'), [
        'module' => 'keuangan',
        'duration_months' => 1,
        'payment_method' => 'manual_bank',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order = PaymentOrder::where('user_id', $user->id)->latest()->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('paid')
        ->and($order->final_amount)->toEqual(0)
        ->and($order->discount_applied)->toEqual(75000);

    // Ensure pro subscription was generated
    $sub = UserSubscription::where('user_id', $user->id)->where('module', 'keuangan')->latest()->first();
    expect($sub)->not->toBeNull()
        ->and($sub->plan)->toBe('pro')
        ->and($sub->status)->toBe('active');
});

test('superadmin can view, filter, and search in payment orders management', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    PaymentOrder::create([
        'order_number' => 'ORD-TEST-001',
        'user_id' => $user->id,
        'module' => 'keuangan',
        'duration_months' => 6,
        'gross_amount' => 375000,
        'discount_applied' => 0,
        'final_amount' => 375000,
        'payment_method' => 'manual_bank',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.payments.index', [
        'search' => 'ORD-TEST-001',
        'status' => 'pending',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Payments/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.order_number', 'ORD-TEST-001')
        ->has('metrics')
        ->where('metrics.pending_orders', 1)
    );
});

test('superadmin can approve a manual pending payment order', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $user = User::factory()->create([
        'username' => 'testbuyer',
    ]);

    $order = PaymentOrder::create([
        'order_number' => 'ORD-TEST-APPROVE',
        'user_id' => $user->id,
        'module' => 'keuangan',
        'duration_months' => 3,
        'gross_amount' => 200000,
        'discount_applied' => 0,
        'final_amount' => 200000,
        'payment_method' => 'manual_bank',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($superadmin)->post(route('admin.payments.approve', $order->id));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->verified_by)->toBe($superadmin->id);

    // Verify user role and subscription
    $user->refresh();
    expect($user->hasRole('user_keuangan'))->toBeTrue();

    $subscription = UserSubscription::where('user_id', $user->id)->where('module', 'keuangan')->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->plan)->toBe('pro')
        ->and($subscription->status)->toBe('active')
        ->and($subscription->duration_months)->toBe(3);
});

test('superadmin can reject a pending payment order with reason', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $order = PaymentOrder::create([
        'order_number' => 'ORD-TEST-REJECT',
        'user_id' => $user->id,
        'module' => 'keuangan',
        'duration_months' => 1,
        'gross_amount' => 75000,
        'discount_applied' => 0,
        'final_amount' => 75000,
        'payment_method' => 'manual_bank',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($superadmin)->post(route('admin.payments.reject', $order->id), [
        'rejection_reason' => 'Bukti mutasi transfer bank tidak ditemukan.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $order->refresh();
    expect($order->status)->toBe('rejected')
        ->and($order->rejection_reason)->toBe('Bukti mutasi transfer bank tidak ditemukan.');
});

test('midtrans webhook automatically marks order as paid with valid signature', function () {
    WebsiteSetting::set('payment_midtrans_server_key', 'test-server-key');

    $user = User::factory()->create();

    $order = PaymentOrder::create([
        'order_number' => 'ORD-MIDTRANS-999',
        'user_id' => $user->id,
        'module' => 'wasender',
        'duration_months' => 1,
        'gross_amount' => 99000,
        'discount_applied' => 0,
        'final_amount' => 99000,
        'payment_method' => 'midtrans',
        'status' => 'pending',
    ]);

    $statusCode = '200';
    $grossAmount = '99000.00';
    $serverKey = 'test-server-key';
    $signature = hash('sha512', $order->order_number.$statusCode.$grossAmount.$serverKey);

    $response = $this->postJson('/api/midtrans/webhook', [
        'order_id' => $order->order_number,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-midtrans-abc-123',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $order->refresh();
    expect($order->status)->toBe('paid')
        ->and($order->midtrans_transaction_id)->toBe('trx-midtrans-abc-123');

    $sub = UserSubscription::where('user_id', $user->id)->where('module', 'wasender')->first();
    expect($sub)->not->toBeNull()
        ->and($sub->plan)->toBe('pro')
        ->and($sub->status)->toBe('active');
});

test('midtrans webhook responds 200 ok for healthcheck get request and dashboard test notification', function () {
    // 1. GET request check
    $responseGet = $this->get('/api/midtrans/webhook');
    $responseGet->assertOk();
    $responseGet->assertJson(['status' => 'ok']);

    // 2. Ping without order_id
    $responsePing = $this->postJson('/api/midtrans/webhook', []);
    $responsePing->assertOk();
    $responsePing->assertJson(['status' => 'ok']);

    // 3. Test ping with dummy order_id from Midtrans Dashboard (e.g. test-order-1234)
    $responseTest = $this->postJson('/api/midtrans/webhook', [
        'order_id' => 'test-transaction-dashboard-1234',
        'status_code' => '200',
        'gross_amount' => '10000.00',
        'transaction_status' => 'settlement',
    ]);
    $responseTest->assertOk();
    $responseTest->assertJson(['status' => 'ok']);
});

test('user cannot create paid payment order when all payment methods are disabled', function () {
    WebsiteSetting::set('payment_manual_enabled', '0');
    WebsiteSetting::set('payment_midtrans_enabled', '0');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('profile.payment.order'), [
        'module' => 'keuangan',
        'duration_months' => 1,
        'payment_method' => 'manual_bank',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Layanan transaksi pembayaran saat ini sedang dinonaktifkan sementara karena sedang ada perbaikan. Mohon coba beberapa saat lagi.');

    expect(PaymentOrder::where('user_id', $user->id)->count())->toBe(0);
});
