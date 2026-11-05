<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Modules\WaSender\Models\Contact;
use Modules\WaSender\Models\MessageTemplate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Roles
        $roles = [
            'superadmin',
            'admin_web',
            'admin_keuangan',
            'user_keuangan',
            'admin_wasender',
            'user_wasender',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // 2. Create Default Test Users
        $users = [
            [
                'name' => 'Super Administrator',
                'username' => 'superadmin',
                'email' => 'superadmin@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['superadmin'],
            ],
            [
                'name' => 'Admin Keuangan',
                'username' => 'adminkeuangan',
                'email' => 'adminkeuangan@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['admin_keuangan'],
            ],
            [
                'name' => 'User Keuangan',
                'username' => 'userkeuangan',
                'email' => 'userkeuangan@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['user_keuangan'],
            ],
            [
                'name' => 'Admin WA Sender',
                'username' => 'adminwasender',
                'email' => 'adminwasender@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['admin_wasender'],
            ],
            [
                'name' => 'User WA Sender',
                'username' => 'userwasender',
                'email' => 'userwasender@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['user_wasender'],
            ],
            [
                'name' => 'Sirkelta Multi-App User',
                'username' => 'usercombo',
                'email' => 'usercombo@sirkelta.test',
                'password' => Hash::make('password'),
                'roles' => ['user_keuangan', 'user_wasender'],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'referral_code' => $userData['username'],
                    'password' => $userData['password'],
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles($userData['roles']);
        }

        // 3. Demo Wallets for Keuangan
        $userKeuangan = User::where('email', 'userkeuangan@sirkelta.test')->first();
        if ($userKeuangan) {
            $wTunai = Wallet::firstOrCreate(
                ['user_id' => $userKeuangan->id, 'name' => 'Dompet Kas Tunai'],
                [
                    'category_label' => 'KAS FISIK',
                    'initial_balance' => 15750000,
                    'current_balance' => 15750000,
                    'icon' => 'wallet',
                ]
            );
            $wBca = Wallet::firstOrCreate(
                ['user_id' => $userKeuangan->id, 'name' => 'Rekening Bank BCA'],
                [
                    'category_label' => 'BANK BCA',
                    'initial_balance' => 32500000,
                    'current_balance' => 32500000,
                    'account_number' => '883019284',
                    'icon' => 'credit-card',
                ]
            );

            // 4. Demo Transactions for Keuangan
            if ($userKeuangan->transactions()->count() === 0) {
                Transaction::create([
                    'user_id' => $userKeuangan->id,
                    'wallet_id' => $wBca->id,
                    'type' => 'income',
                    'amount' => 12500000,
                    'category' => 'Pendapatan Jasa',
                    'description' => 'Pembayaran jasa pembuatan aplikasi web',
                    'transaction_date' => now()->subDays(2)->toDateString(),
                ]);

                Transaction::create([
                    'user_id' => $userKeuangan->id,
                    'wallet_id' => $wBca->id,
                    'type' => 'expense',
                    'amount' => 1450000,
                    'category' => 'Infrastruktur Cloud',
                    'description' => 'Langganan server cloud dan database',
                    'transaction_date' => now()->subDay()->toDateString(),
                ]);

                Transaction::create([
                    'user_id' => $userKeuangan->id,
                    'wallet_id' => $wTunai->id,
                    'type' => 'expense',
                    'amount' => 650000,
                    'category' => 'Operasional Kantor',
                    'description' => 'Belanja ATK dan konsumsi tim',
                    'transaction_date' => now()->toDateString(),
                ]);

                Transaction::create([
                    'user_id' => $userKeuangan->id,
                    'wallet_id' => $wTunai->id,
                    'type' => 'debt_receivable',
                    'amount' => 3500000,
                    'party_name' => 'PT Digital Solusindo',
                    'due_date' => now()->addDays(5)->toDateString(),
                    'debt_status' => 'unpaid',
                    'description' => 'Tagihan jasa konsultasi sistem',
                    'transaction_date' => now()->toDateString(),
                ]);

                Transaction::create([
                    'user_id' => $userKeuangan->id,
                    'wallet_id' => $wBca->id,
                    'type' => 'debt_payable',
                    'amount' => 4500000,
                    'party_name' => 'Toko Komputer Mega',
                    'due_date' => now()->addDays(12)->toDateString(),
                    'debt_status' => 'unpaid',
                    'description' => 'Cicilan pengadaan laptop operasional',
                    'transaction_date' => now()->toDateString(),
                ]);
            }
        }

        $userCombo = User::where('email', 'usercombo@sirkelta.test')->first();
        if ($userCombo) {
            Wallet::firstOrCreate(
                ['user_id' => $userCombo->id, 'name' => 'Kas Operasional Usaha'],
                [
                    'category_label' => 'OPERASIONAL',
                    'initial_balance' => 8400000,
                    'current_balance' => 8400000,
                    'icon' => 'wallet',
                ]
            );
            Contact::firstOrCreate(
                ['user_id' => $userCombo->id, 'phone' => '6281234567890'],
                [
                    'name' => 'Budi Pelanggan Setia',
                    'group_name' => 'VIP',
                    'notes' => 'Kontak broadcast toko',
                ]
            );
            MessageTemplate::firstOrCreate(
                ['user_id' => $userCombo->id, 'title' => 'Promo Ramadhan Spesial'],
                [
                    'category' => 'Promosi',
                    'content' => 'Halo Kak {nama}, dapatkan penawaran diskon spesial hari ini!',
                    'has_random_code' => true,
                ]
            );
        }

        $userWa = User::where('email', 'userwasender@sirkelta.test')->first();
        if ($userWa) {
            Contact::firstOrCreate(
                ['user_id' => $userWa->id, 'phone' => '6289876543210'],
                [
                    'name' => 'Siti Reseller',
                    'group_name' => 'Reseller',
                    'notes' => 'Reseller Jabodetabek',
                ]
            );
            MessageTemplate::firstOrCreate(
                ['user_id' => $userWa->id, 'title' => 'Broadcast Update Stok'],
                [
                    'category' => 'Operasional',
                    'content' => 'Hai {nama}, stok barang terbaru sudah masuk. Silakan cek katalog kami.',
                    'has_random_code' => false,
                ]
            );
        }
    }
}
