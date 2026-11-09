<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\ReferralLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Notifications\AccountActivationNotification;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the login view.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request (via Email or Username).
     */
    public function login(Request $request): RedirectResponse
    {
        $loginInput = trim((string) ($request->input('login') ?? $request->input('email') ?? ''));
        $request->merge(['login' => $loginInput]);

        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Email atau username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $fieldType => $loginInput,
            'password' => $request->input('password'),
        ];

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors([
                'login' => 'Kombinasi email/username dan kata sandi yang Anda masukkan tidak cocok.',
            ])->onlyInput('login');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return redirect()->intended($user->resolveDefaultDashboardRoute());
    }

    /**
     * Show the registration view (email-only entry).
     */
    public function showRegister(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'referralCode' => $request->query('ref'),
            'activationSentEmail' => session('activation_sent'),
        ]);
    }

    /**
     * Handle the initial registration request: validate email and send activation link.
     */
    public function register(Request $request): RedirectResponse
    {
        if ($request->filled('referral_code')) {
            $request->merge([
                'referral_code' => strtolower(trim((string) $request->input('referral_code'))),
            ]);
        }

        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'referral_code' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (! empty($value)) {
                    $val = strtolower(trim((string) $value));
                    $exists = User::where('username', $val)
                        ->orWhere('referral_code', $val)
                        ->exists();
                    if (! $exists) {
                        $fail('Username referral yang dimasukkan tidak ditemukan.');
                    }
                }
            }],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar. Silakan langsung masuk ke akun Anda.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $referralCode = $request->filled('referral_code') ? strtolower(trim((string) $request->referral_code)) : null;

        $token = Str::random(64);

        PendingRegistration::updateOrCreate(
            ['email' => $email],
            [
                'token' => hash('sha256', $token),
                'referral_code' => $referralCode,
                'expires_at' => now()->addHours(24),
            ]
        );

        $activationUrl = URL::temporarySignedRoute(
            'register.complete',
            now()->addHours(24),
            [
                'token' => $token,
                'email' => $email,
            ]
        );

        Notification::route('mail', $email)->notify(new AccountActivationNotification($activationUrl, $email));

        return back()
            ->with('activation_sent', $email)
            ->with('success', 'Tautan aktivasi berhasil dikirim! Silakan periksa kotak masuk atau folder spam email Anda.');
    }

    /**
     * Show the view to complete registration (set name, username, and password).
     */
    public function showCompleteRegistration(Request $request): Response|RedirectResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $token = (string) $request->query('token', '');

        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending || $pending->isExpired() || ! hash_equals($pending->token, hash('sha256', $token))) {
            return redirect()->route('register')->withErrors([
                'email' => 'Tautan aktivasi tidak valid atau telah kedaluwarsa. Silakan masukkan email Anda kembali untuk menerima tautan baru.',
            ]);
        }

        return Inertia::render('Auth/CompleteRegistration', [
            'email' => $email,
            'token' => $token,
            'referralCode' => $pending->referral_code,
        ]);
    }

    /**
     * Finalize the registration: create user, assign role, login and direct to onboarding.
     */
    public function completeRegistration(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, garis bawah (_), atau strip (-).',
            'username.min' => 'Username minimal harus 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.unique' => 'Username ini sudah digunakan oleh pengguna lain.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $token = (string) $request->token;

        $pending = PendingRegistration::where('email', $email)->first();

        if (! $pending || $pending->isExpired() || ! hash_equals($pending->token, hash('sha256', $token))) {
            return redirect()->route('register')->withErrors([
                'email' => 'Tautan aktivasi tidak valid atau telah kedaluwarsa. Silakan lakukan pendaftaran ulang.',
            ]);
        }

        $username = strtolower(trim((string) $request->username));

        // Check referral
        $referrer = null;
        if (! empty($pending->referral_code)) {
            $refInput = strtolower(trim((string) $pending->referral_code));
            $referrer = User::where('username', $refInput)
                ->orWhere('referral_code', $refInput)
                ->first();
        }

        $user = User::create([
            'name' => trim((string) $request->name),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), // Terverifikasi langsung karena diklik dari email
            'referral_code' => $username,
            'referred_by' => $referrer?->id,
        ]);

        if ($referrer) {
            $rewardAmount = (float) WebsiteSetting::get('referral_discount_amount', 25000);
            ReferralLog::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $user->id,
                'reward_discount' => $rewardAmount,
                'is_redeemed' => false,
            ]);
        }

        // Berikan role default (keuangan)
        $user->assignRole('user_keuangan');

        // Hapus pending registration
        $pending->delete();

        // Otomatis login
        Auth::login($user);
        $request->session()->regenerate();

        event(new Registered($user));

        return redirect()->route('portal', ['onboarding' => 1])
            ->with('success', 'Akun berhasil diaktifkan! Selamat datang di Sirkelta.');
    }

    /**
     * Check if a username is available in real-time.
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $raw = (string) $request->query('username', '');
        $username = strtolower(trim($raw));

        if (empty($username) || strlen($username) < 3) {
            return response()->json([
                'available' => false,
                'message' => 'Username minimal 3 karakter.',
            ]);
        }

        if (strlen($username) > 50) {
            return response()->json([
                'available' => false,
                'message' => 'Username maksimal 50 karakter.',
            ]);
        }

        if (! preg_match('/^[a-z0-9_-]+$/', $username)) {
            return response()->json([
                'available' => false,
                'message' => 'Username hanya boleh huruf kecil, angka, garis bawah (_), atau strip (-) tanpa spasi.',
            ]);
        }

        $query = User::where('username', $username);
        if ($currentUserId = $request->user()?->id) {
            $query->where('id', '!=', $currentUserId);
        }

        if ($query->exists()) {
            return response()->json([
                'available' => false,
                'message' => 'Username ini sudah digunakan, silakan pilih yang lain.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Username tersedia!',
        ]);
    }

    /**
     * Show the forgot password view.
     */
    public function showForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Send a 6-digit OTP code to user's registered email for password recovery.
     */
    public function sendPasswordResetOtp(Request $request): JsonResponse
    {
        $input = strtolower(trim((string) ($request->input('login') ?? $request->input('email') ?? '')));

        if (empty($input)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email atau username wajib diisi.',
            ], 422);
        }

        $user = User::where('email', $input)
            ->orWhere('username', $input)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Akun dengan email atau username tersebut tidak ditemukan.',
            ], 422);
        }

        $cooldownKey = 'password_reset_cooldown_'.$user->id;
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan tunggu 60 detik sebelum meminta kode OTP kembali.',
            ], 429);
        }

        $otp = (string) random_int(100000, 999999);
        $cacheKey = 'password_reset_otp_'.$user->id;

        Cache::put($cacheKey, [
            'code' => $otp,
            'attempts' => 0,
            'email' => $user->email,
        ], now()->addMinutes(15));

        Cache::put($cooldownKey, true, now()->addSeconds(60));

        $user->notify(new PasswordResetOtpNotification($otp, $user->name));

        $parts = explode('@', $user->email);
        $namePart = $parts[0];
        $domainPart = $parts[1] ?? '';
        $maskedName = strlen($namePart) <= 2
            ? substr($namePart, 0, 1).'***'
            : substr($namePart, 0, 2).str_repeat('*', min(4, max(2, strlen($namePart) - 3))).substr($namePart, -1);
        $maskedEmail = $maskedName.'@'.$domainPart;

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil dikirimkan ke email Anda.',
            'user_id' => $user->id,
            'masked_email' => $maskedEmail,
        ]);
    }

    /**
     * Verify the 6-digit OTP code submitted by the user.
     */
    public function verifyPasswordResetOtp(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'otp' => ['required', 'string'],
        ], [
            'user_id.required' => 'Identifikasi pengguna tidak valid.',
            'otp.required' => 'Kode OTP wajib diisi.',
        ]);

        $userId = (int) $request->user_id;
        $submittedOtp = preg_replace('/\D/', '', (string) $request->otp);

        $cacheKey = 'password_reset_otp_'.$userId;
        $stored = Cache::get($cacheKey);

        if (! $stored) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa atau belum pernah diminta. Silakan minta kode baru.',
            ], 422);
        }

        if (($stored['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);

            return response()->json([
                'success' => false,
                'message' => 'Anda telah salah memasukkan kode OTP sebanyak 5 kali. Demi keamanan, kode OTP telah hangus. Silakan minta kode baru.',
            ], 422);
        }

        if (! hash_equals((string) $stored['code'], (string) $submittedOtp)) {
            $stored['attempts'] = ($stored['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $stored, now()->addMinutes(15));
            $remaining = 5 - $stored['attempts'];

            return response()->json([
                'success' => false,
                'message' => "Kode OTP yang Anda masukkan salah. Sisa kesempatan: {$remaining} kali.",
            ], 422);
        }

        // OTP Valid: buat token reset aman berdurasi 15 menit dan konsumsi OTP
        $resetToken = Str::random(64);
        Cache::put('password_reset_token_'.$userId, $resetToken, now()->addMinutes(15));
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil diverifikasi!',
            'user_id' => $userId,
            'reset_token' => $resetToken,
        ]);
    }

    /**
     * Set a new password using the verified reset token.
     */
    public function resetPasswordWithOtp(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'user_id.required' => 'Identifikasi pengguna tidak valid.',
            'reset_token.required' => 'Token verifikasi wajib disertakan.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
        ]);

        $userId = (int) $request->user_id;
        $storedToken = Cache::get('password_reset_token_'.$userId);

        if (! $storedToken || ! hash_equals($storedToken, (string) $request->reset_token)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi reset kata sandi telah kedaluwarsa. Silakan ulangi proses lupa kata sandi.',
                ], 422);
            }

            return back()->withErrors([
                'password' => 'Sesi reset kata sandi telah kedaluwarsa. Silakan ulangi proses lupa kata sandi.',
            ]);
        }

        $user = User::findOrFail($userId);
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        Cache::forget('password_reset_token_'.$userId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Kata sandi berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.',
                'redirect' => route('login'),
            ]);
        }

        return redirect()->route('login')->with('status', 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
