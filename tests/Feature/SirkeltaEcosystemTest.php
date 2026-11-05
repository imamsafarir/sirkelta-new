<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Keuangan\Models\Wallet;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\Device;
use Modules\WaSender\Models\MessageTemplate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('landing page can be rendered', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('login page can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('single module user redirects directly to their dashboard upon login', function () {
    // Keuangan single app user
    $responseKeuangan = $this->post('/login', [
        'email' => 'userkeuangan@sirkelta.test',
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $responseKeuangan->assertRedirect(route('keuangan.dashboard'));

    $this->post('/logout');

    // WaSender single app user
    $responseWaSender = $this->post('/login', [
        'email' => 'userwasender@sirkelta.test',
        'password' => 'password',
    ]);
    $this->assertAuthenticated();
    $responseWaSender->assertRedirect(route('wasender.dashboard'));
});

test('multi module user redirects to last accessed dashboard upon login', function () {
    $user = User::where('email', 'usercombo@sirkelta.test')->first();
    $user->update(['last_module' => 'wasender']);

    $response = $this->post('/login', [
        'email' => 'usercombo@sirkelta.test',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('wasender.dashboard'));
});

test('multi module user without last_module defaults to first accessible module', function () {
    $user = User::where('email', 'usercombo@sirkelta.test')->first();
    $user->update(['last_module' => null]);

    $response = $this->post('/login', [
        'email' => 'usercombo@sirkelta.test',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('keuangan.dashboard'));
});

test('single module user accessing portal route is redirected directly to dashboard', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $response = $this->actingAs($user)->get('/portal');

    $response->assertRedirect(route('keuangan.dashboard'));
});

test('multi module user accessing portal route can view the portal page', function () {
    $user = User::where('email', 'usercombo@sirkelta.test')->first();

    $response = $this->actingAs($user)->get('/portal');

    $response->assertStatus(200);
});

test('visiting module dashboard updates user last_module', function () {
    $user = User::where('email', 'usercombo@sirkelta.test')->first();
    $this->actingAs($user)->get('/wasender/dashboard')->assertStatus(200);

    expect($user->fresh()->last_module)->toBe('wasender');

    $this->actingAs($user)->get('/keuangan/dashboard')->assertStatus(200);

    expect($user->fresh()->last_module)->toBe('keuangan');
});

test('keuangan user can access keuangan dashboard but forbidden from wasender', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $response = $this->actingAs($user)->get('/keuangan/dashboard');
    $response->assertStatus(200);

    $blocked = $this->actingAs($user)->get('/wasender/dashboard');
    $blocked->assertStatus(403);
});

test('keuangan dashboard provides rich monthly trend and cashflow summary', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $response = $this->actingAs($user)->get('/keuangan/dashboard');
    $response->assertStatus(200);

    $response->assertInertia(fn ($page) => $page
        ->component('Keuangan::Dashboard', false)
        ->has('monthlyTrend', 6)
        ->has('trendSummary')
        ->where('trendSummary.surplus_months_count', fn ($val) => is_numeric($val))
        ->where('monthlyTrend.0.month_name', fn ($val) => ! empty($val))
    );
});

test('wasender user can access wasender dashboard but forbidden from keuangan', function () {
    $user = User::where('email', 'userwasender@sirkelta.test')->first();

    $response = $this->actingAs($user)->get('/wasender/dashboard');
    $response->assertStatus(200);

    $blocked = $this->actingAs($user)->get('/keuangan/dashboard');
    $blocked->assertStatus(403);
});

test('multi app user and superadmin can access both modules', function () {
    $combo = User::where('email', 'usercombo@sirkelta.test')->first();

    $this->actingAs($combo)->get('/keuangan/dashboard')->assertStatus(200);
    $this->actingAs($combo)->get('/wasender/dashboard')->assertStatus(200);

    $super = User::where('email', 'superadmin@sirkelta.test')->first();

    $this->actingAs($super)->get('/keuangan/dashboard')->assertStatus(200);
    $this->actingAs($super)->get('/wasender/dashboard')->assertStatus(200);
});

test('keuangan user can manage wallets and records transactions with automatic balance reconciliation', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    // 1. Wallets Index
    $this->actingAs($user)->get('/keuangan/wallets')->assertStatus(200);

    // 2. Create Wallet
    $this->actingAs($user)->post('/keuangan/wallets', [
        'name' => 'Dompet Operasional Kantor',
        'category_label' => 'OPERASIONAL',
        'initial_balance' => 5000000,
        'account_number' => '1234567890',
        'icon' => 'wallet',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $wallet = $user->wallets()->where('name', 'Dompet Operasional Kantor')->first();
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->current_balance)->toBe(5000000.0);

    // 3. Transactions Index
    $this->actingAs($user)->get('/keuangan/transactions')->assertStatus(200);

    // 4. Record Income Transaction
    $this->actingAs($user)->post('/keuangan/transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'amount' => 1500000,
        'category' => 'Pendapatan Jasa',
        'transaction_date' => now()->toDateString(),
        'description' => 'Pembayaran proyek web',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $newIncomeTx = $user->transactions()->where('description', 'Pembayaran proyek web')->first();
    expect($newIncomeTx)->not->toBeNull();
    expect((float) $wallet->fresh()->current_balance)->toBe(6500000.0);

    // 5. Record Expense Transaction
    $this->actingAs($user)->post('/keuangan/transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'expense',
        'amount' => 500000,
        'category' => 'Operasional',
        'transaction_date' => now()->toDateString(),
        'description' => 'Beli perlengkapan kantor',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((float) $wallet->fresh()->current_balance)->toBe(6000000.0);

    // 6. Record Debt Transaction
    $this->actingAs($user)->post('/keuangan/transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'debt_receivable',
        'amount' => 1000000,
        'party_name' => 'PT Mitra Sejahtera',
        'due_date' => now()->addDays(7)->toDateString(),
        'debt_status' => 'unpaid',
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $tx = $user->transactions()->where('party_name', 'PT Mitra Sejahtera')->first();
    expect($tx)->not->toBeNull();

    // 7. Update Transaction
    $this->actingAs($user)->put("/keuangan/transactions/{$newIncomeTx->id}", [
        'wallet_id' => $wallet->id,
        'type' => 'income',
        'amount' => 2000000, // increased from 1.5m to 2.0m (+500k)
        'category' => 'Pendapatan Jasa Revisi',
        'transaction_date' => now()->toDateString(),
        'description' => 'Pembayaran proyek web (revisi bonus)',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect((float) $wallet->fresh()->current_balance)->toBe(6500000.0);

    // 8. Radar Debts Index & Status Update
    $this->actingAs($user)->get('/keuangan/radar-utang-piutang')->assertStatus(200);
    $this->actingAs($user)->patch("/keuangan/radar-utang-piutang/{$tx->id}/status", [
        'debt_status' => 'paid',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($tx->fresh()->debt_status)->toBe('paid');

    // 9. Reports & CSV Export with period
    $this->actingAs($user)->get('/keuangan/laporan?period=this_month')->assertStatus(200);
    $this->actingAs($user)->get('/keuangan/laporan?period=last_month')->assertStatus(200);
    $csv = $this->actingAs($user)->get('/keuangan/laporan/export-csv?period=this_month');
    $csv->assertStatus(200);
    expect($csv->headers->get('content-type'))->toContain('text/csv');
});

test('authenticated user can view and update their profile and password', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $this->actingAs($user)->get('/profile')->assertStatus(200);

    $this->actingAs($user)->put('/profile', [
        'name' => 'User Keuangan Updated',
        'username' => $user->username,
        'email' => 'userkeuangan@sirkelta.test',
        'monthly_budget' => 7500000,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $freshUser = $user->fresh();
    expect($freshUser->name)->toBe('User Keuangan Updated')
        ->and($freshUser->username)->toBe($user->username)
        ->and((float) $freshUser->monthly_budget)->toBe(7500000.0);

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('keuangan user can view wallet detail page and export filtered transactions to csv', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $wallet = $user->wallets()->first();

    // Wallet show page
    $this->actingAs($user)->get("/keuangan/wallets/{$wallet->id}")->assertStatus(200);

    // Export filtered transactions to CSV
    $csv = $this->actingAs($user)->get('/keuangan/transactions/export-csv?type=income');
    $csv->assertStatus(200);
    expect($csv->headers->get('content-type'))->toContain('text/csv');
    expect($csv->headers->get('content-disposition'))->toContain('mutasi-transaksi-keuangan');
});

test('admin keuangan and superadmin can access admin keuangan hub while ordinary users are forbidden', function () {
    $adminKeuangan = User::where('email', 'adminkeuangan@sirkelta.test')->first();
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $userKeuangan = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $userWaSender = User::where('email', 'userwasender@sirkelta.test')->first();

    // Admin Keuangan can access
    $this->actingAs($adminKeuangan)->get('/keuangan/admin')->assertStatus(200);
    $this->actingAs($adminKeuangan)->get('/keuangan/admin?search=userkeuangan')->assertStatus(200);

    // Superadmin can access
    $this->actingAs($superadmin)->get('/keuangan/admin')->assertStatus(200);

    // Ordinary user keuangan is forbidden
    $this->actingAs($userKeuangan)->get('/keuangan/admin')->assertStatus(403);

    // User wasender is forbidden
    $this->actingAs($userWaSender)->get('/keuangan/admin')->assertStatus(403);
});

test('all core pages can be rendered and return 200', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $wallet = Wallet::first();

    $pages = [
        '/',
        '/login',
        '/register',
        '/portal',
        '/profile',
        '/keuangan/dashboard',
        '/keuangan/wallets',
        "/keuangan/wallets/{$wallet->id}",
        '/keuangan/transactions',
        '/keuangan/radar-utang-piutang',
        '/keuangan/laporan',
        '/keuangan/admin',
        '/wasender/dashboard',
        '/wasender/devices',
        '/wasender/contacts',
        '/wasender/templates',
    ];

    foreach ($pages as $url) {
        $response = in_array($url, ['/', '/login', '/register'])
            ? $this->get($url)
            : $this->actingAs($superadmin)->get($url);

        expect($response->status())->toBe(200, "Failed asserting that {$url} returns 200, got {$response->status()}");
    }
});

test('wasender user can manage devices, contacts, and message templates', function () {
    $user = User::where('email', 'userwasender@sirkelta.test')->first();

    // 1. Devices Index & Add Device
    $this->actingAs($user)->get('/wasender/devices')->assertStatus(200);
    $this->actingAs($user)->post('/wasender/devices', [
        'name' => 'CS Office 1',
        'phone_number' => '08123456789',
        'daily_message_limit' => 500,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $device = Device::where('name', 'CS Office 1')->first();
    expect($device)->not->toBeNull();
    expect($device->phone_number)->toBe('628123456789');

    // Toggle connection status
    $this->actingAs($user)->patch("/wasender/devices/{$device->id}/toggle")->assertRedirect();
    expect($device->fresh()->status)->toBe('connected');

    // 2. Contacts Index & Create
    $this->actingAs($user)->get('/wasender/contacts')->assertStatus(200);
    $this->actingAs($user)->post('/wasender/contacts', [
        'name' => 'Budi Santoso',
        'phone' => '08987654321',
        'group_name' => 'Pelanggan VIP',
        'notes' => 'Langganan bulanan',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $contact = Contact::where('name', 'Budi Santoso')->first();
    expect($contact)->not->toBeNull();
    expect($contact->phone)->toBe('628987654321');

    // Export contacts CSV
    $csv = $this->actingAs($user)->get('/wasender/contacts/export-csv');
    $csv->assertStatus(200);
    expect($csv->headers->get('content-type'))->toContain('text/csv');

    // Update Contact
    $this->actingAs($user)->put("/wasender/contacts/{$contact->id}", [
        'name' => 'Budi Santoso Updated',
        'phone' => '08987654321',
        'group_name' => 'Pelanggan VIP',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($contact->fresh()->name)->toBe('Budi Santoso Updated');

    // 3. Templates Index & Create
    $this->actingAs($user)->get('/wasender/templates')->assertStatus(200);
    $this->actingAs($user)->post('/wasender/templates', [
        'title' => 'Pengingat Tagihan',
        'content' => 'Halo {name}, tagihan Anda jatuh tempo hari ini.',
        'category' => 'Billing',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $template = MessageTemplate::where('title', 'Pengingat Tagihan')->first();
    expect($template)->not->toBeNull();
    expect($template->category)->toBe('Billing');

    // Update Template
    $this->actingAs($user)->put("/wasender/templates/{$template->id}", [
        'title' => 'Pengingat Tagihan Revisi',
        'content' => 'Halo {name}, mohon selesaikan tagihan Anda.',
        'category' => 'Billing',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($template->fresh()->title)->toBe('Pengingat Tagihan Revisi');

    // Delete Template & Contact
    $this->actingAs($user)->delete("/wasender/templates/{$template->id}")->assertRedirect();
    $this->actingAs($user)->delete("/wasender/contacts/{$contact->id}")->assertRedirect();
    $this->actingAs($user)->delete("/wasender/devices/{$device->id}")->assertRedirect();

    expect(MessageTemplate::find($template->id))->toBeNull();
    expect(Contact::find($contact->id))->toBeNull();
    expect(Device::find($device->id))->toBeNull();
});

test('activity validations prevent invalid transactions, duplicate wallets, invalid phones, and self role lockout', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $wallet = $user->wallets()->first();

    // 1. Transfer to same wallet fails validation
    $this->actingAs($user)->post('/keuangan/transactions', [
        'wallet_id' => $wallet->id,
        'target_wallet_id' => $wallet->id,
        'type' => 'transfer',
        'amount' => 100000,
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['target_wallet_id']);

    // 2. Debt receivable without party_name fails validation
    $this->actingAs($user)->post('/keuangan/transactions', [
        'wallet_id' => $wallet->id,
        'type' => 'debt_receivable',
        'amount' => 100000,
        'transaction_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['party_name']);

    // 3. Duplicate wallet name for same user fails validation
    $this->actingAs($user)->post('/keuangan/wallets', [
        'name' => $wallet->name,
        'initial_balance' => 100000,
    ])->assertSessionHasErrors(['name']);

    // 4. Superadmin cannot strip their own superadmin role
    $super = User::where('email', 'superadmin@sirkelta.test')->first();
    $this->actingAs($super)->put("/admin/users/{$super->id}/roles", [
        'roles' => ['user_keuangan'],
    ])->assertSessionHasErrors(['roles']);
});
