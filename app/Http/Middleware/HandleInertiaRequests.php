<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Keuangan\Models\Wallet;
use Modules\Keuangan\Models\WalletShare;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'referral_code' => $request->user()->referral_code,
                    'roles' => $request->user()->getRoleNames(),
                    'accessible_modules' => $request->user()->accessibleModules(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'keuangan_wallets' => function () use ($request) {
                $user = $request->user();
                if (! $user || ! in_array('keuangan', $user->accessibleModules(), true)) {
                    return [];
                }

                $owned = $user->wallets()
                    ->select('id', 'name', 'current_balance', 'user_id')
                    ->orderBy('id')
                    ->get()
                    ->toBase()
                    ->map(fn ($w) => [
                        'id' => $w->id,
                        'name' => $w->name,
                        'current_balance' => (float) $w->current_balance,
                        'is_shared' => false,
                        'role' => 'owner',
                        'owner_username' => $user->username,
                    ]);

                $sharedActiveIds = WalletShare::where('user_id', $user->id)
                    ->where('status', 'accepted')
                    ->where('is_active', true)
                    ->where('role', 'edit')
                    ->pluck('wallet_id');

                $shared = Wallet::whereIn('id', $sharedActiveIds)
                    ->with('user:id,username')
                    ->get(['id', 'name', 'current_balance', 'user_id'])
                    ->toBase()
                    ->map(fn ($w) => [
                        'id' => $w->id,
                        'name' => $w->name,
                        'current_balance' => (float) $w->current_balance,
                        'is_shared' => true,
                        'role' => 'edit',
                        'owner_username' => $w->user?->username ?? 'user',
                    ]);

                return $owned->merge($shared)->values()->all();
            },
        ];
    }
}
