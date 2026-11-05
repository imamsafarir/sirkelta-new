<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\WalletShare;

class DebtRadarController extends Controller
{
    /**
     * Display a listing of debts and receivables.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $accessibleWalletIds = $user->wallets()->pluck('id')->merge(
            WalletShare::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->where('is_active', true)
                ->pluck('wallet_id')
        )->unique();

        $piutang = Transaction::where(function ($q) use ($user, $accessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $accessibleWalletIds);
        })
            ->where('type', 'debt_receivable')
            ->with(['wallet.user:id,name,username', 'user:id,name,username'])
            ->orderBy('debt_status')
            ->orderBy('due_date')
            ->get();

        $utang = Transaction::where(function ($q) use ($user, $accessibleWalletIds) {
            $q->where('user_id', $user->id)
                ->orWhereIn('wallet_id', $accessibleWalletIds);
        })
            ->where('type', 'debt_payable')
            ->with(['wallet.user:id,name,username', 'user:id,name,username'])
            ->orderBy('debt_status')
            ->orderBy('due_date')
            ->get();

        $totalPiutangAktif = (float) $piutang->where('debt_status', '!=', 'paid')->sum('amount');
        $totalUtangAktif = (float) $utang->where('debt_status', '!=', 'paid')->sum('amount');

        return Inertia::render('Keuangan::Debts/Index', [
            'piutang' => $piutang,
            'utang' => $utang,
            'totalPiutangAktif' => $totalPiutangAktif,
            'totalUtangAktif' => $totalUtangAktif,
            'wallets' => $user->wallets()->get(['id', 'name', 'current_balance']),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Update debt/receivable payment status.
     */
    public function updateStatus(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'debt_status' => ['required', 'in:unpaid,partially_paid,paid'],
        ], [
            'debt_status.required' => 'Status pelunasan wajib dipilih.',
            'debt_status.in' => 'Status pelunasan tidak valid.',
        ]);

        $transaction->update(['debt_status' => $validated['debt_status']]);

        return back()->with('success', 'Status utang/piutang berhasil diperbarui.');
    }
}
