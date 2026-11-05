<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminPaymentOrderController;
use App\Models\PaymentOrder;
use App\Models\ReferralLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\WebsiteSetting;
use App\Notifications\EmailChangeVerificationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\Device;
use Modules\WaSender\Models\MessageTemplate;

class ProfileController extends Controller
{
    /**
     * Get pricing tiers with multi-month discounts and popular flags.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function getPackagePricing(): array
    {
        return [
            'keuangan' => [
                1 => ['months' => 1, 'price' => 75000, 'normal' => 75000, 'discount_pct' => 0, 'popular' => false, 'label' => '1 Bulan'],
                3 => ['months' => 3, 'price' => 200000, 'normal' => 225000, 'discount_pct' => 11, 'popular' => false, 'label' => '3 Bulan (Hemat 11%)'],
                6 => ['months' => 6, 'price' => 375000, 'normal' => 450000, 'discount_pct' => 17, 'popular' => true, 'label' => '6 Bulan (Diskon 17%)'],
                12 => ['months' => 12, 'price' => 690000, 'normal' => 900000, 'discount_pct' => 23, 'popular' => false, 'label' => '12 Bulan (Hemat 23%)'],
            ],
            'wasender' => [
                1 => ['months' => 1, 'price' => 99000, 'normal' => 99000, 'discount_pct' => 0, 'popular' => false, 'label' => '1 Bulan'],
                3 => ['months' => 3, 'price' => 269000, 'normal' => 297000, 'discount_pct' => 10, 'popular' => false, 'label' => '3 Bulan (Hemat 10%)'],
                6 => ['months' => 6, 'price' => 495000, 'normal' => 594000, 'discount_pct' => 17, 'popular' => true, 'label' => '6 Bulan (Diskon 17%)'],
                12 => ['months' => 12, 'price' => 890000, 'normal' => 1188000, 'discount_pct' => 25, 'popular' => false, 'label' => '12 Bulan (Hemat 25%)'],
            ],
        ];
    }

    /**
     * Show profile and security settings page, including Pro subscriptions and referral.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Ensure user referral code is synced with username
        if (empty($user->referral_code) || $user->referral_code !== $user->username) {
            $user->update(['referral_code' => $user->username]);
            $user->refresh();
        }

        // 1. Referral Information
        $referralLogs = ReferralLog::where('referrer_id', $user->id)
            ->with('referee:id,name,username,email,created_at')
            ->latest()
            ->take(15)
            ->get();

        $totalReferees = User::where('referred_by', $user->id)->count();
        $availableDiscount = $user->availableReferralDiscount();
        $totalEarnedDiscount = (float) ReferralLog::where('referrer_id', $user->id)->sum('reward_discount');

        // 2. Pro Plans & Validity Periods (Masa Aktif)
        $packagePricing = self::getPackagePricing();

        $proModules = [
            [
                'module' => 'keuangan',
                'name' => 'Sirkelta Keuangan Pro',
                'monthly_price' => 75000,
                'description' => 'Kelola seluruh arus kas bisnis, multi-kasir, dan laporan akuntansi tanpa batas.',
                'features' => [
                    'Dompet & Rekening Kas Tanpa Batas',
                    'Laporan Laba Rugi & Arus Kas Lengkap',
                    'Export Laporan Transaksi ke Format Excel',
                    'Multi-User Kasir & Manajemen Hak Akses',
                ],
            ],
            [
                'module' => 'wasender',
                'name' => 'Sirkelta WaSender Pro',
                'monthly_price' => 99000,
                'description' => 'Kirim broadcast WhatsApp massal tanpa batas via Google Chrome, lampiran dokumen/gambar, dan anti-banned.',
                'features' => [
                    'Kirim Pesan Broadcast Tanpa Batas Harian (Unlimited)',
                    'Kirim File Gambar, Dokumen PDF & Media (Hingga 10 Lampiran)',
                    'Ekstrak Nomor Anggota Grup WhatsApp Otomatis (Group Scraper)',
                    'Impor & Ekspor Kontak Massal dari Format CSV / Excel',
                ],
            ],
        ];

        $isSuperadmin = $user->hasRole('superadmin');
        $accessibleModules = $user->accessibleModules();

        $proApps = array_map(function ($app) use ($user, $isSuperadmin, $packagePricing, $accessibleModules) {
            $subscription = $user->getActiveProSubscription($app['module']);
            $isAccessible = $isSuperadmin || in_array($app['module'], $accessibleModules, true);

            return [
                ...$app,
                'pricing' => $packagePricing[$app['module']] ?? [],
                'is_accessible' => $isAccessible,
                'is_pro' => $isSuperadmin || $subscription !== null,
                'is_superadmin' => $isSuperadmin,
                'subscription' => $subscription ? [
                    'id' => $subscription->id,
                    'starts_at' => $subscription->starts_at?->format('d M Y'),
                    'expires_at' => $subscription->expires_at?->format('d M Y'),
                    'remaining_days' => $subscription->remainingDays(),
                    'duration_months' => $subscription->duration_months,
                ] : null,
            ];
        }, $proModules);

        // 3. Free vs Pro Comparison Data
        $comparisonPlans = [
            'keuangan' => [
                'name' => 'Sirkelta Keuangan',
                'description' => 'Solusi pencatatan kas, dompet, dan arus keuangan bisnis',
                'items' => [
                    ['feature' => 'Jumlah Dompet Kas & Rekening Bank', 'free' => 'Maksimal 2 Dompet', 'pro' => 'Unlimited (Tanpa Batas)'],
                    ['feature' => 'Pencatatan Pemasukan & Pengeluaran', 'free' => 'Fitur Dasar', 'pro' => 'Lengkap & Terkategori'],
                    ['feature' => 'Kategori Transaksi Kustom', 'free' => 'Maksimal 5 Kategori', 'pro' => 'Unlimited Bebas Tambah'],
                    ['feature' => 'Batas Riwayat Transaksi Kas', 'free' => '30 Hari Terakhir', 'pro' => 'Selamanya (Tanpa Batas)'],
                    ['feature' => 'Laporan Laba Rugi & Arus Kas', 'free' => 'Ringkasan Kas Sederhana', 'pro' => 'Laporan Akuntansi Lengkap'],
                    ['feature' => 'Grafik Statistik & Tren Keuangan', 'free' => 'Grafik Dasar', 'pro' => 'Analisis Visual Lengkap Real-time'],
                    ['feature' => 'Export Data ke Excel (.xlsx) / PDF', 'free' => 'Tidak Tersedia', 'pro' => 'Tersedia Sepuasnya'],
                    ['feature' => 'Multi-User Kasir & Manajemen Hak Akses', 'free' => 'Hanya 1 Akun Utama', 'pro' => 'Multi-User (Banyak Staf / Kasir)'],
                    ['feature' => 'Penyimpanan & Backup Data Otomatis', 'free' => 'Standar', 'pro' => 'Cloud Backup Aman Berkala'],
                    ['feature' => 'Dukungan Pelanggan (Customer Support)', 'free' => 'Standar Komunitas', 'pro' => 'Prioritas VIP 24/7'],
                ],
            ],
            'wasender' => [
                'name' => 'Sirkelta WaSender',
                'description' => 'Ekstensi broadcast WhatsApp resmi Sirkelta untuk Google Chrome & WhatsApp Web',
                'items' => [
                    ['feature' => 'Kuota Kirim Pesan Broadcast Harian', 'free' => 'Maks. 20 Pesan / Hari', 'pro' => 'Tanpa Batas Harian (Unlimited)'],
                    ['feature' => 'Kirim Lampiran Gambar, Foto & File Dokumen', 'free' => 'Tidak Tersedia (Teks Saja)', 'pro' => 'Tersedia (Hingga 10 File per Pesan)'],
                    ['feature' => 'Ekstrak Anggota Grup WhatsApp (Group Scraper)', 'free' => 'Tidak Tersedia', 'pro' => 'Aktif Bebas Ekstrak Kontak Grup'],
                    ['feature' => 'Impor & Ekspor Kontak Massal (CSV / Excel)', 'free' => 'Tidak Tersedia (Ketik Manual)', 'pro' => 'Aktif Impor & Ekspor Sekali Klik'],
                    ['feature' => 'Variasi Kata Spintax {Halo|Hai|Pagi}', 'free' => 'Tidak Tersedia', 'pro' => 'Tersedia Acak Anti-Spam'],
                    ['feature' => 'Pengaturan Jeda Pengiriman & Anti-Banned', 'free' => 'Terkunci (Standar 5-10 Detik)', 'pro' => 'Bebas Atur Detik + 4 Preset Keamanan'],
                    ['feature' => 'Buku Kontak Cloud & Multi-Grup Segmentasi', 'free' => 'Maks. 50 Kontak (1 Kontak / Kirim)', 'pro' => 'Unlimited Kontak & Multi-Grup'],
                    ['feature' => 'Penyimpanan Template Pesan Cloud Dinamis', 'free' => 'Maks. 1 Template', 'pro' => 'Unlimited Template Pesan Dinamis'],
                    ['feature' => 'Sensor & Privasi Layar WhatsApp (Anti-Intip)', 'free' => 'Aktif (Blur Chat, Avatar & Media)', 'pro' => 'Aktif Penuh Bebas Digunakan'],
                    ['feature' => 'Kode Unik Acak Anti-Duplikasi (#12345)', 'free' => 'Tersedia Dasar', 'pro' => 'Aktif Otomatis Anti-Banned'],
                    ['feature' => 'Bantuan Tim Support (Customer Support)', 'free' => 'Standar Komunitas & Panduan', 'pro' => 'Prioritas VIP via WhatsApp Tim Sirkelta'],
                ],
            ],
        ];

        $paymentOrders = PaymentOrder::where('user_id', $user->id)
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'app_name' => $order->app_name,
                    'duration_months' => $order->duration_months,
                    'final_amount' => (float) $order->final_amount,
                    'payment_method_label' => $order->payment_method_label,
                    'status' => $order->status,
                    'rejection_reason' => $order->rejection_reason,
                    'created_at' => $order->created_at?->format('d M Y H:i'),
                ];
            });

        return Inertia::render('Profile/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
                'can_change_email' => $user->canChangeEmail(),
                'days_until_next_email_change' => $user->daysUntilNextEmailChange(),
                'email_changed_at' => $user->email_changed_at?->toIso8601String(),
            ],
            'referral' => [
                'code' => $user->username,
                'url' => url('/register?ref='.$user->username),
                'total_referees' => $totalReferees,
                'available_discount' => $availableDiscount,
                'total_earned_discount' => $totalEarnedDiscount,
                'logs' => $referralLogs,
            ],
            'proApps' => $proApps,
            'packagePricing' => $packagePricing,
            'comparisonPlans' => $comparisonPlans,
            'paymentOrders' => $paymentOrders,
            'paymentMethods' => [
                'manual_enabled' => (bool) ((int) WebsiteSetting::get('payment_manual_enabled', '1')),
                'bank_name' => WebsiteSetting::getWithDefault('payment_bank_name'),
                'bank_account_number' => WebsiteSetting::getWithDefault('payment_bank_account_number'),
                'bank_account_holder' => WebsiteSetting::getWithDefault('payment_bank_account_holder'),
                'qris_merchant_name' => WebsiteSetting::getWithDefault('payment_qris_merchant_name'),
                'qris_image_path' => WebsiteSetting::get('payment_qris_image_path') ? asset(WebsiteSetting::get('payment_qris_image_path')) : null,
                'support_whatsapp' => WebsiteSetting::getWithDefault('support_whatsapp'),
                'midtrans_enabled' => (bool) ((int) WebsiteSetting::get('payment_midtrans_enabled', '0')),
                'midtrans_mode' => WebsiteSetting::get('payment_midtrans_mode', 'sandbox'),
                'midtrans_client_key' => WebsiteSetting::get('payment_midtrans_client_key', ''),
            ],
            'status' => session('status'),
            'flash' => [
                'midtrans_snap_token' => session('midtrans_snap_token'),
                'order_number' => session('order_number'),
            ],
        ]);
    }

    /**
     * Send 6-digit OTP verification code to the user's current email to authorize an email change.
     */
    public function sendEmailOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'new_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ], [
            'new_email.required' => 'Alamat email baru wajib diisi.',
            'new_email.email' => 'Format alamat email baru tidak valid.',
            'new_email.unique' => 'Alamat email ini sudah terdaftar pada akun lain.',
        ]);

        $newEmail = strtolower(trim((string) $request->input('new_email')));
        if ($newEmail === strtolower((string) $user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email baru harus berbeda dari email Anda saat ini.',
            ], 422);
        }

        // Generate 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Cache for 15 minutes
        Cache::put('email_change_otp_'.$user->id, [
            'code' => $otp,
            'new_email' => $newEmail,
        ], now()->addMinutes(15));

        // Send OTP to user's current active email
        $user->notify(new EmailChangeVerificationNotification($otp, $newEmail));

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi 6 digit telah dikirim ke email aktif Anda saat ini ('.$user->email.'). Silakan periksa kotak masuk atau spam.',
        ]);
    }

    /**
     * Update user profile information (name, username, email, monthly_budget).
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Sanitize username: strip spaces and convert to lowercase
        if ($request->filled('username')) {
            $cleanedUsername = strtolower(str_replace(' ', '', (string) $request->input('username')));
            $request->merge(['username' => $cleanedUsername]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'alpha_dash:ascii',
                'min:3',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'email_otp' => ['nullable', 'string'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'username.required' => 'Username wajib diisi.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, garis bawah (_), atau strip (-) tanpa spasi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.max' => 'Username maksimal 50 karakter.',
            'username.unique' => 'Username ini sudah digunakan oleh pengguna lain.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar pada akun lain.',
            'monthly_budget.numeric' => 'Nominal batas anggaran harus berupa angka.',
            'monthly_budget.min' => 'Nominal batas anggaran tidak boleh kurang dari 0.',
        ]);

        if (array_key_exists('monthly_budget', $validated) && (float) $validated['monthly_budget'] <= 0) {
            $validated['monthly_budget'] = null;
        }

        // Check if username is being changed
        $newUsername = $validated['username'];
        $isUsernameChanged = $newUsername !== $user->username;
        if ($isUsernameChanged) {
            $user->username = $newUsername;
            $user->referral_code = $newUsername;
        }

        // Check if email is being changed
        $isEmailChanged = strtolower(trim((string) $validated['email'])) !== strtolower((string) $user->email);
        if ($isEmailChanged) {
            $otp = trim((string) $request->input('email_otp'));
            $cached = Cache::get('email_change_otp_'.$user->id);

            if (! $cached || ! hash_equals((string) $cached['code'], $otp) || strtolower($cached['new_email']) !== strtolower(trim((string) $validated['email']))) {
                return back()->withErrors([
                    'email_otp' => 'Kode verifikasi dari email Anda saat ini tidak valid atau telah kedaluwarsa. Silakan minta kode baru.',
                ])->withInput();
            }

            Cache::forget('email_change_otp_'.$user->id);
            $user->email = strtolower(trim((string) $validated['email']));
            $user->email_changed_at = now();
            $user->email_verified_at = null;
        }

        $user->name = $validated['name'];
        if (array_key_exists('monthly_budget', $validated)) {
            $user->monthly_budget = $validated['monthly_budget'];
        }
        $user->save();

        if ($isEmailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', 'Email berhasil diperbarui ke '.$user->email.'. Silakan klik tautan verifikasi yang kami kirimkan ke email baru Anda.');
        }

        return back()->with('success', 'Data profil akun berhasil diperbarui.');
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini salah.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }

    /**
     * Activate a free application into user workspace.
     */
    public function activateModule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:keuangan,wasender'],
        ], [
            'module.required' => 'Pilih modul aplikasi yang ingin diaktifkan.',
            'module.in' => 'Modul aplikasi tidak valid.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $module = $validated['module'];
        $roleName = "user_{$module}";

        if (! $user->hasRole($roleName) && ! $user->hasRole('superadmin')) {
            $user->assignRole($roleName);
        }

        $appName = $module === 'keuangan' ? 'Sirkelta Keuangan' : 'Sirkelta WaSender';

        return back()->with('success', "Aplikasi {$appName} berhasil diaktifkan ke ruang kerja Anda! Anda sekarang dapat beralih ruang kerja melalui menu Pindah Ruang Kerja atau Portal Hub.");
    }

    /**
     * Deactivate an application module from user workspace and purge all its data.
     */
    public function deactivateModule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:keuangan,wasender'],
            'confirmation_text' => ['required', 'string'],
        ], [
            'module.required' => 'Pilih modul aplikasi yang ingin dinonaktifkan.',
            'module.in' => 'Modul aplikasi tidak valid.',
            'confirmation_text.required' => 'Teks konfirmasi wajib diisi.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $module = $validated['module'];
        $expectedConfirmation = 'HAPUS '.strtoupper($module);

        if (strtoupper(trim($validated['confirmation_text'])) !== $expectedConfirmation) {
            return back()->withErrors([
                'confirmation_text' => "Teks konfirmasi tidak cocok. Harap ketik '{$expectedConfirmation}' untuk melanjutkan.",
            ]);
        }

        DB::transaction(function () use ($user, $module) {
            if ($module === 'keuangan') {
                // Delete all wallet shares involving this user
                WalletShare::where('user_id', $user->id)->orWhere('shared_by', $user->id)->delete();

                // Delete all transactions recorded by user
                Transaction::where('user_id', $user->id)->delete();

                // Delete all wallets owned by user
                Wallet::where('user_id', $user->id)->delete();

                // Reset user monthly budget limit
                $user->update(['monthly_budget' => null]);

                // Delete subscriptions & payment orders for keuangan
                UserSubscription::where('user_id', $user->id)->where('module', 'keuangan')->delete();
                PaymentOrder::where('user_id', $user->id)->where('module', 'keuangan')->delete();

                // Remove role
                if ($user->hasRole('user_keuangan')) {
                    $user->removeRole('user_keuangan');
                }
            } elseif ($module === 'wasender') {
                if (class_exists(Device::class)) {
                    Device::where('user_id', $user->id)->delete();
                }
                if (class_exists(Contact::class)) {
                    Contact::where('user_id', $user->id)->delete();
                }
                if (class_exists(MessageTemplate::class)) {
                    MessageTemplate::where('user_id', $user->id)->delete();
                }

                // Delete subscriptions & payment orders for wasender
                UserSubscription::where('user_id', $user->id)->where('module', 'wasender')->delete();
                PaymentOrder::where('user_id', $user->id)->where('module', 'wasender')->delete();

                // Remove role
                if ($user->hasRole('user_wasender')) {
                    $user->removeRole('user_wasender');
                }
            }

            // If current last_module was this module, reset or switch
            if ($user->last_module === $module) {
                $remaining = $user->accessibleModules();
                $newLastModule = ! empty($remaining) ? $remaining[0] : null;
                $user->forceFill(['last_module' => $newLastModule])->saveQuietly();
            }
        });

        $appName = $module === 'keuangan' ? 'Sirkelta Keuangan' : 'Sirkelta WaSender';

        return back()->with('success', "Aplikasi {$appName} berhasil dinonaktifkan dan seluruh datanya telah dihapus secara permanen.");
    }

    /**
     * Permanently delete user account and all associated data across the platform.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasRole('superadmin') && User::role('superadmin')->count() <= 1) {
            return back()->withErrors([
                'account' => 'Akun Superadmin utama tidak dapat dihapus untuk mencegah hilangnya akses sistem.',
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'confirmation_text' => ['required', 'string'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi untuk verifikasi keamanan.',
            'current_password.current_password' => 'Kata sandi saat ini tidak valid.',
            'confirmation_text.required' => 'Teks konfirmasi wajib diisi.',
        ]);

        if (strtoupper(trim($validated['confirmation_text'])) !== 'HAPUS AKUN SAYA') {
            return back()->withErrors([
                'confirmation_text' => "Teks konfirmasi tidak cocok. Harap ketik 'HAPUS AKUN SAYA' untuk mengonfirmasi.",
            ]);
        }

        Auth::logout();

        DB::transaction(function () use ($user) {
            // Delete all user related module data explicitly
            WalletShare::where('user_id', $user->id)->orWhere('shared_by', $user->id)->delete();
            Transaction::where('user_id', $user->id)->delete();
            Wallet::where('user_id', $user->id)->delete();

            if (class_exists(Device::class)) {
                Device::where('user_id', $user->id)->delete();
            }
            if (class_exists(Contact::class)) {
                Contact::where('user_id', $user->id)->delete();
            }
            if (class_exists(MessageTemplate::class)) {
                MessageTemplate::where('user_id', $user->id)->delete();
            }

            // Delete support messages & tickets
            SupportMessage::where('user_id', $user->id)->delete();
            SupportTicket::where('user_id', $user->id)->delete();

            // Delete referral logs & subscriptions & payment orders
            ReferralLog::where('referrer_id', $user->id)->orWhere('referee_id', $user->id)->delete();
            UserSubscription::where('user_id', $user->id)->delete();
            PaymentOrder::where('user_id', $user->id)->delete();

            // Delete user
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Akun Anda dan seluruh data telah berhasil dihapus secara permanen.');
    }

    /**
     * Upgrade or renew app Pro plan with expiration period (masa aktif).
     */
    public function upgradePro(Request $request): RedirectResponse
    {
        // Support backward compatibility if called from legacy referral form
        if ($request->filled('app_id') && ! $request->filled('module')) {
            $module = str_replace('_pro', '', (string) $request->input('app_id'));
            $request->merge(['module' => $module]);
        }
        if (! $request->filled('duration_months')) {
            $request->merge(['duration_months' => 1]);
        }

        $validated = $request->validate([
            'module' => ['required', 'string', 'in:keuangan,wasender'],
            'duration_months' => ['required', 'integer', 'in:1,3,6,12'],
        ], [
            'module.required' => 'Pilih modul aplikasi yang ingin di-upgrade.',
            'module.in' => 'Modul aplikasi tidak valid.',
            'duration_months.required' => 'Pilih masa aktif langganan.',
            'duration_months.in' => 'Durasi masa aktif tidak valid.',
        ]);

        /** @var User $user */
        $user = $request->user();

        $module = $validated['module'];
        $months = (int) $validated['duration_months'];

        // Ensure user has role for this module so they can access it immediately
        $roleName = "user_{$module}";
        if (! $user->hasRole($roleName) && ! $user->hasRole('superadmin') && ! $user->hasRole("admin_{$module}")) {
            $user->assignRole($roleName);
        }

        $tier = self::getPackagePricing()[$module][$months] ?? null;
        $totalPrice = $tier ? (float) $tier['price'] : (float) (($module === 'wasender' ? 99000 : 75000) * $months);

        // Apply available referral discount
        $availableDiscount = $user->availableReferralDiscount();
        $discountToApply = min($availableDiscount, (float) $totalPrice);
        $finalAmount = max(0, $totalPrice - $discountToApply);

        // Redeem referral logs if discount was used
        if ($discountToApply > 0) {
            $unredeemedLogs = ReferralLog::where('referrer_id', $user->id)
                ->where('is_redeemed', false)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'valid');
                })
                ->orderBy('id')
                ->get();

            $remainingDiscountToCover = $discountToApply;
            foreach ($unredeemedLogs as $log) {
                if ($remainingDiscountToCover <= 0) {
                    break;
                }
                $log->update([
                    'is_redeemed' => true,
                    'redeemed_at' => now(),
                    'applied_app' => $module.'_pro',
                ]);
                $remainingDiscountToCover -= (float) $log->reward_discount;
            }
        }

        // Calculate starts_at and expires_at with masa aktif
        $activeSubscription = $user->getActiveProSubscription($module);
        if ($activeSubscription && $activeSubscription->expires_at && $activeSubscription->expires_at->isFuture()) {
            $startsAt = $activeSubscription->expires_at;
            $expiresAt = $activeSubscription->expires_at->copy()->addMonths($months);
        } else {
            $startsAt = now();
            $expiresAt = now()->addMonths($months);
        }

        // Record subscription
        UserSubscription::create([
            'user_id' => $user->id,
            'module' => $module,
            'plan' => 'pro',
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'duration_months' => $months,
            'price' => $totalPrice,
            'discount_applied' => $discountToApply,
            'final_amount' => $finalAmount,
            'status' => 'active',
        ]);

        $appName = $module === 'keuangan' ? 'Sirkelta Keuangan Pro' : 'Sirkelta WaSender Pro';
        $formattedDate = $expiresAt->format('d M Y');

        return back()->with('success', "Selamat! Paket {$appName} berhasil diaktifkan dengan masa aktif hingga {$formattedDate}.");
    }

    /**
     * Create payment order for subscription (Manual Bank/QRIS or Midtrans Snap).
     */
    public function createPaymentOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', 'in:keuangan,wasender'],
            'duration_months' => ['required', 'integer', 'in:1,3,6,12'],
            'payment_method' => ['required', 'string', 'in:manual_bank,manual_qris,midtrans'],
            'payment_proof' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
        ], [
            'module.required' => 'Pilih modul aplikasi yang ingin diaktifkan.',
            'duration_months.required' => 'Pilih masa aktif langganan.',
            'payment_method.required' => 'Pilih metode pembayaran.',
            'payment_proof.image' => 'File bukti transfer harus berupa gambar.',
            'payment_proof.max' => 'Ukuran file bukti transfer maksimal 3MB.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $module = $validated['module'];
        $months = (int) $validated['duration_months'];
        $method = $validated['payment_method'];

        $tier = self::getPackagePricing()[$module][$months] ?? null;
        $normalPrice = $tier ? (float) $tier['price'] : (float) (($module === 'wasender' ? 99000 : 75000) * $months);

        $availableDiscount = $user->availableReferralDiscount();
        $discountToApply = min($availableDiscount, (float) $normalPrice);
        $finalAmount = max(0, $normalPrice - $discountToApply);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('payment_proofs', 'public');
            $proofPath = 'storage/'.$path;
        }

        $orderNumber = PaymentOrder::generateOrderNumber();
        $appName = $module === 'keuangan' ? 'Sirkelta Keuangan Pro' : 'Sirkelta WaSender Pro';

        // If 100% covered by referral discount, auto approve immediately
        if ($finalAmount <= 0) {
            DB::transaction(function () use ($user, $module, $months, $normalPrice, $discountToApply, $orderNumber) {
                // Deduct referral
                $unredeemedLogs = ReferralLog::where('referrer_id', $user->id)
                    ->where('is_redeemed', false)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', 'valid');
                    })
                    ->orderBy('id')
                    ->get();

                $remaining = $discountToApply;
                foreach ($unredeemedLogs as $log) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $log->update([
                        'is_redeemed' => true,
                        'redeemed_at' => now(),
                        'applied_app' => $module.'_pro',
                    ]);
                    $remaining -= (float) $log->reward_discount;
                }

                $activeSub = $user->getActiveProSubscription($module);
                $startsAt = ($activeSub && $activeSub->expires_at && $activeSub->expires_at->isFuture())
                    ? $activeSub->expires_at
                    : now();
                $expiresAt = $startsAt->copy()->addMonths($months);

                UserSubscription::create([
                    'user_id' => $user->id,
                    'module' => $module,
                    'plan' => 'pro',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'duration_months' => $months,
                    'price' => $normalPrice,
                    'discount_applied' => $discountToApply,
                    'final_amount' => 0,
                    'status' => 'active',
                ]);

                // Assign role
                $roleName = "user_{$module}";
                if (! $user->hasRole($roleName) && ! $user->hasRole('superadmin')) {
                    $user->assignRole($roleName);
                }

                PaymentOrder::create([
                    'order_number' => $orderNumber,
                    'user_id' => $user->id,
                    'module' => $module,
                    'duration_months' => $months,
                    'gross_amount' => $normalPrice,
                    'discount_applied' => $discountToApply,
                    'final_amount' => 0,
                    'payment_method' => 'referral_discount',
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            });

            return back()->with('success', "Selamat! Paket {$appName} berhasil diaktifkan dengan 100% potongan referral.");
        }

        $snapToken = null;
        if ($method === 'midtrans') {
            $isProduction = WebsiteSetting::get('payment_midtrans_mode', 'sandbox') === 'production';
            $serverKey = WebsiteSetting::get('payment_midtrans_server_key', '');
            $snapUrl = $isProduction
                ? 'https://app.midtrans.com/snap/v1/transactions'
                : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

            if (! empty($serverKey)) {
                try {
                    $response = Http::withBasicAuth($serverKey, '')
                        ->acceptJson()
                        ->post($snapUrl, [
                            'transaction_details' => [
                                'order_id' => $orderNumber,
                                'gross_amount' => (int) round($finalAmount),
                            ],
                            'customer_details' => [
                                'first_name' => $user->name,
                                'email' => $user->email,
                            ],
                            'item_details' => [
                                [
                                    'id' => $module.'_pro',
                                    'price' => (int) round($finalAmount),
                                    'quantity' => 1,
                                    'name' => "{$appName} ({$months} Bulan)",
                                ],
                            ],
                        ]);

                    if ($response->successful()) {
                        $snapToken = $response->json('token');
                    }
                } catch (\Throwable $e) {
                    Log::error('Midtrans Snap Error: '.$e->getMessage());
                }
            }
        }

        PaymentOrder::create([
            'order_number' => $orderNumber,
            'user_id' => $user->id,
            'module' => $module,
            'duration_months' => $months,
            'gross_amount' => $normalPrice,
            'discount_applied' => $discountToApply,
            'final_amount' => $finalAmount,
            'payment_method' => $method,
            'payment_proof_path' => $proofPath,
            'status' => 'pending',
            'midtrans_snap_token' => $snapToken,
        ]);

        if ($method === 'midtrans' && $snapToken) {
            return back()->with([
                'success' => "Pesanan #{$orderNumber} berhasil dibuat! Silakan selesaikan pembayaran di jendela Midtrans.",
                'midtrans_snap_token' => $snapToken,
                'order_number' => $orderNumber,
            ]);
        }

        return back()->with('success', "Pesanan #{$orderNumber} berhasil dibuat! Silakan lakukan transfer dan konfirmasi pembayaran.");
    }

    /**
     * Handle Midtrans HTTP notification webhook.
     */
    public function midtransWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $fraudStatus = $payload['fraud_status'] ?? null;
        $paymentType = $payload['payment_type'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;

        if (! $orderId) {
            return response()->json(['message' => 'Missing order_id'], 400);
        }

        $order = PaymentOrder::where('order_number', $orderId)->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $serverKey = WebsiteSetting::get('payment_midtrans_server_key', '');
        $expectedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! empty($serverKey) && $signatureKey && $signatureKey !== $expectedSignature) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $order->update([
            'midtrans_transaction_id' => $transactionId,
            'midtrans_payment_type' => $paymentType,
        ]);

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            if ($fraudStatus === 'challenge') {
                $order->update(['status' => 'pending']);
            } else {
                if ($order->status !== 'paid') {
                    app(AdminPaymentOrderController::class)->approve($order);
                }
            }
        } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny' || $transactionStatus === 'expire') {
            $order->update(['status' => 'rejected', 'rejection_reason' => 'Midtrans status: '.$transactionStatus]);
        } elseif ($transactionStatus === 'pending') {
            $order->update(['status' => 'pending']);
        }

        return response()->json(['status' => 'ok']);
    }
}
