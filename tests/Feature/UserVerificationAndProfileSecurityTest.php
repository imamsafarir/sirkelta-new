<?php

use App\Models\User;
use App\Notifications\EmailChangeVerificationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('check username endpoint returns availability status correctly', function () {
    User::factory()->create(['username' => 'existing_user']);

    // Check taken username
    $responseTaken = $this->getJson(route('username.check', ['username' => 'existing_user']));
    $responseTaken->assertOk()
        ->assertJson([
            'available' => false,
        ]);

    // Check available username
    $responseAvailable = $this->getJson(route('username.check', ['username' => 'fresh_unique_user']));
    $responseAvailable->assertOk()
        ->assertJson([
            'available' => true,
        ]);

    // Check invalid length
    $responseTooShort = $this->getJson(route('username.check', ['username' => 'ab']));
    $responseTooShort->assertOk()
        ->assertJson([
            'available' => false,
        ]);
});

test('user can update username and referral_code is kept in sync', function () {
    $user = User::factory()->create([
        'username' => 'original_username',
        'referral_code' => 'original_username',
        'email' => 'user@example.com',
    ]);
    $user->assignRole('user_keuangan');

    $response = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'new_fresh_username',
        'email' => 'user@example.com',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    $freshUser = $user->fresh();
    expect($freshUser->username)->toBe('new_fresh_username');
    expect($freshUser->referral_code)->toBe('new_fresh_username');
});

test('username update rejects spaces and already taken usernames', function () {
    User::factory()->create([
        'username' => 'taken_username',
        'email' => 'taken@example.com',
    ]);

    $user = User::factory()->create([
        'username' => 'my_username',
        'email' => 'my@example.com',
    ]);
    $user->assignRole('user_keuangan');

    // Attempt to change to already taken username
    $responseTaken = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'taken_username',
        'email' => 'my@example.com',
    ]);
    $responseTaken->assertSessionHasErrors('username');

    // Attempt to change to username with spaces (should be sanitized or rejected)
    $responseSpaces = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'with spaces inside',
        'email' => 'my@example.com',
    ]);
    // The controller sanitizes by removing spaces, resulting in "withspacesinside"
    $responseSpaces->assertSessionHasNoErrors();
    expect($user->fresh()->username)->toBe('withspacesinside');
});

test('user can request email change otp sent to current active email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'username' => 'otp_user',
        'email' => 'active_current@example.com',
    ]);
    $user->assignRole('user_keuangan');

    $response = $this->actingAs($user)->postJson(route('profile.email.send-otp'), [
        'new_email' => 'brand_new@example.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    Notification::assertSentTo($user, EmailChangeVerificationNotification::class, function ($notification) {
        return $notification->newEmail === 'brand_new@example.com' && strlen($notification->otp) === 6;
    });

    $cached = Cache::get('email_change_otp_'.$user->id);
    expect($cached)->not->toBeNull();
    expect($cached['new_email'])->toBe('brand_new@example.com');
});

test('email change requires valid otp sent to current email and triggers verification for new email', function () {
    $user = User::factory()->create([
        'username' => 'email_change_user',
        'email' => 'old_email@example.com',
        'email_verified_at' => now(),
    ]);
    $user->assignRole('user_keuangan');

    // Attempt without OTP fails
    $responseNoOtp = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'email_change_user',
        'email' => 'valid_new_email@example.com',
    ]);
    $responseNoOtp->assertSessionHasErrors('email_otp');
    expect($user->fresh()->email)->toBe('old_email@example.com');

    // Set correct OTP in Cache
    Cache::put('email_change_otp_'.$user->id, [
        'code' => '654321',
        'new_email' => 'valid_new_email@example.com',
    ], now()->addMinutes(15));

    // Attempt with wrong OTP fails
    $responseWrongOtp = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'email_change_user',
        'email' => 'valid_new_email@example.com',
        'email_otp' => '999999',
    ]);
    $responseWrongOtp->assertSessionHasErrors('email_otp');
    expect($user->fresh()->email)->toBe('old_email@example.com');

    // Attempt with valid OTP succeeds
    $responseValid = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'Updated Name',
        'username' => 'email_change_user',
        'email' => 'valid_new_email@example.com',
        'email_otp' => '654321',
    ]);

    $responseValid->assertRedirect(route('verification.notice'));
    $freshUser = $user->fresh();
    expect($freshUser->email)->toBe('valid_new_email@example.com');
    expect($freshUser->email_verified_at)->toBeNull();
    expect($freshUser->email_changed_at)->not->toBeNull();
    expect(Cache::has('email_change_otp_'.$user->id))->toBeFalse();
});

test('registration dispatches registered event and redirects to verification prompt', function () {
    Event::fake([Registered::class]);

    $response = $this->post(route('register.attempt'), [
        'name' => 'New Customer',
        'username' => 'brand_new_user',
        'email' => 'new_customer@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'initial_modules' => ['keuangan'],
    ]);

    $response->assertRedirect(route('verification.notice'));
    Event::assertDispatched(Registered::class);

    $newUser = User::where('email', 'new_customer@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->hasVerifiedEmail())->toBeFalse();
});

test('unverified user cannot access portal or module dashboards and is redirected to verification notice', function () {
    $unverifiedUser = User::factory()->unverified()->create();
    $unverifiedUser->assignRole('user_keuangan');

    // Accessing portal redirects to email verification notice
    $response = $this->actingAs($unverifiedUser)->get(route('portal'));
    $response->assertRedirect(route('verification.notice'));

    // Accessing Keuangan dashboard redirects to email verification notice
    $responseKeuangan = $this->actingAs($unverifiedUser)->get(route('keuangan.dashboard'));
    $responseKeuangan->assertRedirect(route('verification.notice'));
});

test('clicking valid signed email verification link verifies email and redirects to portal', function () {
    $unverifiedUser = User::factory()->unverified()->create();
    $unverifiedUser->assignRole('user_keuangan');

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $unverifiedUser->id, 'hash' => sha1($unverifiedUser->getEmailForVerification())]
    );

    $response = $this->actingAs($unverifiedUser)->get($verificationUrl);

    $response->assertRedirect(route('portal'));
    expect($unverifiedUser->fresh()->hasVerifiedEmail())->toBeTrue();
});
