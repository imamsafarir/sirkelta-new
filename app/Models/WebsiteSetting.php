<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    public static function getDefault(string $key): string
    {
        return match ($key) {
            'about_us' => '<h3>Visi & Misi Kami</h3><p>Sirkelta lahir dari kebutuhan nyata para pengusaha, UMKM, dan perorangan yang menginginkan sistem pencatatan keuangan dan komunikasi pelanggan yang handal tanpa kerumitan software konvensional. Kami percaya teknologi produktivitas harus cepat, aman, bebas beban biaya tersembunyi, dan mudah diakses dari perangkat manapun.</p><h3>Keunggulan Sirkelta</h3><ul><li><strong>Aman & Terenkripsi:</strong> Seluruh data pengguna terlindungi dengan standar keamanan berlapis dan isolasi multi-tenant.</li><li><strong>Cepat & Responsif:</strong> Antarmuka Single Page Application modern yang instan, ringan, dan bebas lag.</li><li><strong>Ekosistem Kolaboratif:</strong> Dukungan multi-modul terpadu (Sirkelta Keuangan & Sirkelta WaSender) dan program referral berhadiah potongan Pro.</li></ul><h3>Layanan Unggulan Ekosistem</h3><p><strong>Sirkelta Keuangan:</strong> Solusi manajemen dompet kas multi-sumber, pencatatan transaksi masuk dan keluar, monitoring radar jatuh tempo hutang dan piutang, serta visualisasi laba rugi bulanan yang akurat.</p><p><strong>Sirkelta WaSender:</strong> Platform broadcast pesan WhatsApp otomatis, manajemen perangkat multi-koneksi QR, pengelompokan buku kontak pelanggan, dan perpustakaan template pesan siap kirim.</p>',
            'privacy_policy' => '<h3>1. Komitmen Privasi Kami</h3><p>Sirkelta sangat menghargai dan menjunjung tinggi privasi setiap pengguna. Kebijakan Privasi ini menjelaskan bagaimana kami mengumpulkan, menggunakan, menyimpan, dan melindungi informasi pribadi serta data transaksi Anda saat menggunakan layanan Sirkelta Keuangan dan Sirkelta WaSender.</p><h3>2. Informasi yang Kami Kumpulkan</h3><ul><li><strong>Informasi Akun:</strong> Nama lengkap, username, alamat email, dan kata sandi yang telah dienkripsi menggunakan algoritma hashing.</li><li><strong>Data Penggunaan Keuangan:</strong> Nama dompet kas, mutasi transaksi (pemasukan/pengeluaran), catatan piutang dan hutang yang Anda masukkan secara mandiri.</li><li><strong>Data Komunikasi & Kontak:</strong> Nomor WhatsApp yang Anda hubungkan melalui sesi QR code dan daftar buku kontak yang Anda unggah untuk keperluan broadcast.</li><li><strong>Program Referral:</strong> Kode referral unik Anda dan catatan perolehan reward potongan perpanjangan pro.</li></ul><h3>3. Penggunaan Informasi</h3><p>Informasi yang Anda berikan semata-mata digunakan untuk menyediakan layanan operasional aplikasi, otentikasi login, perhitungan potongan referral Pro, dan tindak lanjut bantuan pelanggan.</p><h3>4. Keamanan & Kerahasiaan Data</h3><p>Kami tidak pernah menjual, menyewakan, atau membagikan data keuangan maupun nomor kontak pelanggan Anda kepada pihak ketiga manapun untuk tujuan periklanan.</p>',
            'terms_of_service' => '<h3>1. Penerimaan Ketentuan</h3><p>Dengan mendaftar, mengakses, atau menggunakan platform dan modul aplikasi Sirkelta, Anda menyatakan telah membaca, memahami, dan menyetujui seluruh Syarat & Ketentuan ini secara sadar dan mengikat.</p><h3>2. Akun Pengguna & Keamanan Kredensial</h3><ul><li>Anda bertanggung jawab penuh atas keamanan username, email, dan kata sandi yang Anda gunakan.</li><li>Setiap aktivitas yang terjadi di bawah akun Anda menjadi tanggung jawab Anda sendiri.</li><li>Anda dilarang membuat akun palsu atau mengalihkan akun ke pihak lain tanpa persetujuan resmi dari pihak Sirkelta.</li></ul><h3>3. Program Referral & Potongan Perpanjangan Pro</h3><ul><li>Pengguna yang terdaftar berhak membagikan kode referral kepada teman atau relasi.</li><li>Setiap akun baru yang mendaftar secara sah menggunakan kode referral Anda akan menghasilkan saldo potongan untuk akun perujuk.</li><li>Saldo referral hanya dapat digunakan sebagai potongan biaya perpanjangan langganan Pro aplikasi Sirkelta dan tidak dapat diuangkan atau dicairkan secara tunai.</li></ul><h3>4. Larangan Penggunaan Layanan</h3><p>Pengguna dilarang menggunakan modul broadcast Sirkelta WaSender untuk mengirimkan pesan penipuan, spam massal tanpa persetujuan penerima, konten perjudian, pornografi, atau konten yang melanggar hukum.</p>',
            'refund_policy' => '<h3>1. Ketentuan Umum</h3><p>Sirkelta berkomitmen memberikan kepuasan maksimal atas modul dan layanan perangkat lunak berbasis cloud (SaaS) yang kami tawarkan. Kebijakan ini mengatur syarat pengembalian dana untuk pembelian lisensi berbayar atau paket perpanjangan Pro.</p><h3>2. Garansi Uang Kembali 7 Hari</h3><p>Apabila Anda berlangganan paket Pro aplikasi dan mengalami kendala teknis mendasar yang tidak dapat diselesaikan oleh tim dukungan teknis kami dalam waktu 7 (tujuh) hari kalender sejak pembayaran, Anda berhak mengajukan permohonan pengembalian dana penuh (100%).</p><h3>3. Potongan Referral</h3><p>Bagian pembayaran yang dibayarkan menggunakan potongan saldo referral tidak dapat dikembalikan dalam bentuk uang tunai. Jika permohonan pengembalian dana disetujui, saldo referral yang telah dipakai akan dikembalikan kembali ke akun Sirkelta Anda.</p><h3>4. Prosedur Pengajuan Refund</h3><ul><li>Hubungi tim Customer Care kami melalui bubble chat di kanan bawah atau melalui halaman Hubungi Kami.</li><li>Sertakan bukti pembayaran dan jelaskan secara singkat kendala teknis yang dihadapi.</li><li>Tim redaksi dan keuangan Sirkelta akan memproses verifikasi dalam waktu maksimal 2x24 jam kerja.</li></ul>',
            'contact_address' => 'Indonesia',
            'contact_hours' => 'Senin - Sabtu: 08.00 - 18.00 WIB',
            'contact_description' => 'Punya pertanyaan, kendala, atau penawaran kerja sama? Jangan ragu untuk menghubungi tim dukungan pelanggan kami.',
            'payment_manual_enabled' => '1',
            'payment_bank_name' => 'Bank Central Asia (BCA)',
            'payment_bank_account_number' => '8830192842',
            'payment_bank_account_holder' => 'PT Sirkelta Digital Nusantara',
            'payment_qris_merchant_name' => 'Sirkelta Ecosystem',
            'payment_qris_image_path' => '',
            'payment_midtrans_enabled' => '0',
            'payment_midtrans_mode' => 'sandbox',
            'payment_midtrans_client_key' => '',
            'payment_midtrans_server_key' => '',
            'payment_midtrans_merchant_id' => '',
            default => '',
        };
    }

    public static function getWithDefault(string $key): string
    {
        $val = static::get($key);
        if ($val === null || trim((string) $val) === '') {
            return static::getDefault($key);
        }

        return (string) $val;
    }

    public static function incrementResolvedComplaints(): int
    {
        $setting = static::firstOrCreate(
            ['key' => 'resolved_complaints_count'],
            ['value' => '0']
        );

        $newCount = ((int) $setting->value) + 1;
        $setting->update(['value' => (string) $newCount]);

        return $newCount;
    }

    public static function getResolvedComplaintsCount(): int
    {
        return (int) static::get('resolved_complaints_count', 0);
    }
}
