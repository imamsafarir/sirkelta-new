<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('website_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100)->default('Umum');
            $table->string('question', 255);
            $table->text('answer');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed initial counter for resolved complaints & standard website settings
        DB::table('website_settings')->insertOrIgnore([
            ['key' => 'resolved_complaints_count', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_whatsapp', 'value' => '6281234567890', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'support_email', 'value' => 'support@sirkelta.test', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'referral_discount_amount', 'value' => '25000', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed basic default FAQs
        $faqs = [
            [
                'category' => 'Akun & Akses',
                'question' => 'Bagaimana cara masuk (login) ke aplikasi Sirkelta?',
                'answer' => 'Anda dapat masuk ke Sirkelta menggunakan alamat email terdaftar atau username Anda beserta kata sandi yang valid.',
                'order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Program Referral',
                'question' => 'Bagaimana cara mendapatkan potongan perpanjangan Pro dengan Referral?',
                'answer' => 'Bagikan kode atau tautan referral Anda kepada rekan Anda. Setiap rekan yang mendaftar dan menggunakan kode Anda akan memberikan Anda potongan saldo yang dapat langsung digunakan untuk memotong biaya perpanjangan paket Pro aplikasi Sirkelta.',
                'order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Aplikasi & Fitur',
                'question' => 'Aplikasi apa saja yang tersedia di ekosistem Sirkelta?',
                'answer' => 'Saat ini tersedia Sirkelta Keuangan (pencatatan arus kas, dompet kas, mutasi, radar piutang & hutang) dan Sirkelta WaSender (manajemen perangkat WhatsApp, buku kontak, dan broadcast pesan).',
                'order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Bantuan & Aduan',
                'question' => 'Bagaimana cara menyampaikan kendala atau aduan layanan?',
                'answer' => 'Anda dapat mengklik tombol bubble chat melayang di pojok kanan bawah setiap halaman untuk terhubung langsung dengan tim dukungan pelanggan Sirkelta.',
                'order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('website_faqs')->insert($faqs);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_settings');
        Schema::dropIfExists('website_faqs');
    }
};
