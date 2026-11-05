<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\ReferralLog;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminPaymentOrderController extends Controller
{
    /**
     * Display a listing of payment orders and transaction ledger.
     */
    public function index(Request $request): Response
    {
        // 1. KPI Metrics
        $totalOrders = PaymentOrder::count();
        $pendingOrders = PaymentOrder::where('status', 'pending')->count();
        $paidOrders = PaymentOrder::where('status', 'paid')->count();
        $totalRevenue = (float) PaymentOrder::where('status', 'paid')->sum('final_amount');

        // 2. Query builder
        $query = PaymentOrder::with([
            'user:id,name,username,email',
            'verifier:id,name',
        ])->latest('id');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('module') && $request->module !== 'all') {
            $query->where('module', $request->module);
        }

        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(12)->withQueryString()->through(function ($order) {
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

        return Inertia::render('Admin/Payments/Index', [
            'orders' => $orders,
            'metrics' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'paid_orders' => $paidOrders,
                'total_revenue' => $totalRevenue,
            ],
            'filters' => $request->only(['status', 'module', 'payment_method', 'search']),
        ]);
    }

    /**
     * Approve manual payment and activate Pro subscription.
     */
    public function approve(PaymentOrder $order): RedirectResponse
    {
        if ($order->status !== 'pending') {
            return back()->with('error', 'Transaksi ini tidak lagi dalam status menunggu.');
        }

        /** @var User $user */
        $user = $order->user;
        if (! $user) {
            return back()->with('error', 'Pengguna untuk transaksi ini tidak ditemukan.');
        }

        DB::transaction(function () use ($order, $user) {
            // 1. Mark order as paid
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'verified_by' => Auth::id(),
            ]);

            // 2. Grant module access role if not present
            $roleName = "user_{$order->module}";
            if (! $user->hasRole($roleName) && ! $user->hasRole('superadmin') && ! $user->hasRole("admin_{$order->module}")) {
                $user->assignRole($roleName);
            }

            // 3. Redeem referral discount if used
            if ($order->discount_applied > 0) {
                $unredeemedLogs = ReferralLog::where('referrer_id', $user->id)
                    ->where('is_redeemed', false)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', 'valid');
                    })
                    ->orderBy('id')
                    ->get();

                $remainingDiscountToCover = (float) $order->discount_applied;
                foreach ($unredeemedLogs as $log) {
                    if ($remainingDiscountToCover <= 0) {
                        break;
                    }
                    $log->update([
                        'is_redeemed' => true,
                        'redeemed_at' => now(),
                        'applied_app' => $order->module.'_pro',
                    ]);
                    $remainingDiscountToCover -= (float) $log->reward_discount;
                }
            }

            // 4. Calculate subscription dates
            $activeSub = $user->getActiveProSubscription($order->module);
            if ($activeSub && $activeSub->expires_at && $activeSub->expires_at->isFuture()) {
                $startsAt = $activeSub->expires_at;
                $expiresAt = $activeSub->expires_at->copy()->addMonths($order->duration_months);
            } else {
                $startsAt = now();
                $expiresAt = now()->addMonths($order->duration_months);
            }

            // 5. Create user subscription
            UserSubscription::create([
                'user_id' => $user->id,
                'module' => $order->module,
                'plan' => 'pro',
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'duration_months' => $order->duration_months,
                'price' => $order->gross_amount,
                'discount_applied' => $order->discount_applied,
                'final_amount' => $order->final_amount,
                'status' => 'active',
            ]);
        });

        $formattedExpiry = now()->addMonths($order->duration_months)->format('d M Y');

        return back()->with('success', "Pembayaran invoice {$order->order_number} berhasil disetujui! Paket {$order->app_name} untuk @{$user->username} aktif.");
    }

    /**
     * Reject manual payment order with reason.
     */
    public function reject(Request $request, PaymentOrder $order): RedirectResponse
    {
        if ($order->status !== 'pending') {
            return back()->with('error', 'Transaksi ini tidak lagi dalam status menunggu.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'verified_by' => Auth::id(),
        ]);

        return back()->with('success', "Pesanan invoice {$order->order_number} telah ditolak.");
    }
}
