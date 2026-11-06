<?php

use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\AccountActivationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('registration page can be rendered', function () {
    $response = $this->get(route('register'));
    $response->assertStatus(200);
});

test('user cannot register with already registered email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->post(route('register.attempt'), [
        'email' => 'existing@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('submitting email creates pending registration and sends notification', function () {
    Notification::fake();

    $response = $this->post(route('register.attempt'), [
        'email' => 'newuser@example.com',
    ]);

    $response->assertSessionHas('activation_sent', 'newuser@example.com');
    Notification::assertSentOnDemand(AccountActivationNotification::class);

    $pending = PendingRegistration::where('email', 'newuser@example.com')->first();
    expect($pending)->not->toBeNull();
    expect($pending->isExpired())->toBeFalse();
});

test('accessing complete registration requires valid signed url and valid token', function () {
    $token = Str::random(64);
    PendingRegistration::create([
        'email' => 'valid@example.com',
        'token' => hash('sha256', $token),
        'expires_at' => now()->addHours(24),
    ]);

    // Unsigned request fails (403)
    $unsignedResponse = $this->get(route('register.complete', ['email' => 'valid@example.com', 'token' => $token]));
    $unsignedResponse->assertStatus(403);

    // Valid signed request succeeds
    $signedUrl = URL::temporarySignedRoute(
        'register.complete',
        now()->addHours(24),
        ['email' => 'valid@example.com', 'token' => $token]
    );

    $signedResponse = $this->get($signedUrl);
    $signedResponse->assertStatus(200);
    $signedResponse->assertInertia(fn ($page) => $page
        ->component('Auth/CompleteRegistration')
        ->where('email', 'valid@example.com')
        ->where('token', $token)
    );
});

test('check username endpoint returns availability status', function () {
    User::factory()->create(['username' => 'takenuser']);

    $takenResponse = $this->getJson(route('username.check', ['username' => 'takenuser']));
    $takenResponse->assertJson(['available' => false]);

    $freeResponse = $this->getJson(route('username.check', ['username' => 'freeuser123']));
    $freeResponse->assertJson(['available' => true]);
});

test('completing registration creates verified user and redirects to portal with onboarding', function () {
    Event::fake([Registered::class]);

    $referrer = User::factory()->create(['username' => 'sponsoruser']);

    $token = Str::random(64);
    PendingRegistration::create([
        'email' => 'ready@example.com',
        'token' => hash('sha256', $token),
        'referral_code' => 'sponsoruser',
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->post(route('register.complete.submit'), [
        'email' => 'ready@example.com',
        'token' => $token,
        'name' => 'Siap Sukses',
        'username' => 'siapsukses',
        'password' => 'Sirkelta123!',
        'password_confirmation' => 'Sirkelta123!',
    ]);

    $response->assertRedirect(route('portal', ['onboarding' => 1]));
    Event::assertDispatched(Registered::class);

    $user = User::where('email', 'ready@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->username)->toBe('siapsukses');
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($user->referred_by)->toBe($referrer->id);
    expect($user->hasRole('user_keuangan'))->toBeTrue();

    // Pending registration is deleted
    expect(PendingRegistration::where('email', 'ready@example.com')->exists())->toBeFalse();

    // Authenticated session established
    $this->assertAuthenticatedAs($user);
});

test('portal displays onboarding when requested and does not auto redirect single module', function () {
    $user = User::factory()->create();
    $user->assignRole('user_keuangan');

    $response = $this->actingAs($user)->get(route('portal', ['onboarding' => 1]));
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/AppLauncher')
        ->where('showOnboarding', true)
    );
});
