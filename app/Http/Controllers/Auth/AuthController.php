<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ReferralLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Show the registration view.
     */
    public function showRegister(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'referralCode' => $request->query('ref'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request): RedirectResponse
    {
        if ($request->filled('referral_code')) {
            $request->merge([
                'referral_code' => strtolower(trim((string) $request->input('referral_code'))),
            ]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:'.User::class],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
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
            'initial_modules' => ['array', 'min:1'],
            'initial_modules.*' => ['string', 'in:keuangan,wasender'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, garis bawah (_), atau strip (-).',
            'username.min' => 'Username minimal harus 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.unique' => 'Username ini sudah digunakan oleh pengguna lain.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal harus 8 karakter.',
            'initial_modules.min' => 'Pilih minimal satu modul aplikasi.',
        ]);

        $username = strtolower(trim((string) $request->username));

        // Check if user was referred by another user (matching username or legacy code)
        $referrer = null;
        if ($request->filled('referral_code')) {
            $refInput = strtolower(trim((string) $request->referral_code));
            $referrer = User::where('username', $refInput)
                ->orWhere('referral_code', $refInput)
                ->first();
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referral_code' => $username,
            'referred_by' => $referrer?->id,
        ]);

        // If referred by someone, record reward discount log for referrer
        if ($referrer) {
            $rewardAmount = (float) WebsiteSetting::get('referral_discount_amount', 25000);
            ReferralLog::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $user->id,
                'reward_discount' => $rewardAmount,
                'is_redeemed' => false,
            ]);
        }

        // Assign chosen module roles (default to user_keuangan if none selected)
        $selectedModules = $request->input('initial_modules', ['keuangan']);
        $rolesToAssign = [];

        if (in_array('keuangan', $selectedModules, true)) {
            $rolesToAssign[] = 'user_keuangan';
        }
        if (in_array('wasender', $selectedModules, true)) {
            $rolesToAssign[] = 'user_wasender';
        }

        if (! empty($rolesToAssign)) {
            $user->syncRoles($rolesToAssign);
        } else {
            $user->assignRole('user_keuangan');
        }

        Auth::login($user);
        $request->session()->regenerate();

        event(new Registered($user));

        return redirect()->route('verification.notice')->with('success', 'Akun berhasil dibuat! Silakan verifikasi email Anda untuk memilih aplikasi.');
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
