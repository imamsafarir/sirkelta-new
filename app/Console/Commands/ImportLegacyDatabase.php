<?php

namespace App\Console\Commands;

use App\Models\ReferralLog;
use App\Models\User;
use App\Models\WebsiteFaq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Keuangan\Models\Transaction;
use Modules\Keuangan\Models\Wallet;
use Spatie\Permission\Models\Role;

class ImportLegacyDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-legacy-database {--file=C:\Users\HP\Downloads\sird7969_dompet.sql}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import production data from legacy sird7969_dompet.sql cleanly into sirkelta-new';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file');

        if (! file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");

            return Command::FAILURE;
        }

        $this->info("=== MEMULAI IMPORT DATA DARI {$filePath} ===");

        $sqlContent = file_get_contents($filePath);

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_keuangan', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user_keuangan', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_wasender', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user_wasender', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin_web', 'guard_name' => 'web']);

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // 1. Bersihkan data dummy transaksi & dompet lokal sebelumnya
            $this->info('1. Membersihkan data dummy transaksi dan dompet lama...');
            Transaction::query()->delete();
            Wallet::query()->delete();
            ReferralLog::query()->delete();

            // 2. Data Pengguna (Users) dari SQL Dump
            $this->info('2. Mengimpor akun pengguna (users) asli...');
            $usersData = [
                [
                    'id' => 1,
                    'name' => 'RUPA',
                    'username' => 'superadmin',
                    'email' => 'test@test.com',
                    'email_verified_at' => null,
                    'password' => '$2y$12$6W2gTjcowacw8ohsoOvI4OiomR9aHjoGoAPu3N2WmnxGpXVam.flu',
                    'referred_by' => null,
                    'referral_code' => 'superadmin',
                    'roles' => ['superadmin', 'admin_keuangan', 'admin_web'],
                    'monthly_budget' => null,
                    'remember_token' => 'LifZ86JKiD4T1WVMpGfYZEF5Pj2hZkrX0vAS5GwPStb1T46CTVvmb15ZmTc5',
                    'created_at' => '2026-10-18 22:58:26',
                    'updated_at' => '2026-10-31 06:29:02',
                ],
                [
                    'id' => 2,
                    'name' => 'IMAM SAFARI RAMADHAN',
                    'username' => 'imamsafari_r',
                    'email' => 'imamsafariramadhan@gmail.com',
                    'email_verified_at' => '2026-10-19 00:48:48',
                    'password' => '$2y$12$wQ0uh1C2w1r/QhuR31C1FOoHAcoVkuRmoYqz//4ifssKpPhRPX2.i',
                    'referred_by' => null,
                    'referral_code' => 'imamsafari_r',
                    'roles' => ['user_keuangan'],
                    'monthly_budget' => null,
                    'remember_token' => 'fymFDposIa2X2VY5jPf45gOoZGRxNP6fdoz4en3hJXcDIYutgdIqPjhc6N4p',
                    'created_at' => '2026-10-19 00:48:31',
                    'updated_at' => '2026-10-21 23:27:50',
                ],
                [
                    'id' => 3,
                    'name' => 'Rabiahtul Adawiah',
                    'username' => 'rabeeah',
                    'email' => 'rabiahadwh@gmail.com',
                    'email_verified_at' => '2026-10-19 00:54:19',
                    'password' => '$2y$12$gftPmKf8YSmfKVO2QqOrieW6jo6uf67xCSptwIrZI1dF0x5yxutPO',
                    'referred_by' => null,
                    'referral_code' => 'rabeeah',
                    'roles' => ['user_keuangan'],
                    'monthly_budget' => null,
                    'remember_token' => '1PIRhevhSRWZCjUy1jvK8aUdmwKjKYV2fxeI4L4gorIyEKX8FteURouS3hEu',
                    'created_at' => '2026-10-19 00:53:44',
                    'updated_at' => '2026-10-19 01:08:05',
                ],
                [
                    'id' => 4,
                    'name' => 'SARMILA',
                    'username' => 'milaaa',
                    'email' => 'ssarmila523@gmail.com',
                    'email_verified_at' => '2026-10-19 01:01:27',
                    'password' => '$2y$12$vLuBBiZ744kW9.iU8.OJneJ0hrK.LM.P/RgV/JOOURDI8PaQ8IGlW',
                    'referred_by' => 3,
                    'referral_code' => 'milaaa',
                    'roles' => ['user_keuangan'],
                    'monthly_budget' => null,
                    'remember_token' => 'KKri6ElDQALy93DOovI3bkKKSszeDWeweJxXmvUfk9qKNfEjTsCPYkjZkTyZ',
                    'created_at' => '2026-10-19 01:00:59',
                    'updated_at' => '2026-10-19 01:02:33',
                ],
                [
                    'id' => 5,
                    'name' => 'Rohana Nur Has',
                    'username' => 'hanhana',
                    'email' => 'rohana.nurhas@gmail.com',
                    'email_verified_at' => '2026-10-21 23:01:05',
                    'password' => '$2y$12$i8SmXUHTRWZXweDj2wd4q.o8rlwxRarmVcty9uEfuKlFXXJQW6BdC',
                    'referred_by' => 2,
                    'referral_code' => 'hanhana',
                    'roles' => ['user_keuangan'],
                    'monthly_budget' => null,
                    'remember_token' => 'jjWjcP7UDsPLiki3iMqafBXrXY9VGiivWsvwRCqIIilyQyjM0VsD60yU8jFo',
                    'created_at' => '2026-10-21 22:59:57',
                    'updated_at' => '2026-10-31 16:24:05',
                ],
                [
                    'id' => 6,
                    'name' => 'Abdullah Azhari',
                    'username' => 'abdullahazhari',
                    'email' => 'abdullahanshori07@gmail.com',
                    'email_verified_at' => '2026-10-22 23:47:24',
                    'password' => '$2y$12$8vjkt5AUIzWAVXI4PIo43.3gv76Zjninru/o3xo8c9.Ey.B2Mphii',
                    'referred_by' => null,
                    'referral_code' => 'abdullahazhari',
                    'roles' => ['user_keuangan'],
                    'monthly_budget' => null,
                    'remember_token' => 'UWp1BzFEtwtjtH1SQ3EuOfrKsqtt0LBtCgHahLa9eFlh39E3j8rzJHe1qqO4',
                    'created_at' => '2026-10-22 23:47:10',
                    'updated_at' => '2026-10-24 20:08:27',
                ],
            ];

            // Simpan / update users
            foreach ($usersData as $u) {
                $user = User::updateOrCreate(
                    ['id' => $u['id']],
                    [
                        'name' => $u['name'],
                        'username' => $u['username'],
                        'email' => $u['email'],
                        'email_verified_at' => $u['email_verified_at'],
                        'password' => $u['password'],
                        'referred_by' => $u['referred_by'],
                        'referral_code' => $u['referral_code'],
                        'last_module' => 'keuangan',
                        'monthly_budget' => $u['monthly_budget'],
                        'remember_token' => $u['remember_token'],
                        'created_at' => $u['created_at'],
                        'updated_at' => $u['updated_at'],
                    ]
                );

                $user->syncRoles($u['roles']);
                $this->line("   [User] {$user->name} ({$user->username} / {$user->email}) tersinkron.");
            }

            // Hapus user dummy sisa di atas ID 6 jika bukan akun nyata
            User::where('id', '>', 6)->delete();

            // 3. Dompet (Wallets)
            $this->info('3. Mengimpor Dompet (keuangan_wallets)...');
            $walletsData = [
                [
                    'id' => 1,
                    'user_id' => 1,
                    'name' => 'Dompet Kas Pokok',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'FISIK • PETTY CASH',
                    'initial_balance' => 0.00,
                    'current_balance' => 3000000.00,
                    'balance' => 3000000.00,
                    'color' => 'emerald',
                    'is_active' => true,
                    'created_at' => '2026-10-18 22:58:26',
                    'updated_at' => '2026-10-19 01:22:13',
                ],
                [
                    'id' => 2,
                    'user_id' => 2,
                    'name' => 'Dompet Kas Pokok',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'FISIK • PETTY CASH',
                    'initial_balance' => 0.00,
                    'current_balance' => 5055500.00,
                    'balance' => 5055500.00,
                    'color' => 'emerald',
                    'is_active' => true,
                    'created_at' => '2026-10-19 00:48:31',
                    'updated_at' => '2026-10-21 23:16:48',
                ],
                [
                    'id' => 3,
                    'user_id' => 3,
                    'name' => 'Dompet Kas Pokok',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'FISIK • PETTY CASH',
                    'initial_balance' => 0.00,
                    'current_balance' => -101999.50,
                    'balance' => -101999.50,
                    'color' => 'emerald',
                    'is_active' => true,
                    'created_at' => '2026-10-19 00:53:44',
                    'updated_at' => '2026-10-19 01:08:24',
                ],
                [
                    'id' => 4,
                    'user_id' => 4,
                    'name' => 'Dompet Kas Pokok',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'FISIK • PETTY CASH',
                    'initial_balance' => 0.00,
                    'current_balance' => 0.00,
                    'balance' => 0.00,
                    'color' => 'emerald',
                    'is_active' => true,
                    'created_at' => '2026-10-19 01:00:59',
                    'updated_at' => '2026-10-19 01:00:59',
                ],
                [
                    'id' => 7,
                    'user_id' => 5,
                    'name' => 'Cash Flow',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'Daily Cash',
                    'initial_balance' => 0.00,
                    'current_balance' => 1910000.00,
                    'balance' => 1910000.00,
                    'color' => 'teal',
                    'is_active' => true,
                    'created_at' => '2026-10-21 23:59:13',
                    'updated_at' => '2026-10-31 17:06:52',
                ],
                [
                    'id' => 8,
                    'user_id' => 6,
                    'name' => 'Dompet Kas Pokok',
                    'icon' => 'wallet',
                    'account_number' => null,
                    'category_label' => 'FISIK • PETTY CASH',
                    'initial_balance' => 0.00,
                    'current_balance' => 0.00,
                    'balance' => 0.00,
                    'color' => 'emerald',
                    'is_active' => true,
                    'created_at' => '2026-10-22 23:47:10',
                    'updated_at' => '2026-10-24 20:09:15',
                ],
                [
                    'id' => 9,
                    'user_id' => 6,
                    'name' => 'BCA',
                    'icon' => 'wallet',
                    'account_number' => '12345678',
                    'category_label' => 'BANK',
                    'initial_balance' => 0.00,
                    'current_balance' => 10000.00,
                    'balance' => 10000.00,
                    'color' => 'blue',
                    'is_active' => true,
                    'created_at' => '2026-10-22 23:48:09',
                    'updated_at' => '2026-10-24 20:09:15',
                ],
            ];

            foreach ($walletsData as $w) {
                DB::table('keuangan_wallets')->insert($w);
                $this->line("   [Wallet] #{$w['id']} {$w['name']} (User ID: {$w['user_id']}) - Saldo: Rp ".number_format($w['current_balance'], 0, ',', '.'));
            }

            // 4. Transaksi (Transactions)
            $this->info('4. Mengimpor Transaksi (keuangan_transactions)...');
            $transactionsData = [
                [
                    'id' => 1,
                    'wallet_id' => 3,
                    'target_wallet_id' => null,
                    'user_id' => 3,
                    'type' => 'income',
                    'amount' => 28000.50,
                    'category' => 'Investasi',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-19',
                    'created_at' => '2026-10-19 01:00:09',
                    'updated_at' => '2026-10-19 01:00:09',
                ],
                [
                    'id' => 2,
                    'wallet_id' => 3,
                    'target_wallet_id' => null,
                    'user_id' => 3,
                    'type' => 'debt_receivable',
                    'amount' => 120000.00,
                    'category' => 'Investasi',
                    'party_name' => 'dilan',
                    'due_date' => '2026-10-21',
                    'debt_status' => 'unpaid',
                    'description' => 'beli ayam',
                    'transaction_date' => '2026-10-18',
                    'created_at' => '2026-10-19 01:00:57',
                    'updated_at' => '2026-10-19 01:00:57',
                ],
                [
                    'id' => 3,
                    'wallet_id' => 3,
                    'target_wallet_id' => null,
                    'user_id' => 3,
                    'type' => 'expense',
                    'amount' => 130000.00,
                    'category' => null,
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-19',
                    'created_at' => '2026-10-19 01:08:24',
                    'updated_at' => '2026-10-19 01:08:24',
                ],
                [
                    'id' => 4,
                    'wallet_id' => 1,
                    'target_wallet_id' => null,
                    'user_id' => 1,
                    'type' => 'expense',
                    'amount' => 100000.00,
                    'category' => null,
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-19',
                    'created_at' => '2026-10-19 01:21:25',
                    'updated_at' => '2026-10-19 01:21:25',
                ],
                [
                    'id' => 5,
                    'wallet_id' => 1,
                    'target_wallet_id' => null,
                    'user_id' => 1,
                    'type' => 'income',
                    'amount' => 100000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-19',
                    'created_at' => '2026-10-19 01:21:57',
                    'updated_at' => '2026-10-19 01:21:57',
                ],
                [
                    'id' => 6,
                    'wallet_id' => 1,
                    'target_wallet_id' => null,
                    'user_id' => 1,
                    'type' => 'income',
                    'amount' => 3000000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-19',
                    'created_at' => '2026-10-19 01:22:13',
                    'updated_at' => '2026-10-19 01:22:13',
                ],
                [
                    'id' => 7,
                    'wallet_id' => 2,
                    'target_wallet_id' => null,
                    'user_id' => 2,
                    'type' => 'income',
                    'amount' => 5000000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-22',
                    'created_at' => '2026-10-21 17:06:49',
                    'updated_at' => '2026-10-21 17:06:49',
                ],
                [
                    'id' => 10,
                    'wallet_id' => 2,
                    'target_wallet_id' => null,
                    'user_id' => 2,
                    'type' => 'income',
                    'amount' => 50000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-22',
                    'created_at' => '2026-10-21 23:16:47',
                    'updated_at' => '2026-10-21 23:16:47',
                ],
                [
                    'id' => 11,
                    'wallet_id' => 2,
                    'target_wallet_id' => null,
                    'user_id' => 2,
                    'type' => 'expense',
                    'amount' => 20000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-22',
                    'created_at' => '2026-10-21 23:16:47',
                    'updated_at' => '2026-10-21 23:16:47',
                ],
                [
                    'id' => 12,
                    'wallet_id' => 2,
                    'target_wallet_id' => null,
                    'user_id' => 2,
                    'type' => 'income',
                    'amount' => 25500.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-22',
                    'created_at' => '2026-10-21 23:16:48',
                    'updated_at' => '2026-10-21 23:16:48',
                ],
                [
                    'id' => 21,
                    'wallet_id' => 8,
                    'target_wallet_id' => null,
                    'user_id' => 6,
                    'type' => 'income',
                    'amount' => 10000.00,
                    'category' => 'Usaha',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-23',
                    'created_at' => '2026-10-22 23:47:39',
                    'updated_at' => '2026-10-22 23:47:39',
                ],
                [
                    'id' => 34,
                    'wallet_id' => 8,
                    'target_wallet_id' => 9,
                    'user_id' => 6,
                    'type' => 'transfer',
                    'amount' => 10000.00,
                    'category' => null,
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => null,
                    'transaction_date' => '2026-10-25',
                    'created_at' => '2026-10-24 20:09:15',
                    'updated_at' => '2026-10-24 20:09:15',
                ],
                [
                    'id' => 37,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'income',
                    'amount' => 3300000.00,
                    'category' => 'Gaji',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'Gapok November',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 16:27:26',
                    'updated_at' => '2026-10-31 16:27:26',
                ],
                [
                    'id' => 40,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'expense',
                    'amount' => 192000.00,
                    'category' => 'BPJS',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'BPJS Rehab',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 16:29:42',
                    'updated_at' => '2026-10-31 16:29:42',
                ],
                [
                    'id' => 41,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'expense',
                    'amount' => 940000.00,
                    'category' => 'Tagihan',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'Tagihan Spl 6,8',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 16:30:26',
                    'updated_at' => '2026-10-31 16:30:26',
                ],
                [
                    'id' => 42,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'expense',
                    'amount' => 73000.00,
                    'category' => 'BPJS',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'BPJS Malang',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 16:31:08',
                    'updated_at' => '2026-10-31 16:31:08',
                ],
                [
                    'id' => 43,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'expense',
                    'amount' => 55000.00,
                    'category' => 'Listrik',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'Token Listrik',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 16:43:50',
                    'updated_at' => '2026-10-31 16:43:50',
                ],
                [
                    'id' => 44,
                    'wallet_id' => 7,
                    'target_wallet_id' => null,
                    'user_id' => 5,
                    'type' => 'expense',
                    'amount' => 130000.00,
                    'category' => 'Hiburan',
                    'party_name' => null,
                    'due_date' => null,
                    'debt_status' => null,
                    'description' => 'Smartfren',
                    'transaction_date' => '2026-11-01',
                    'created_at' => '2026-10-31 17:06:52',
                    'updated_at' => '2026-10-31 17:06:52',
                ],
            ];

            foreach ($transactionsData as $t) {
                DB::table('keuangan_transactions')->insert($t);
            }
            $this->line('   [Transactions] '.count($transactionsData).' transaksi berhasil dipindahkan.');

            // 5. Referral Logs
            $this->info('5. Mengimpor Riwayat Referral (referral_logs)...');
            $referralData = [
                [
                    'id' => 1,
                    'referrer_id' => 2, // Imam
                    'referee_id' => 5,  // Rohana
                    'reward_discount' => 10.00,
                    'is_redeemed' => false,
                    'applied_app' => 'keuangan',
                    'created_at' => '2026-10-21 22:59:57',
                    'updated_at' => '2026-10-21 22:59:57',
                ],
                [
                    'id' => 2,
                    'referrer_id' => 3, // Rabiahtul
                    'referee_id' => 4,  // Sarmila
                    'reward_discount' => 10.00,
                    'is_redeemed' => false,
                    'applied_app' => 'keuangan',
                    'created_at' => '2026-10-19 01:00:59',
                    'updated_at' => '2026-10-19 01:00:59',
                ],
            ];

            foreach ($referralData as $r) {
                DB::table('referral_logs')->insert($r);
            }
            $this->line('   [Referral] '.count($referralData).' catatan relasi referral aktif berhasil dipulihkan.');

            // 6. Website FAQ
            $this->info('6. Mengimpor FAQ & Tanya Jawab (website_faqs)...');
            WebsiteFaq::truncate();
            $faqsData = [
                [
                    'id' => 1,
                    'category' => 'Umum',
                    'question' => 'Apakah Dompet Sirkelta benar-benar gratis?',
                    'answer' => 'Ya, 100% gratis tanpa biaya tersembunyi. Kami tidak menjual data pengguna dan tidak memasang iklan.',
                    'order' => 1,
                    'is_active' => true,
                    'created_at' => '2026-10-18 22:44:22',
                    'updated_at' => '2026-10-18 22:44:22',
                ],
                [
                    'id' => 2,
                    'category' => 'Fitur',
                    'question' => 'Bisa dipakai saat offline tanpa sinyal internet?',
                    'answer' => 'Tentu saja! Dompet Sirkelta adalah Progressive Web App (PWA). Anda bisa mencatat transaksi di mana saja meski tanpa koneksi internet, dan data akan disinkronkan saat terhubung kembali.',
                    'order' => 2,
                    'is_active' => true,
                    'created_at' => '2026-10-18 22:44:22',
                    'updated_at' => '2026-10-18 22:44:22',
                ],
                [
                    'id' => 3,
                    'category' => 'Referral',
                    'question' => 'Bagaimana sistem penilaian peringkat referral?',
                    'answer' => 'Cukup bagikan tautan atau kode referral Anda ke teman. Perhitungan peringkat referral direset dan terkunci secara otomatis setiap periode 6 bulan (semesteran).',
                    'order' => 3,
                    'is_active' => true,
                    'created_at' => '2026-10-18 22:44:22',
                    'updated_at' => '2026-10-18 22:44:22',
                ],
                [
                    'id' => 4,
                    'category' => 'Perangkat',
                    'question' => 'Apakah bisa digunakan di Android, iPhone, dan Laptop?',
                    'answer' => 'Bisa! Anda cukup membuka web ini di peramban (Chrome/Safari) lalu tekan tombol "Tambahkan ke Layar Utama" (Install PWA) untuk menggunakannya seperti aplikasi native.',
                    'order' => 4,
                    'is_active' => true,
                    'created_at' => '2026-10-18 22:44:22',
                    'updated_at' => '2026-10-18 22:44:22',
                ],
            ];

            foreach ($faqsData as $f) {
                DB::table('website_faqs')->insert($f);
            }
            $this->line('   [FAQ] '.count($faqsData).' pertanyaan FAQ dipindahkan.');

            // 7. Website Settings (Privacy Policy, Terms of Service, dll)
            $this->info('7. Mengimpor Pengaturan Website & Halaman Hukum (website_settings)...');

            $startPos = strpos($sqlContent, 'INSERT INTO `website_settings`');
            $endPos = strpos($sqlContent, "--\n-- Indeks untuk tabel", $startPos);
            if ($endPos === false) {
                $endPos = strpos($sqlContent, "--\r\n-- Indeks untuk tabel", $startPos);
            }
            if ($endPos === false) {
                $endPos = strpos($sqlContent, 'ALTER TABLE `blog_posts`', $startPos);
            }

            if ($startPos !== false && $endPos !== false) {
                $settingsSql = trim(substr($sqlContent, $startPos, $endPos - $startPos));
                DB::statement('DELETE FROM `website_settings`;');
                DB::statement($settingsSql);
                $this->line('   [WebsiteSettings] Pengaturan website dan kebijakan hukum resmi berhasil dipulihkan.');
            } else {
                $this->warn('   [WebsiteSettings] Blok pengaturan website tidak ditemukan.');
            }

            $this->info('=== PEMINDAHAN DATA BERHASIL DILAKUKAN 100% SECARA BERSIH ===');

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('Gagal melakukan migrasi: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
}
