<?php

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('module:migrate Keuangan');
    $this->artisan('module:migrate WaSender');
    $this->seed();
});

test('forgot password page can be rendered', function () {
    $response = $this->get(route('password.request'));
    $response->assertStatus(200);
});

test('sending otp fails if email or username does not exist', function () {
    $response = $this->postJson(route('password.otp.send'), [
        'login' => 'nonexistent@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
    ]);
});

test('sending otp succeeds with valid email, stores cache, and sends notification', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'budisantoso@example.com',
        'username' => 'budisantoso',
        'name' => 'Budi Santoso',
    ]);

    $response = $this->postJson(route('password.otp.send'), [
        'login' => 'budisantoso@example.com',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'user_id' => $user->id,
    ]);

    Notification::assertSentTo($user, PasswordResetOtpNotification::class, function ($notification) {
        return strlen($notification->otp) === 6;
    });

    $cached = Cache::get('password_reset_otp_'.$user->id);
    expect($cached)->not->toBeNull();
    expect($cached['code'])->toHaveLength(6);
});

test('sending otp succeeds with username as login input', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'user_username@example.com',
        'username' => 'uniqueloginname',
        'name' => 'Unique User',
    ]);

    $response = $this->postJson(route('password.otp.send'), [
        'login' => 'uniqueloginname',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'user_id' => $user->id,
    ]);

    Notification::assertSentTo($user, PasswordResetOtpNotification::class);
});

test('sending otp is rejected if requested again within cooldown', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'cooldown@example.com',
    ]);

    $firstResponse = $this->postJson(route('password.otp.send'), [
        'login' => 'cooldown@example.com',
    ]);
    $firstResponse->assertOk();

    $secondResponse = $this->postJson(route('password.otp.send'), [
        'login' => 'cooldown@example.com',
    ]);
    $secondResponse->assertStatus(429);
});

test('verifying invalid otp increments attempts and rejects', function () {
    $user = User::factory()->create();

    Cache::put('password_reset_otp_'.$user->id, [
        'code' => '123456',
        'attempts' => 0,
        'email' => $user->email,
    ], now()->addMinutes(15));

    $response = $this->postJson(route('password.otp.verify'), [
        'user_id' => $user->id,
        'otp' => '999999',
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
    ]);

    $cached = Cache::get('password_reset_otp_'.$user->id);
    expect($cached['attempts'])->toBe(1);
});

test('verifying valid otp generates reset token and clears otp cache', function () {
    $user = User::factory()->create();

    Cache::put('password_reset_otp_'.$user->id, [
        'code' => '654321',
        'attempts' => 0,
        'email' => $user->email,
    ], now()->addMinutes(15));

    $response = $this->postJson(route('password.otp.verify'), [
        'user_id' => $user->id,
        'otp' => '654321',
    ]);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'user_id' => $user->id,
    ]);

    $token = $response->json('reset_token');
    expect($token)->not->toBeNull();
    expect(Cache::has('password_reset_otp_'.$user->id))->toBeFalse();
    expect(Cache::get('password_reset_token_'.$user->id))->toBe($token);
});

test('resetting password with valid token updates password and allows login', function () {
    $user = User::factory()->create([
        'email' => 'resetme@example.com',
        'password' => Hash::make('OldPassword123!'),
    ]);

    $token = 'valid-test-reset-token-1234567890';
    Cache::put('password_reset_token_'.$user->id, $token, now()->addMinutes(15));

    $response = $this->post(route('password.reset.submit'), [
        'user_id' => $user->id,
        'reset_token' => $token,
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    expect(Cache::has('password_reset_token_'.$user->id))->toBeFalse();

    // Verify user can login with new password
    $freshUser = $user->fresh();
    expect(Hash::check('BrandNewPassword123!', $freshUser->password))->toBeTrue();

    $loginResponse = $this->post(route('login.attempt'), [
        'login' => 'resetme@example.com',
        'password' => 'BrandNewPassword123!',
    ]);
    $this->assertAuthenticatedAs($freshUser);
});
