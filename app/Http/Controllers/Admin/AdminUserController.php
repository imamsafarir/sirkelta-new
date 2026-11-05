<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    /**
     * Display a listing of registered users.
     */
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with(['roles', 'referrer:id,name,username'])
            ->withCount(['referees', 'wallets', 'transactions']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $roles = Role::pluck('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $request->search,
                'role' => $request->role,
            ],
        ]);
    }

    /**
     * Update roles for a given user.
     */
    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ], [
            'roles.required' => 'Pilih minimal satu role akses untuk pengguna ini.',
            'roles.min' => 'Pilih minimal satu role akses untuk pengguna ini.',
            'roles.*.exists' => 'Role yang dipilih tidak valid.',
        ]);

        if (
            $user->id === $request->user()->id
            && $request->user()->hasRole('superadmin')
            && ! in_array('superadmin', $validated['roles'], true)
        ) {
            return back()->withErrors([
                'roles' => 'Anda tidak dapat mencabut role superadmin dari akun Anda sendiri.',
            ]);
        }

        $user->syncRoles($validated['roles']);

        return back()->with('success', "Hak akses role untuk pengguna @{$user->username} berhasil diperbarui.");
    }

    /**
     * Delete user from system (cannot delete oneself).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "Pengguna {$name} berhasil dihapus dari sistem.");
    }
}
