<?php

namespace Modules\Keuangan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;

class WalletController extends Controller
{
    /**
     * Display a listing of user wallets and shared wallets.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 1. Wallets owned by current user
        $wallets = $user->wallets()
            ->withCount('transactions')
            ->with(['shares.user:id,name,username'])
            ->orderBy('id')
            ->get();

        $totalBalance = (float) $wallets->sum('current_balance');

        // 2. Pending invitations received by this user
        $pendingShares = WalletShare::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with(['wallet:id,name,icon,category_label,current_balance', 'sharedBy:id,name,username'])
            ->latest('id')
            ->get();

        // 3. Accepted shared wallets for this user
        $sharedWallets = WalletShare::where('user_id', $user->id)
            ->where('status', 'accepted')
            ->with([
                'wallet' => function ($q) {
                    $q->withCount('transactions')->with('user:id,name,username');
                },
                'sharedBy:id,name,username',
            ])
            ->latest('id')
            ->get();

        return Inertia::render('Keuangan::Wallets/Index', [
            'wallets' => $wallets,
            'totalBalance' => $totalBalance,
            'pendingShares' => $pendingShares,
            'sharedWallets' => $sharedWallets,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Store a newly created wallet.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('keuangan_wallets', 'name')->where('user_id', $user->id),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'category_label' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'initial_balance' => ['required', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Nama dompet wajib diisi.',
            'name.max' => 'Nama dompet maksimal 150 karakter.',
            'name.unique' => 'Nama dompet ini sudah digunakan pada akun Anda.',
            'initial_balance.required' => 'Saldo awal wajib diisi.',
            'initial_balance.numeric' => 'Saldo awal harus berupa angka.',
            'initial_balance.min' => 'Saldo awal tidak boleh kurang dari Rp 0.',
        ]);

        $wallet = $user->wallets()->create([
            'name' => $validated['name'],
            'icon' => $validated['icon'] ?? 'wallet',
            'category_label' => $validated['category_label'] ?? 'KAS MANDIRI',
            'account_number' => $validated['account_number'] ?? null,
            'initial_balance' => $validated['initial_balance'],
            'current_balance' => $validated['initial_balance'],
        ]);

        return back()->with('success', "Dompet '{$wallet->name}' berhasil ditambahkan ke buku besar.");
    }

    /**
     * Display the specified wallet details and its transactions.
     */
    public function show(Request $request, Wallet $wallet): Response
    {
        $user = $request->user();

        $isOwner = $wallet->isOwner($user) || $user->hasRole('superadmin');
        $share = null;

        if (! $isOwner) {
            $share = $wallet->shares()
                ->where('user_id', $user->id)
                ->where('status', 'accepted')
                ->first();

            if (! $share) {
                abort(403, 'Anda tidak memiliki hak akses untuk melihat dompet ini.');
            }
        }

        // Income specifically for this wallet
        $incomeSum = (float) $wallet->transactions()
            ->where('type', 'income')
            ->sum('amount');

        // Transfers received
        $transfersIn = (float) Transaction::where('target_wallet_id', $wallet->id)
            ->where('type', 'transfer')
            ->sum('amount');

        $totalIn = $incomeSum + $transfersIn;

        // Expense specifically from this wallet
        $expenseSum = (float) $wallet->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        // Transfers sent
        $transfersOut = (float) $wallet->transactions()
            ->where('type', 'transfer')
            ->sum('amount');

        $totalOut = $expenseSum + $transfersOut;

        $transactions = Transaction::where(function ($q) use ($wallet) {
            $q->where('wallet_id', $wallet->id)
                ->orWhere('target_wallet_id', $wallet->id);
        })
            ->with(['wallet:id,name,user_id', 'targetWallet:id,name,user_id', 'user:id,name,username'])
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $ownerWallets = $wallet->user ? $wallet->user->wallets()->get(['id', 'name', 'current_balance']) : $user->wallets()->get(['id', 'name', 'current_balance']);

        $wallet->load(['user:id,name,username', 'shares.user:id,name,username']);

        return Inertia::render('Keuangan::Wallets/Show', [
            'wallet' => $wallet,
            'isOwner' => $isOwner,
            'userRole' => $isOwner ? 'owner' : $share->role,
            'stats' => [
                'current_balance' => (float) $wallet->current_balance,
                'initial_balance' => (float) $wallet->initial_balance,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'net_flow' => $totalIn - $totalOut,
                'total_transactions' => $transactions->total(),
            ],
            'transactions' => $transactions,
            'wallets' => $ownerWallets,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'accessible_modules' => $user->accessibleModules(),
            ],
        ]);
    }

    /**
     * Share a wallet with another user by username.
     */
    public function shareWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $user = $request->user();

        if (! $wallet->isOwner($user) && ! $user->hasRole('superadmin')) {
            abort(403, 'Hanya pemilik dompet yang dapat membagikan akses.');
        }

        $validated = $request->validate([
            'username' => ['required', 'string'],
            'role' => ['required', 'in:view,edit'],
        ], [
            'username.required' => 'Username pengguna tujuan wajib diisi.',
            'role.required' => 'Pilih hak akses (Lihat atau Edit).',
            'role.in' => 'Hak akses yang dipilih tidak valid.',
        ]);

        $targetUsername = strtolower(trim($validated['username']));
        $targetUser = User::where('username', $targetUsername)->first();

        if (! $targetUser) {
            return back()->withErrors(['username' => "Pengguna dengan username '{$validated['username']}' tidak ditemukan."]);
        }

        if ($targetUser->id === $user->id) {
            return back()->withErrors(['username' => 'Anda tidak dapat membagikan dompet kepada akun Anda sendiri.']);
        }

        WalletShare::updateOrCreate(
            [
                'wallet_id' => $wallet->id,
                'user_id' => $targetUser->id,
            ],
            [
                'shared_by' => $user->id,
                'role' => $validated['role'],
                'status' => 'pending',
                'is_active' => true,
            ]
        );

        $roleLabel = $validated['role'] === 'edit' ? 'Edit (Pemasukan & Pengeluaran)' : 'Lihat Saja (View)';

        return back()->with('success', "Undangan akses dompet berhasil dikirimkan ke @{$targetUser->username} dengan hak akses {$roleLabel}.");
    }

    /**
     * Revoke a wallet share.
     */
    public function revokeShare(Request $request, Wallet $wallet, WalletShare $share): RedirectResponse
    {
        $user = $request->user();

        if (! $wallet->isOwner($user) && ! $user->hasRole('superadmin')) {
            abort(403, 'Hanya pemilik dompet yang dapat mencabut akses.');
        }

        if ($share->wallet_id !== $wallet->id) {
            abort(404);
        }

        $share->delete();

        return back()->with('success', 'Akses kolaborasi dompet berhasil dicabut.');
    }

    /**
     * Accept a pending wallet share.
     */
    public function acceptShare(Request $request, WalletShare $share): RedirectResponse
    {
        $user = $request->user();

        if ($share->user_id !== $user->id) {
            abort(403);
        }

        $share->update([
            'status' => 'accepted',
            'is_active' => true,
        ]);

        $walletName = $share->wallet ? $share->wallet->name : 'Dompet';

        return back()->with('success', "Anda telah menerima undangan dompet '{$walletName}'. Dompet sekarang aktif di akun Anda.");
    }

    /**
     * Reject a pending wallet share.
     */
    public function rejectShare(Request $request, WalletShare $share): RedirectResponse
    {
        $user = $request->user();

        if ($share->user_id !== $user->id) {
            abort(403);
        }

        $share->delete();

        return back()->with('success', 'Undangan akses dompet telah ditolak.');
    }

    /**
     * Toggle visibility (active/inactive) of an accepted shared wallet.
     */
    public function toggleShare(Request $request, WalletShare $share): RedirectResponse
    {
        $user = $request->user();

        if ($share->user_id !== $user->id) {
            abort(403);
        }

        if ($share->status !== 'accepted') {
            abort(403, 'Dompet belum diterima.');
        }

        $share->update([
            'is_active' => ! $share->is_active,
        ]);

        $statusText = $share->is_active ? 'dinyalakan' : 'dimatikan';

        return back()->with('success', "Dompet berhasil {$statusText}.");
    }

    /**
     * Update the specified wallet.
     */
    public function update(Request $request, Wallet $wallet): RedirectResponse
    {
        $user = $request->user();

        if (! $wallet->isOwner($user) && ! $user->hasRole('superadmin')) {
            abort(403, 'Hanya pemilik dompet yang dapat mengubah informasi dompet.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('keuangan_wallets', 'name')
                    ->where('user_id', $wallet->user_id)
                    ->ignore($wallet->id),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'category_label' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required' => 'Nama dompet wajib diisi.',
            'name.max' => 'Nama dompet maksimal 150 karakter.',
            'name.unique' => 'Nama dompet ini sudah digunakan pada akun Anda.',
        ]);

        $wallet->update($validated);

        return back()->with('success', "Informasi dompet '{$wallet->name}' berhasil diperbarui.");
    }

    /**
     * Remove the specified wallet.
     */
    public function destroy(Request $request, Wallet $wallet): RedirectResponse
    {
        $user = $request->user();

        if (! $wallet->isOwner($user) && ! $user->hasRole('superadmin')) {
            abort(403, 'Hanya pemilik dompet yang dapat menghapus dompet.');
        }

        $wallet->delete();

        return back()->with('success', 'Dompet berhasil dihapus beserta seluruh mutasinya.');
    }
}
