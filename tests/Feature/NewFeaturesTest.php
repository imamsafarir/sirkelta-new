<?php

use App\Models\PendingRegistration;
use App\Models\ReferralLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WebsiteFaq;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('user can login using either email or username', function () {
    $user = User::where('email', 'superadmin@sirkelta.test')->first();
    $user->update(['username' => 'superboss']);

    // 1. Login with username
    $responseUser = $this->post('/login', [
        'login' => 'superboss',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($user);
    $this->post('/logout');

    // 2. Login with email
    $responseEmail = $this->post('/login', [
        'login' => 'superadmin@sirkelta.test',
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($user);
});

test('registration requires username and records referral reward when ref code is used', function () {
    $referrer = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $refUsername = $referrer->username;

    // 1. Initial register sends activation
    $this->post('/register', [
        'email' => 'budi@example.com',
        'referral_code' => strtoupper($refUsername),
    ]);

    $token = Str::random(64);
    PendingRegistration::updateOrCreate(
        ['email' => 'budi@example.com'],
        [
            'token' => hash('sha256', $token),
            'referral_code' => strtolower($refUsername),
            'expires_at' => now()->addHours(24),
        ]
    );

    // 2. Complete registration
    $response = $this->post(route('register.complete.submit'), [
        'email' => 'budi@example.com',
        'token' => $token,
        'name' => 'Budi Santoso',
        'username' => 'budisantoso',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
    $newUser = User::where('email', 'budi@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->username)->toBe('budisantoso')
        ->and($newUser->referred_by)->toBe($referrer->id)
        ->and($newUser->referral_code)->toBe('budisantoso');

    // Verify referral reward log was created for the referrer
    $log = ReferralLog::where('referrer_id', $referrer->id)->where('referee_id', $newUser->id)->first();
    expect($log)->not->toBeNull()
        ->and((float) $log->reward_discount)->toBe(25000.0)
        ->and($log->is_redeemed)->toBeFalse();
});

test('user can view referral center in profile and upgrade to pro with validity period', function () {
    $referrer = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $referee = User::where('email', 'userwasender@sirkelta.test')->first();

    ReferralLog::create([
        'referrer_id' => $referrer->id,
        'referee_id' => $referee->id,
        'reward_discount' => 25000,
        'is_redeemed' => false,
    ]);

    expect($referrer->availableReferralDiscount())->toBe(25000.0);

    // Access profile edit page which includes referral & pro subscriptions
    $this->actingAs($referrer)->get(route('profile.edit'))->assertStatus(200);

    // Legacy referral route redirects to profile
    $this->actingAs($referrer)->get(route('referral.index'))->assertRedirect(route('profile.edit'));

    // Upgrade / Redeem discount for Keuangan Pro with validity period
    $redeemResponse = $this->actingAs($referrer)->post(route('profile.pro.upgrade'), [
        'module' => 'keuangan',
        'duration_months' => 1,
    ]);

    $redeemResponse->assertSessionHas('success');
    expect($referrer->availableReferralDiscount())->toBe(0.0);

    $log = ReferralLog::where('referrer_id', $referrer->id)->first();
    expect($log->is_redeemed)->toBeTrue()
        ->and($log->applied_app)->toBe('keuangan_pro');

    // Verify subscription was created with active period
    $subscription = $referrer->getActiveProSubscription('keuangan');
    expect($subscription)->not->toBeNull()
        ->and($subscription->isActive())->toBeTrue()
        ->and($subscription->remainingDays())->toBeGreaterThan(0)
        ->and($referrer->isPro('keuangan'))->toBeTrue();
});

test('admin keuangan can review and block suspicious referrals with anti-fraud controls', function () {
    $admin = User::where('email', 'adminkeuangan@sirkelta.test')->first();
    $referrer = User::where('email', 'userkeuangan@sirkelta.test')->first();
    $referee = User::where('email', 'userwasender@sirkelta.test')->first();

    $log = ReferralLog::create([
        'referrer_id' => $referrer->id,
        'referee_id' => $referee->id,
        'reward_discount' => 25000,
        'is_redeemed' => false,
        'status' => 'valid',
    ]);

    expect($referrer->availableReferralDiscount())->toBe(25000.0);

    // Admin blocks referral for fraud
    $response = $this->actingAs($admin)->patch(route('keuangan.admin.referrals.update-status', $log->id), [
        'status' => 'blocked',
        'fraud_reason' => 'Akun kloningan terdeteksi',
    ]);

    $response->assertSessionHas('success');

    $log->refresh();
    expect($log->status)->toBe('blocked')
        ->and($log->fraud_reason)->toBe('Akun kloningan terdeteksi')
        ->and($log->reviewed_by)->toBe($admin->id);

    // Blocked referral cannot be counted in available discount
    expect($referrer->availableReferralDiscount())->toBe(0.0);

    // Admin unblocks referral
    $unblockResponse = $this->actingAs($admin)->patch(route('keuangan.admin.referrals.update-status', $log->id), [
        'status' => 'valid',
    ]);
    $unblockResponse->assertSessionHas('success');

    $log->refresh();
    expect($log->status)->toBe('valid');
    expect($referrer->availableReferralDiscount())->toBe(25000.0);
});

test('all public legal and informational pages are accessible', function () {
    $pages = [
        '/faq',
        '/tentang-kami',
        '/hubungi-kami',
        '/kebijakan-privasi',
        '/syarat-ketentuan',
        '/kebijakan-pengembalian-dana',
    ];

    foreach ($pages as $url) {
        $response = $this->get($url);
        $response->assertStatus(200);
    }
});

test('support chat messages work and admin resolution purges chat and increments counter', function () {
    $initialResolvedCount = WebsiteSetting::getResolvedComplaintsCount();

    // 1. Guest or user sends support chat message
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $chatResponse = $this->actingAs($user)->postJson(route('support.chat.send'), [
        'message' => 'Halo min, saldo dompet kas saya tidak sinkron.',
    ]);

    $chatResponse->assertStatus(200);
    $data = $chatResponse->json();
    $ticketId = $data['ticket']['id'];

    expect(SupportTicket::count())->toBe(1);
    expect(SupportMessage::count())->toBe(1);

    // 2. Admin inspects aduan
    $admin = User::where('email', 'superadmin@sirkelta.test')->first();
    $this->actingAs($admin)->get(route('admin.support.index'))->assertStatus(200);
    $this->actingAs($admin)->get(route('admin.support.show', $ticketId))->assertStatus(200);

    // 3. Admin replies
    $replyResponse = $this->actingAs($admin)->post(route('admin.support.reply', $ticketId), [
        'message' => 'Sudah kami cek dan perbaiki dari sistem kami ya.',
    ]);
    $replyResponse->assertSessionHas('success');
    expect(SupportMessage::count())->toBe(2);

    // 4. Admin marks as resolved & cleans chat
    $resolveResponse = $this->actingAs($admin)->post(route('admin.support.resolve', $ticketId));
    $resolveResponse->assertRedirect(route('admin.support.index'));
    $resolveResponse->assertSessionHas('success');

    // VERIFY: All chat messages are cleanly deleted (terhapus bersih)
    expect(SupportMessage::count())->toBe(0);

    // VERIFY: Ticket is marked resolved
    $ticket = SupportTicket::find($ticketId);
    expect($ticket->status)->toBe('resolved');

    // VERIFY: Lifetime resolved complaints counter has incremented (hitung aduan selesai)
    expect(WebsiteSetting::getResolvedComplaintsCount())->toBe($initialResolvedCount + 1);
});

test('admin can manage users and website settings', function () {
    $admin = User::where('email', 'superadmin@sirkelta.test')->first();
    $targetUser = User::where('email', 'userkeuangan@sirkelta.test')->first();

    // View users list
    $this->actingAs($admin)->get(route('admin.users.index'))->assertStatus(200);

    // Update target user's roles
    $roleResponse = $this->actingAs($admin)->put(route('admin.users.roles', $targetUser->id), [
        'roles' => ['admin_web', 'user_keuangan'],
    ]);
    $roleResponse->assertSessionHas('success');
    expect($targetUser->fresh()->hasRole('admin_web'))->toBeTrue();

    // View website settings and update
    $this->actingAs($admin)->get(route('admin.website.index'))->assertStatus(200);

    $settingsResponse = $this->actingAs($admin)->put(route('admin.website.settings.update'), [
        'support_whatsapp' => '628999888777',
        'support_email' => 'cs@sirkelta.test',
        'referral_discount_amount' => 35000,
    ]);
    $settingsResponse->assertSessionHas('success');
    expect(WebsiteSetting::get('support_whatsapp'))->toBe('628999888777')
        ->and(WebsiteSetting::get('referral_discount_amount'))->toBe('35000');

    // Create a new FAQ
    $faqResponse = $this->actingAs($admin)->post(route('admin.website.faqs.store'), [
        'category' => 'Keamanan',
        'question' => 'Apakah data saya aman?',
        'answer' => 'Ya, sangat aman terenkripsi.',
        'order' => 10,
        'is_active' => true,
    ]);
    $faqResponse->assertSessionHas('success');
    expect(WebsiteFaq::where('question', 'Apakah data saya aman?')->exists())->toBeTrue();

    // Update Legal Page Content dynamically
    $pageResponse = $this->actingAs($admin)->put(route('admin.website.page.update'), [
        'page_key' => 'about_us',
        'content' => '<h2>Tentang Sirkelta Baru</h2><p>Ekosistem finansial paling lengkap.</p>',
    ]);
    $pageResponse->assertSessionHas('success');
    expect(WebsiteSetting::get('about_us'))->toBe('<h2>Tentang Sirkelta Baru</h2><p>Ekosistem finansial paling lengkap.</p>');

    // Public page renders updated content
    $this->get('/tentang-kami')
        ->assertStatus(200)
        ->assertSee('Tentang Sirkelta Baru');
});

test('regular admin cannot access user management or website settings which are restricted to superadmin', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();
    $adminKeuangan = User::where('email', 'adminkeuangan@sirkelta.test')->first();

    // Create an admin_web user
    $adminWeb = User::firstOrCreate(
        ['email' => 'adminweb@sirkelta.test'],
        [
            'name' => 'Admin Website Support',
            'username' => 'adminweb',
            'referral_code' => 'adminweb',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]
    );
    $adminWeb->syncRoles(['admin_web']);

    // 1. Regular admins get 403 on kelola pengguna
    $this->actingAs($adminWeb)->get(route('admin.users.index'))->assertStatus(403);
    $this->actingAs($adminKeuangan)->get(route('admin.users.index'))->assertStatus(403);

    // 2. Regular admins get 403 on pengaturan & FAQ
    $this->actingAs($adminWeb)->get(route('admin.website.index'))->assertStatus(403);
    $this->actingAs($adminKeuangan)->get(route('admin.website.index'))->assertStatus(403);

    // 3. Superadmin can access both
    $this->actingAs($superadmin)->get(route('admin.users.index'))->assertStatus(200);
    $this->actingAs($superadmin)->get(route('admin.website.index'))->assertStatus(200);

    // 4. Regular admin can access their own specific features
    $this->actingAs($adminWeb)->get(route('admin.support.index'))->assertStatus(200);
    $this->actingAs($adminKeuangan)->get(route('keuangan.admin.index'))->assertStatus(200);
});

test('user can filter reports with custom date range and export csv', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    // 1. Visit report page with custom date range
    $response = $this->actingAs($user)->get(route('keuangan.reports.index', [
        'period' => 'custom',
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
    ]));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Keuangan::Reports/Index', false)
        ->has('summary')
        ->has('expenseByCategory')
        ->has('incomeByCategory')
        ->where('currentPeriod', 'custom')
    );

    // 2. Export report with custom date range
    $exportResponse = $this->actingAs($user)->get(route('keuangan.reports.export', [
        'period' => 'custom',
        'date_from' => '2026-01-01',
        'date_to' => '2026-12-31',
    ]));

    $exportResponse->assertStatus(200);
    expect($exportResponse->headers->get('content-type'))->toContain('text/csv');
});

test('single module user can activate second application for free to unlock multi workspace switching', function () {
    // Create a new single-module user with only user_keuangan
    $user = User::firstOrCreate(
        ['email' => 'singleapp@sirkelta.test'],
        [
            'name' => 'Single App User',
            'username' => 'singleapp',
            'referral_code' => 'singleapp',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]
    );
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles(['user_keuangan']);

    // 1. Check profile page: Keuangan is accessible, wasender is NOT accessible
    $profileRes = $this->actingAs($user)->get(route('profile.edit'));
    $profileRes->assertStatus(200);
    $profileRes->assertInertia(fn ($page) => $page
        ->component('Profile/Edit')
        ->where('proApps.0.module', 'keuangan')
        ->where('proApps.0.is_accessible', true)
        ->where('proApps.1.module', 'wasender')
        ->where('proApps.1.is_accessible', false)
    );

    // 2. Portal can be opened directly by single-app user with 1-click activate option
    $this->actingAs($user)->get(route('portal'))->assertStatus(200);

    // 3. User activates WaSender for free
    $activateRes = $this->actingAs($user)->post(route('profile.modules.activate'), [
        'module' => 'wasender',
    ]);
    $activateRes->assertSessionHas('success');

    // 4. Verify user now has user_wasender role & 2 accessible modules
    $user->refresh();
    expect($user->hasRole('user_wasender'))->toBeTrue();
    expect($user->accessibleModules())->toContain('keuangan', 'wasender');

    // 5. Portal Hub can now be opened without redirection (user has multiple workspaces)
    $this->actingAs($user)->get(route('portal'))->assertStatus(200);

    // 6. Profile page now reflects both apps as accessible
    $profileRes2 = $this->actingAs($user)->get(route('profile.edit'));
    $profileRes2->assertInertia(fn ($page) => $page
        ->component('Profile/Edit')
        ->where('proApps.0.is_accessible', true)
        ->where('proApps.1.is_accessible', true)
    );
});
