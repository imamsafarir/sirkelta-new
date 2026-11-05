<?php

use App\Models\PaymentOrder;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('superadmin can access unified admin dashboard with global alerts', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();

    // Create a pending order and an open ticket
    PaymentOrder::create([
        'order_number' => 'ORD-ALERT-1',
        'user_id' => $superadmin->id,
        'module' => 'keuangan',
        'duration_months' => 1,
        'gross_amount' => 75000,
        'discount_applied' => 0,
        'final_amount' => 75000,
        'payment_method' => 'manual_bank',
        'status' => 'pending',
    ]);

    SupportTicket::create([
        'ticket_number' => 'TCK-ALERT-1',
        'user_id' => $superadmin->id,
        'subject' => 'Kendala Pembayaran',
        'status' => 'open',
    ]);

    $response = $this->actingAs($superadmin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'payments')
        ->has('accessibleTabs', 4)
        ->where('globalAlerts.pending_orders', 1)
        ->where('globalAlerts.open_tickets', 1)
        ->has('payments.orders.data')
    );
});

test('superadmin can switch tabs in unified admin dashboard', function () {
    $superadmin = User::where('email', 'superadmin@sirkelta.test')->first();

    // 1. Users Tab
    $responseUsers = $this->actingAs($superadmin)->get(route('admin.dashboard', ['tab' => 'users']));
    $responseUsers->assertOk();
    $responseUsers->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'users')
        ->has('users.users.data')
        ->has('users.roles')
    );

    // 2. Support Tab
    $responseSupport = $this->actingAs($superadmin)->get(route('admin.dashboard', ['tab' => 'support']));
    $responseSupport->assertOk();
    $responseSupport->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'support')
        ->has('support.tickets.data')
    );

    // 3. Fraud Tab
    $responseFraud = $this->actingAs($superadmin)->get(route('admin.dashboard', ['tab' => 'fraud']));
    $responseFraud->assertOk();
    $responseFraud->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'fraud')
        ->has('fraud.logs.data')
    );
});

test('admin_web can access dashboard and defaults to support tab', function () {
    $adminWeb = User::factory()->create([
        'username' => 'supportagent',
    ]);
    $adminWeb->assignRole('admin_web');

    $response = $this->actingAs($adminWeb)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'support')
        ->where('accessibleTabs', ['support'])
    );
});

test('admin_keuangan can access dashboard and defaults to fraud tab', function () {
    $adminKeuangan = User::where('email', 'adminkeuangan@sirkelta.test')->first();

    $response = $this->actingAs($adminKeuangan)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('currentTab', 'fraud')
        ->where('accessibleTabs', ['fraud'])
    );
});

test('regular user cannot access admin dashboard', function () {
    $user = User::where('email', 'userkeuangan@sirkelta.test')->first();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));
    $response->assertForbidden();
});
