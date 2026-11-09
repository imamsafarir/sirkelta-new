<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Show the Sirkelta unified application portal.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $isSuperadmin = $user->hasRole('superadmin');

        $hasKeuanganAccess = $isSuperadmin || $user->hasAnyRole(['admin_keuangan', 'user_keuangan']);
        $hasWaSenderAccess = $isSuperadmin || $user->hasAnyRole(['admin_wasender', 'user_wasender']);

        $keuanganRoleLabel = 'Belum Ada Akses';
        if ($isSuperadmin) {
            $keuanganRoleLabel = 'Superadmin';
        } elseif ($user->hasRole('admin_keuangan')) {
            $keuanganRoleLabel = 'Admin Modul';
        } elseif ($user->hasRole('user_keuangan')) {
            $keuanganRoleLabel = 'Pengguna';
        }

        $waSenderRoleLabel = 'Belum Ada Akses';
        if ($isSuperadmin) {
            $waSenderRoleLabel = 'Superadmin';
        } elseif ($user->hasRole('admin_wasender')) {
            $waSenderRoleLabel = 'Admin Modul';
        } elseif ($user->hasRole('user_wasender')) {
            $waSenderRoleLabel = 'Pengguna';
        }

        $apps = [
            [
                'id' => 'keuangan',
                'name' => 'Sirkelta Keuangan',
                'description' => 'Pencatatan kas harian, multi-dompet digital, dan arus kas.',
                'route' => route('keuangan.dashboard'),
                'has_access' => $hasKeuanganAccess,
                'role_label' => $keuanganRoleLabel,
                'color' => 'emerald',
            ],
            [
                'id' => 'wasender',
                'name' => 'Sirkelta WaSender',
                'description' => 'Broadcast pesan WhatsApp, manajemen kontak, dan otomasi.',
                'route' => route('wasender.dashboard'),
                'has_access' => $hasWaSenderAccess,
                'role_label' => $waSenderRoleLabel,
                'color' => 'blue',
            ],
        ];

        return Inertia::render('Portal/AppLauncher', [
            'apps' => $apps,
            'showOnboarding' => (bool) $request->has('onboarding'),
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }
}
