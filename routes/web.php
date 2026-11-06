<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPaymentOrderController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWebsiteController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Landing Page
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
})->name('home');

// Public Information & Legal Pages
Route::get('/faq', [FaqController::class, 'index'])->name('faq');
Route::get('/tentang-kami', [LegalController::class, 'aboutUs'])->name('about');
Route::get('/hubungi-kami', [LegalController::class, 'contactUs'])->name('contact');
Route::get('/kebijakan-privasi', [LegalController::class, 'privacyPolicy'])->name('privacy');
Route::get('/syarat-ketentuan', [LegalController::class, 'termsOfService'])->name('terms');
Route::get('/kebijakan-pengembalian-dana', [LegalController::class, 'refundPolicy'])->name('refund');

// Public / User Support Chat API
Route::get('/support/chat', [SupportController::class, 'getActiveChat'])->name('support.chat.get');
Route::post('/support/chat', [SupportController::class, 'sendMessage'])->name('support.chat.send');

// Real-time Username Availability Check
Route::get('/check-username', [AuthController::class, 'checkUsername'])->name('username.check');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register.attempt');
});

// Email Verification Notice & Handlers
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

// Authenticated User Routes
Route::middleware('auth')->group(function () {
    // Universal Dashboard Endpoint (Used by PWA and direct navigation)
    Route::get('/dashboard', function () {
        /** @var User $user */
        $user = auth()->user();

        return redirect()->to($user->resolveDefaultDashboardRoute());
    })->name('dashboard');

    Route::get('/portal', [PortalController::class, 'index'])->middleware('verified')->name('portal');
    // Profile, Security, Pro Subscriptions, and Referral
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/email/send-otp', [ProfileController::class, 'sendEmailOtp'])->name('profile.email.send-otp');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/modules/activate', [ProfileController::class, 'activateModule'])->name('profile.modules.activate');
    Route::post('/profile/modules/deactivate', [ProfileController::class, 'deactivateModule'])->name('profile.modules.deactivate');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/payment/order', [ProfileController::class, 'createPaymentOrder'])->name('profile.payment.order');
    Route::post('/profile/pro/upgrade', [ProfileController::class, 'upgradePro'])->name('profile.pro.upgrade');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Legacy Referral redirects & aliases
    Route::redirect('/referral', '/profile')->name('referral.index');
    Route::post('/referral/redeem', [ProfileController::class, 'upgradePro'])->name('referral.redeem');

    // Role Admin for Managing Website & Users
    Route::prefix('admin')->name('admin.')->group(function () {
        // Unified Admin Operations Hub (Pusat Kontrol Admin)
        Route::middleware('role:superadmin|admin_web|admin_keuangan')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        });

        // Superadmin ONLY: User Management & Website Settings / FAQ
        Route::middleware('role:superadmin')->group(function () {
            // User Management
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

            // Payment Orders & Transactions Management
            Route::get('/payments', [AdminPaymentOrderController::class, 'index'])->name('payments.index');
            Route::post('/payments/{order}/approve', [AdminPaymentOrderController::class, 'approve'])->name('payments.approve');
            Route::post('/payments/{order}/reject', [AdminPaymentOrderController::class, 'reject'])->name('payments.reject');

            // Website Settings, FAQs & Payment Configuration
            Route::get('/website', [AdminWebsiteController::class, 'index'])->name('website.index');
            Route::put('/website/settings', [AdminWebsiteController::class, 'updateSettings'])->name('website.settings.update');
            Route::match(['put', 'post'], '/website/payment-settings', [AdminWebsiteController::class, 'updatePaymentSettings'])->name('website.payment.update');
            Route::post('/website/payment-settings/test-midtrans', [AdminWebsiteController::class, 'testMidtrans'])->name('website.payment.test-midtrans');
            Route::put('/website/page', [AdminWebsiteController::class, 'updatePage'])->name('website.page.update');
            Route::post('/website/faqs', [AdminWebsiteController::class, 'storeFaq'])->name('website.faqs.store');
            Route::put('/website/faqs/{faq}', [AdminWebsiteController::class, 'updateFaq'])->name('website.faqs.update');
            Route::delete('/website/faqs/{faq}', [AdminWebsiteController::class, 'destroyFaq'])->name('website.faqs.destroy');
        });

        // Aduan Masuk (Support Tickets) & Clean Purge Resolution (Accessible by superadmin & admin_web)
        Route::middleware('role:superadmin|admin_web')->group(function () {
            Route::get('/aduan', [AdminSupportController::class, 'index'])->name('support.index');
            Route::get('/aduan/{ticket}', [AdminSupportController::class, 'show'])->name('support.show');
            Route::post('/aduan/{ticket}/reply', [AdminSupportController::class, 'reply'])->name('support.reply');
            Route::post('/aduan/{ticket}/resolve', [AdminSupportController::class, 'resolveAndClean'])->name('support.resolve');
        });
    });
});

// Midtrans Payment Gateway Webhook Notification Endpoint (External Public Access)
Route::match(['get', 'post'], '/api/midtrans/webhook', [ProfileController::class, 'midtransWebhook'])->name('midtrans.webhook');
