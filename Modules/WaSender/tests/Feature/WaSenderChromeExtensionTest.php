<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\ExtensionSession;
use Modules\WaSender\Models\MessageTemplate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user_wasender', 'guard_name' => 'web']);
});

test('it normalizes contact phone numbers correctly', function () {
    expect(Contact::normalizePhone('08123456789'))->toBe('628123456789')
        ->and(Contact::normalizePhone('+62 812-3456-789'))->toBe('628123456789')
        ->and(Contact::normalizePhone('628123456789'))->toBe('628123456789')
        ->and(Contact::normalizePhone('8123456789'))->toBe('628123456789');
});

test('message template handles random code and spintax', function () {
    $template = new MessageTemplate([
        'title' => 'Promo Ramadhan',
        'content' => '[Halo/Hai] {nama}, cek promo kami!',
        'has_random_code' => true,
    ]);

    expect($template->containsSpintax())->toBeTrue()
        ->and(strlen(MessageTemplate::generateRandomCode()))->toBe(5);

    $rendered = $template->renderFor('Budi', '628123456789');
    expect($rendered)->toContain('Budi')
        ->and($rendered)->toContain('#');
});

test('user can access wasender dashboard and see extension card', function () {
    $user = User::factory()->create();
    $user->assignRole('user_wasender');

    $response = $this->actingAs($user)->get(route('wasender.dashboard'));
    $response->assertOk();
    $response->assertSee('downloadUrl');
});

test('extension download endpoint serves valid zip file', function () {
    $user = User::factory()->create();
    $user->assignRole('user_wasender');

    $response = $this->actingAs($user)->get(route('wasender.download.extension'));
    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');
});

test('extension api allows login and issues bearer token', function () {
    $user = User::factory()->create([
        'email' => 'wasendertest@sirkelta.test',
        'username' => 'wasenderuser',
        'password' => bcrypt('secret123'),
    ]);

    $response = $this->postJson('/api/extension/login', [
        'login' => 'wasenderuser',
        'password' => 'secret123',
        'device_name' => 'Chrome Windows 11',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'token',
        'user' => ['id', 'name', 'email'],
    ]);

    expect(ExtensionSession::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);

    // Test session takeover
    $takeover = $this->postJson('/api/extension/login', [
        'login' => 'wasendertest@sirkelta.test',
        'password' => 'secret123',
        'device_name' => 'Chrome Laptop',
        'device_id' => 'device-laptop-999',
        'force_takeover' => true,
    ]);
    $takeover->assertOk();
    expect(ExtensionSession::where('user_id', $user->id)->where('status', 'active')->count())->toBe(1);
});

test('extension auth sync endpoint syncs web session and supports takeover', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('extension.auth.sync', ['force_takeover' => 1]));
    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('authenticated', true);
    $response->assertJsonStructure([
        'data' => ['token', 'user', 'plan', 'quota'],
    ]);
});

test('extension authenticated routes require valid token', function () {
    $response = $this->getJson('/api/extension/me');
    $response->assertStatus(401);

    $user = User::factory()->create();
    $plainToken = ExtensionSession::generateToken();
    $session = ExtensionSession::create([
        'user_id' => $user->id,
        'session_token_hash' => hash('sha256', $plainToken),
        'device_name' => 'Chrome Test',
        'ip_address' => '127.0.0.1',
        'status' => 'active',
        'last_heartbeat_at' => now(),
    ]);

    $authResponse = $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/api/extension/me');

    $authResponse->assertOk();
    $authResponse->assertJsonPath('data.user.id', $user->id);
});

test('contact can be stored, updated and deleted', function () {
    $user = User::factory()->create();
    $user->assignRole('user_wasender');

    $this->actingAs($user)->post(route('wasender.contacts.store'), [
        'name' => 'Budi Santoso',
        'phone' => '081298765432',
        'group_name' => 'VIP',
        'notes' => 'Langganan tetap',
    ])->assertRedirect();

    $contact = Contact::where('phone', '6281298765432')->first();
    expect($contact)->not->toBeNull()
        ->and($contact->name)->toBe('Budi Santoso')
        ->and($contact->group_name)->toBe('VIP');

    $this->actingAs($user)->put(route('wasender.contacts.update', $contact->id), [
        'name' => 'Budi S. Updated',
        'phone' => '081298765432',
        'group_name' => 'Reseller',
        'notes' => 'Catatan baru',
    ])->assertRedirect();

    expect($contact->fresh()->group_name)->toBe('Reseller');

    $this->actingAs($user)->delete(route('wasender.contacts.destroy', $contact->id))
        ->assertRedirect();

    expect(Contact::find($contact->id))->toBeNull();
});
