<?php

namespace App\Http\Controllers;

use App\Models\ReferralLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    /**
     * Display the referral dashboard and renewal discount center.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (empty($user->referral_code) || $user->referral_code !== $user->username) {
            $user->update(['referral_code' => $user->username]);
            $user->refresh();
        }

        $logs = ReferralLog::where('referrer_id', $user->id)
            ->with('referee:id,name,username,email,created_at')
            ->latest()
            ->take(20)
            ->get();

        $totalReferees = User::where('referred_by', $user->id)->count();
        $availableDiscount = $user->availableReferralDiscount();
        $totalEarnedDiscount = (float) ReferralLog::where('referrer_id', $user->id)->sum('reward_discount');

        $proApps = [
            [
                'id' => 'keuangan_pro',
                'name' => 'Sirkelta Keuangan Pro',
                'module' => 'keuangan',
                'monthly_price' => 75000,
                'description' => 'Unlimited dompet, laporan laba rugi lengkap, multi-user kasir, dan export Excel.',
            ],
            [
                'id' => 'wasender_pro',
                'name' => 'Sirkelta WaSender Pro',
                'module' => 'wasender',
                'monthly_price' => 99000,
                'description' => 'Multi-device WhatsApp, broadcast tanpa batas harian, dan auto-responder cerdas.',
            ],
        ];

        return Inertia::render('Referral/Index', [
            'referralCode' => $user->username,
            'referralUrl' => url('/register?ref='.$user->username),
            'totalReferees' => $totalReferees,
            'availableDiscount' => $availableDiscount,
            'totalEarnedDiscount' => $totalEarnedDiscount,
            'referralLogs' => $logs,
            'proApps' => $proApps,
        ]);
    }

    /**
     * Apply referral discount toward app Pro renewal.
     */
    public function redeemForRenewal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_id' => ['required', 'string', 'in:keuangan_pro,wasender_pro'],
        ], [
            'app_id.required' => 'Pilih aplikasi yang ingin diperpanjang.',
            'app_id.in' => 'Aplikasi yang dipilih tidak valid.',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (empty($user->referral_code) || $user->referral_code !== $user->username) {
            $user->update(['referral_code' => $user->username]);
            $user->refresh();
        }

        $availableDiscount = $user->availableReferralDiscount();
        if ($availableDiscount <= 0) {
            return back()->with('error', 'Anda belum memiliki saldo potongan referral. Bagikan kode Anda ke teman untuk mendapatkan potongan perpanjangan!');
        }

        // Fetch unredeemed valid logs
        $unredeemedLogs = ReferralLog::where('referrer_id', $user->id)
            ->where('is_redeemed', false)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'valid');
            })
            ->get();

        $appliedTotal = 0;
        foreach ($unredeemedLogs as $log) {
            $log->update([
                'is_redeemed' => true,
                'redeemed_at' => now(),
                'applied_app' => $validated['app_id'],
            ]);
            $appliedTotal += (float) $log->reward_discount;
        }

        $appName = $validated['app_id'] === 'keuangan_pro' ? 'Sirkelta Keuangan Pro' : 'Sirkelta WaSender Pro';
        $formattedAmount = 'Rp '.number_format($appliedTotal, 0, ',', '.');

        return back()->with('success', "Selamat! Potongan senilai {$formattedAmount} berhasil diterapkan untuk perpanjangan {$appName}.");
    }
}
