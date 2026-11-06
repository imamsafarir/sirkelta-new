<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\ReferralLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Notifications\AccountActivationNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
