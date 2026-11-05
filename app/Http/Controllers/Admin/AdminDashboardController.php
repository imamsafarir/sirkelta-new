<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\ReferralLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminDashboardController extends Controller
{
    /**
     * Unified Admin Operations Hub (Pusat Kontrol Admin).
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $isSuperadmin = $user->hasRole('superadmin');
        $isAdminWeb = $user->hasRole('admin_web');
        $isAdminKeuangan = $user->hasRole('admin_keuangan');

        // Determine accessible tabs for this admin user
        $accessibleTabs = [];
        if ($isSuperadmin) {
            $accessibleTabs = ['payments', 'users', 'support', 'fraud'];
        } else {
            if ($isAdminWeb) {
                $accessibleTabs[] = 'support';
            }
            if ($isAdminKeuangan) {
                $accessibleTabs[] = 'fraud';
            }
        }

        // Determine active tab
        $requestedTab = $request->query('tab');
        if ($requestedTab && in_array($requestedTab, $accessibleTabs)) {
            $currentTab = $requestedTab;
        } else {
            $currentTab = $accessibleTabs[0] ?? 'payments';
        }

        // Global Alerts & Attention KPI (Always loaded for top alert banner)
        $globalAlerts = [
            'pending_orders' => PaymentOrder::where('status', 'pending')->count(),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'unread_messages' => SupportMessage::where('sender_type', 'user')->where('is_read', false)->count(),
            'total_users' => User::count(),
            'total_revenue' => (float) PaymentOrder::where('status', 'paid')->sum('final_amount'),
            'flagged_referrals' => ReferralLog::whereIn('status', ['suspect', 'blocked'])->count(),
        ];

        $tabData = [
            'currentTab' => $currentTab,
            'accessibleTabs' => $accessibleTabs,
            'globalAlerts' => $globalAlerts,
        ];

        // 1. TAB: PAYMENTS (Transaksi & Pembayaran Pro)
        if ($currentTab === 'payments') {
            $orderQuery = PaymentOrder::with([
                'user:id,name,username,email',
                'verifier:id,name',
            ])->latest('id');

            if ($request->filled('status') && $request->status !== 'all') {
                $orderQuery->where('status', $request->status);
            }
            if ($request->filled('module') && $request->module !== 'all') {
                $orderQuery->where('module', $request->module);
            }
            if ($request->filled('payment_method') && $request->payment_method !== 'all') {
                $orderQuery->where('payment_method', $request->payment_method);
            }
            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $orderQuery->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            $orders = $orderQuery->paginate(12)->withQueryString()->through(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'user' => [
                        'id' => $order->user?->id,
                        'name' => $order->user?->name ?? 'Pengguna Dihapus',
                        'username' => $order->user?->username ?? '-',
                        'email' => $order->user?->email ?? '-',
                    ],
                    'module' => $order->module,
                    'app_name' => $order->app_name,
                    'duration_months' => $order->duration_months,
                    'gross_amount' => (float) $order->gross_amount,
                    'discount_applied' => (float) $order->discount_applied,
                    'final_amount' => (float) $order->final_amount,
                    'payment_method' => $order->payment_method,
                    'payment_method_label' => $order->payment_method_label,
                    'payment_proof_path' => $order->payment_proof_path ? asset($order->payment_proof_path) : null,
                    'status' => $order->status,
                    'rejection_reason' => $order->rejection_reason,
                    'midtrans_snap_token' => $order->midtrans_snap_token,
                    'midtrans_transaction_id' => $order->midtrans_transaction_id,
                    'created_at' => $order->created_at?->format('d M Y H:i'),
                    'created_at_human' => $order->created_at?->diffForHumans(),
                    'paid_at' => $order->paid_at?->format('d M Y H:i'),
                    'verifier' => $order->verifier ? ['name' => $order->verifier->name] : null,
                ];
            });

            $tabData['payments'] = [
                'orders' => $orders,
                'metrics' => [
                    'total_orders' => PaymentOrder::count(),
                    'pending_orders' => $globalAlerts['pending_orders'],
                    'paid_orders' => PaymentOrder::where('status', 'paid')->count(),
                    'total_revenue' => $globalAlerts['total_revenue'],
                ],
                'filters' => $request->only(['status', 'module', 'payment_method', 'search']),
            ];
        }

        // 2. TAB: USERS (Kelola Pengguna)
        if ($currentTab === 'users') {
            $userQuery = User::query()
                ->with(['roles:id,name', 'referrer:id,name,username'])
                ->withCount(['referees', 'wallets', 'transactions']);

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $userQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('referral_code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('role')) {
                $userQuery->role($request->role);
            }

            $users = $userQuery->latest('id')->paginate(12)->withQueryString();
            $roles = Role::pluck('name');

            $tabData['users'] = [
                'users' => $users,
                'roles' => $roles,
                'filters' => $request->only(['search', 'role']),
            ];
        }

        // 3. TAB: SUPPORT (Layanan Aduan Pengguna)
        if ($currentTab === 'support') {
            $ticketStatus = $request->query('ticket_status', 'open');

            $ticketQuery = SupportTicket::query()
                ->with(['user:id,name,username,email'])
                ->withCount([
                    'messages',
                    'messages as unread_count' => function ($q) {
                        $q->where('sender_type', 'user')->where('is_read', false);
                    },
                ]);

            if ($ticketStatus !== 'all') {
                $ticketQuery->where('status', $ticketStatus);
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $ticketQuery->where(function ($q) use ($search) {
                    $q->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            $tickets = $ticketQuery->latest('id')->paginate(12)->withQueryString()->through(function ($t) {
                return [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'user' => $t->user ? [
                        'id' => $t->user->id,
                        'name' => $t->user->name,
                        'username' => $t->user->username,
                        'email' => $t->user->email,
                    ] : null,
                    'guest_name' => $t->guest_name,
                    'guest_email' => $t->guest_email,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'messages_count' => $t->messages_count,
                    'unread_count' => $t->unread_count,
                    'created_at' => $t->created_at?->format('d M Y H:i'),
                    'created_at_human' => $t->created_at?->diffForHumans(),
                    'resolved_at' => $t->resolved_at?->format('d M Y H:i'),
                ];
            });

            $tabData['support'] = [
                'tickets' => $tickets,
                'stats' => [
                    'open_count' => SupportTicket::where('status', 'open')->count(),
                    'resolved_count' => WebsiteSetting::getResolvedComplaintsCount(),
                    'unread_messages' => $globalAlerts['unread_messages'],
                ],
                'filters' => [
                    'ticket_status' => $ticketStatus,
                    'search' => $request->search ?? '',
                ],
            ];
        }

        // 4. TAB: FRAUD (Audit Referral & Anti-Fraud)
        if ($currentTab === 'fraud') {
            $fraudQuery = ReferralLog::with([
                'referrer:id,name,username,email,referral_code',
                'referee:id,name,username,email,created_at',
                'referee.wallets:id,user_id',
                'reviewer:id,name',
            ]);

            if ($request->filled('referral_filter')) {
                $rFilter = $request->referral_filter;
                if ($rFilter === 'valid') {
                    $fraudQuery->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', 'valid');
                    });
                } elseif ($rFilter === 'suspect') {
                    $fraudQuery->where('status', 'suspect');
                } elseif ($rFilter === 'blocked') {
                    $fraudQuery->where('status', 'blocked');
                } elseif ($rFilter === 'redeemed') {
                    $fraudQuery->where('is_redeemed', true);
                } elseif ($rFilter === 'unredeemed') {
                    $fraudQuery->where('is_redeemed', false);
                }
            }

            if ($request->filled('search')) {
                $search = trim((string) $request->search);
                $fraudQuery->where(function ($q) use ($search) {
                    $q->whereHas('referrer', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('referral_code', 'like', "%{$search}%");
                    })->orWhereHas('referee', function ($eq) use ($search) {
                        $eq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            }

            $referralLogs = $fraudQuery->latest('id')->paginate(12)->withQueryString()->through(function ($log) {
                $refereeWalletsCount = $log->referee ? $log->referee->wallets->count() : 0;
                $riskFlags = [];
                if ($refereeWalletsCount === 0) {
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
                    'reviewer' => $log->reviewer ? ['name' => $log->reviewer->name] : null,
                    'reviewed_at' => $log->reviewed_at?->format('d M Y H:i'),
                    'created_at' => $log->created_at?->format('d M Y H:i'),
                ];
            });

            $tabData['fraud'] = [
                'logs' => $referralLogs,
                'metrics' => [
                    'total_referrals_count' => ReferralLog::count(),
                    'flagged_referrals_count' => $globalAlerts['flagged_referrals'],
                    'blocked_referrals_count' => ReferralLog::where('status', 'blocked')->count(),
                    'total_rewards_available' => (float) ReferralLog::where('is_redeemed', false)->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', 'valid');
                    })->sum('reward_discount'),
                    'total_rewards_redeemed' => (float) ReferralLog::where('is_redeemed', true)->sum('reward_discount'),
                ],
                'filters' => [
                    'referral_filter' => $request->referral_filter ?? '',
                    'search' => $request->search ?? '',
                ],
            ];
        }

        return Inertia::render('Admin/Dashboard', $tabData);
    }
}
