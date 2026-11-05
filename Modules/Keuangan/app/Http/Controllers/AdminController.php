<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReferralLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    /**
     * Display the Admin Keuangan management hub, user directory, and referral anti-fraud center.
     */
    public function index(Request $request): Response
    {
        // 1. Global Governance & Referral Anti-Fraud KPI Metrics (Confidential user cash is strictly excluded)
        $totalUsers = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['user_keuangan', 'admin_keuangan', 'superadmin']);
        })->orWhereHas('wallets')->count();

        $totalReferralRewardsIssued = (float) ReferralLog::sum('reward_discount');
        $totalReferralRewardsRedeemed = (float) ReferralLog::where('is_redeemed', true)->sum('reward_discount');
        $totalReferralRewardsAvailable = (float) ReferralLog::where('is_redeemed', false)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'valid');
            })
            ->sum('reward_discount');

        $totalReferralsCount = ReferralLog::count();
        $flaggedReferralsCount = ReferralLog::whereIn('status', ['suspect', 'blocked'])->count();
        $blockedReferralsCount = ReferralLog::where('status', 'blocked')->count();

        // 2. Filterable User Roster (User profile, role, referral & activity tracking only)
        $userQuery = User::where(function ($q) {
            $q->whereHas('roles', function ($sub) {
                $sub->whereIn('name', ['user_keuangan', 'admin_keuangan', 'superadmin']);
            })->orWhereHas('wallets');
        })
            ->with(['roles:id,name', 'referrer:id,name,username'])
            ->withCount('referrals')
            ->addSelect([
                'users.*',
                'last_session_activity' => DB::table('sessions')
                    ->select('last_activity')
                    ->whereColumn('user_id', 'users.id')
                    ->latest('last_activity')
                    ->limit(1),
            ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $userQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->role;
            $userQuery->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $userQuery->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(function ($u) {
                $lastActiveCarbon = $u->last_session_activity
                    ? Carbon::createFromTimestamp($u->last_session_activity)
                    : ($u->updated_at ?? $u->created_at);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username ?? '-',
                    'email' => $u->email,
                    'roles' => $u->roles->pluck('name')->toArray(),
                    'referral_code' => $u->referral_code,
                    'referred_by' => $u->referrer ? [
                        'name' => $u->referrer->name,
                        'username' => $u->referrer->username,
                    ] : null,
                    'referrals_count' => $u->referrals_count,
                    'created_at' => $u->created_at?->format('d M Y') ?? '-',
                    'last_active_human' => $lastActiveCarbon ? $lastActiveCarbon->locale('id')->diffForHumans() : '-',
                    'last_active_date' => $lastActiveCarbon ? $lastActiveCarbon->format('d M Y H:i') : '-',
                ];
            });

        // 3. Referral Anti-Fraud Center Logs
        $referralQuery = ReferralLog::with([
            'referrer:id,name,username,email,referral_code',
            'referee:id,name,username,email,created_at',
            'referee.wallets:id,user_id',
            'reviewer:id,name',
        ]);

        if ($request->filled('referral_filter')) {
            $rFilter = $request->referral_filter;
            if ($rFilter === 'valid') {
                $referralQuery->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', 'valid');
                });
            } elseif ($rFilter === 'suspect') {
                $referralQuery->where('status', 'suspect');
            } elseif ($rFilter === 'blocked') {
                $referralQuery->where('status', 'blocked');
            } elseif ($rFilter === 'redeemed') {
                $referralQuery->where('is_redeemed', true);
            } elseif ($rFilter === 'unredeemed') {
                $referralQuery->where('is_redeemed', false);
            }
        }

        if ($request->filled('referral_search')) {
            $rSearch = $request->referral_search;
            $referralQuery->where(function ($q) use ($rSearch) {
                $q->whereHas('referrer', function ($sub) use ($rSearch) {
                    $sub->where('name', 'like', "%{$rSearch}%")
                        ->orWhere('username', 'like', "%{$rSearch}%")
                        ->orWhere('email', 'like', "%{$rSearch}%")
                        ->orWhere('referral_code', 'like', "%{$rSearch}%");
                })->orWhereHas('referee', function ($sub) use ($rSearch) {
                    $sub->where('name', 'like', "%{$rSearch}%")
                        ->orWhere('username', 'like', "%{$rSearch}%")
                        ->orWhere('email', 'like', "%{$rSearch}%");
                });
            });
        }

        $referralLogs = $referralQuery->latest('id')
            ->take(50)
            ->get()
            ->map(function ($log) {
                $refereeWalletsCount = $log->referee ? $log->referee->wallets->count() : 0;
                $isGhost = $refereeWalletsCount === 0;

                // Automated heuristic risk warning
                $riskFlags = [];
                if ($isGhost) {
                    $riskFlags[] = 'Akun Kosong (0 Dompet)';
                }
                if ($log->referrer_id === $log->referee_id) {
                    $riskFlags[] = 'Self-Referral';
                }

                return [
                    'id' => $log->id,
                    'referrer' => [
                        'id' => $log->referrer?->id,
                        'name' => $log->referrer?->name ?? 'Pengguna Dihapus',
                        'username' => $log->referrer?->username ?? '-',
                        'email' => $log->referrer?->email ?? '-',
                        'referral_code' => $log->referrer?->referral_code,
                    ],
                    'referee' => [
                        'id' => $log->referee?->id,
                        'name' => $log->referee?->name ?? 'Pengguna Dihapus',
                        'username' => $log->referee?->username ?? '-',
                        'email' => $log->referee?->email ?? '-',
                        'wallets_count' => $refereeWalletsCount,
                        'joined_at' => $log->referee?->created_at?->format('d M Y') ?? '-',
                    ],
                    'reward_discount' => (float) $log->reward_discount,
                    'status' => $log->status ?? 'valid',
                    'fraud_reason' => $log->fraud_reason,
                    'risk_flags' => $riskFlags,
                    'is_redeemed' => (bool) $log->is_redeemed,
                    'redeemed_at' => $log->redeemed_at?->format('d M Y H:i'),
                    'applied_app' => $log->applied_app,
                    'reviewer' => $log->reviewer ? [
                        'name' => $log->reviewer->name,
                    ] : null,
                    'reviewed_at' => $log->reviewed_at?->format('d M Y H:i'),
                    'created_at' => $log->created_at?->format('d M Y H:i'),
                ];
            });

        return Inertia::render('Keuangan::Admin/Index', [
            'metrics' => [
                'total_users' => $totalUsers,
                'total_referrals_count' => $totalReferralsCount,
                'total_referral_rewards_available' => $totalReferralRewardsAvailable,
                'total_referral_rewards_issued' => $totalReferralRewardsIssued,
                'total_referral_rewards_redeemed' => $totalReferralRewardsRedeemed,
                'flagged_referrals_count' => $flaggedReferralsCount,
                'blocked_referrals_count' => $blockedReferralsCount,
            ],
            'users' => $users,
            'referral_logs' => $referralLogs,
            'filters' => [
                'search' => $request->search ?? '',
                'role' => $request->role ?? '',
                'referral_filter' => $request->referral_filter ?? '',
                'referral_search' => $request->referral_search ?? '',
            ],
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'roles' => $request->user()->getRoleNames()->toArray(),
                'accessible_modules' => $request->user()->accessibleModules(),
            ],
        ]);
    }

    /**
     * Update referral log anti-fraud status (verify / flag / block).
     */
    public function updateReferralStatus(Request $request, ReferralLog $referralLog): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:valid,suspect,blocked'],
            'fraud_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'status.required' => 'Status referral wajib dipilih.',
            'status.in' => 'Status referral tidak valid.',
        ]);

        $referralLog->update([
            'status' => $validated['status'],
            'fraud_reason' => $validated['fraud_reason'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $statusLabel = match ($validated['status']) {
            'valid' => 'Sah / Diverifikasi (Valid)',
            'suspect' => 'Mencurigakan (Suspect)',
            'blocked' => 'Dibekukan / Diblokir (Fraud)',
        };

        return back()->with('success', "Status reward referral berhasil diperbarui menjadi {$statusLabel}.");
    }
}
