# Sirkelta Platform

Platform ekosistem bisnis terintegrasi yang mencakup modul **Sirkelta Keuangan** (pencatatan transaksi & dompet) dan **Sirkelta WaSender** (otomasi broadcast WhatsApp Web dengan Google Chrome Extension terproteksi).

---

## 🛠️ Tech Stack

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue 3, Inertia.js, Tailwind CSS, Vite
- **Modul**: `nwidart/laravel-modules` (Modules/Keuangan, Modules/WaSender)
- **Otentikasi & Hak Akses**: Spatie Laravel-Permission (Role & Permissions)
- **Chrome Extension**: TypeScript, Vite, Manifest V3, Web Auth Bridge, Extreme Code Obfuscator
- **Testing**: Pest PHP (Backend) & Vitest (Chrome Extension)

---

## 📋 Prasyarat Sistem (Requirements)

Pastikan lingkungan server / komputer Anda telah terpasang:
- **PHP** `>= 8.3` (Sangat disarankan **PHP 8.4**) dengan ekstensi: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`.
- **Composer** `>= 2.x`
- **Node.js** `>= 18.x` atau `>= 20.x` & **npm**
- **Database**: SQLite (default lokal) atau MySQL `>= 8.0` / PostgreSQL `>= 14`
- **Google Chrome** / Brave / Microsoft Edge (untuk menjalankan ekstensi WaSender)

---

## 🚀 Panduan Instalasi Awal (Initial Setup)

Ikuti langkah-langkah di bawah ini untuk menginstal proyek dari awal:

### 1. Clone Repository
```bash
git clone https://github.com/username/sirkelta-new.git
cd sirkelta-new
```

### 2. Install Dependensi PHP (Composer)
```bash
composer install
```

### 3. Salin Konfigurasi Environment (`.env`)
```bash
# Untuk Linux / macOS:
cp .env.example .env

# Untuk Windows (Command Prompt / PowerShell):
copy .env.example .env
```

### 4. Generate Kunci Aplikasi (App Key)
```bash
php artisan key:generate
```

### 5. Setup Database
Secara default, aplikasi menggunakan database **SQLite**. Buat file database jika belum ada:
```bash
# Untuk Linux / macOS:
touch database/database.sqlite

# Untuk Windows PowerShell:
New-Item -ItemType File -Path database/database.sqlite -Force
```
*(Jika ingin menggunakan MySQL/PostgreSQL, ubah pengaturan `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di dalam file `.env`).*

### 6. Jalankan Migrasi & Seeder Database
Perintah ini akan membuat semua tabel modul dan mengisi akun pengujian default:
```bash
php artisan migrate --seed
```

### 7. Buat Symbolic Link Storage
```bash
php artisan storage:link
```

### 8. Install Dependensi & Build Frontend Website
```bash
# Install package frontend
npm install

# Kompilasi aset frontend untuk produksi:
npm run build
```

### 9. Setup & Build Ekstensi Chrome WaSender
Kompilasi kode ekstensi TypeScript dan proteksi kode (obfuscation):
```bash
cd extension
npm install
npm run build
cd ..
```

### 10. Jalankan Server Lokal
```bash
# Opsi 1: Menjalankan dev server lengkap (Laravel + Vite):
composer run dev

# Opsi 2: Menggunakan server bawaan Laravel biasa:
php artisan serve
```
Buka browser dan akses: `http://localhost:8000` (atau domain Herd/Valet Anda, misal `http://sirkelta-new.test`).

---

## 🔑 Akun Pengujian Default (Seeded Accounts)

Semua akun pengujian di bawah ini memiliki kata sandi default: **`password`**

| Peran (Role) | Username | Email | Akses Fitur |
| :--- | :--- | :--- | :--- |
| **Superadmin** | `superadmin` | `superadmin@sirkelta.test` | Akses Penuh Semua Modul & Sistem |
| **Admin Keuangan** | `adminkeuangan` | `adminkeuangan@sirkelta.test` | Kelola Dompet, Kategori & Transaksi Keuangan |
| **User Keuangan** | `userkeuangan` | `userkeuangan@sirkelta.test` | Transaksi Pribadi Modul Keuangan |
| **Admin WaSender** | `adminwasender` | `adminwasender@sirkelta.test` | Kelola Kuota, Kontak & Template WaSender |
| **User WaSender** | `userwasender` | `userwasender@sirkelta.test` | Broadcast WhatsApp & Download Ekstensi |
| **Multi-App User** | `usercombo` | `usercombo@sirkelta.test` | Pengguna Gabungan Keuangan + WaSender |

---

## 🧩 Cara Memasang Ekstensi di Google Chrome (Developer Mode)

1. Buka browser Google Chrome / Brave / Edge.
2. Akses halaman ekstensi dengan mengetik: `chrome://extensions` di bilah alamat (*address bar*).
3. Di pojok kanan atas, aktifkan tombol **Developer mode (Mode pengembang)**.
4. Klik tombol **Load unpacked (Muat yang belum dibongkar)** di pojok kiri atas.
5. Pilih folder:
   - **`c:\path\to\sirkelta-new\extension\dist`** (folder hasil build).
   - *Atau*: Pengguna biasa dapat mengunduh ZIP dari Dashboard Sirkelta (`/wasender/dashboard`), mengekstraknya, lalu memilih folder hasil ekstrak.
6. Buka [web.whatsapp.com](https://web.whatsapp.com).
7. Tombol launcher **WaSender ◀** akan muncul di sisi kanan layar WhatsApp Web dan otomatis terintegrasi dengan akun Sirkelta Anda!

---

## 🔄 Panduan Melakukan Pembaruan (Updating the Application)

Saat Anda atau tim melakukan update kode baru dari Git (misal di server staging / produksi / komputer lain), jalankan rangkaian perintah berikut secara berurutan:

```bash
# 1. Ambil kode terbaru dari repository Git
git pull origin main

# 2. Update dependensi Composer (PHP)
composer install --no-interaction --prefer-dist --optimize-autoloader

# 3. Jalankan migrasi database baru jika ada skema yang berubah
php artisan migrate --force

# 4. Bersihkan dan segarkan cache konfigurasi, route, dan view
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Update dependensi frontend web & build aset baru
npm install
npm run build

# 6. Jika ada pembaruan pada ekstensi Chrome, build ulang ekstensi:
cd extension
npm install
npm run build
cd ..

# 7. (Opsional) Paket ulang file ZIP ekstensi untuk tombol unduh dashboard:
# Windows PowerShell:
Compress-Archive -Path extension/dist/* -DestinationPath public/downloads/wasender-extension.zip -Force
# Linux / macOS:
# (cd extension/dist && zip -r ../../public/downloads/wasender-extension.zip .)

# 8. Restart queue worker jika menggunakan background queue
php artisan queue:restart
```

---

## 🧪 Menjalankan Pengujian (Automated Tests)

Aplikasi ini dilengkapi pengujian otomatis menyeluruh untuk backend maupun ekstensi:

### Test Backend Laravel (Pest PHP):
```bash
# Menjalankan seluruh test suite backend:
php artisan test

# Menjalankan test khusus modul WaSender:
vendor/bin/pest Modules/WaSender
```

### Test Ekstensi Chrome (Vitest):
```bash
cd extension
npm test
```

### Standardisasi & Pemformatan Kode (Laravel Pint):
```bash
vendor/bin/pint
```

---

## 🛡️ Lisensi & Keamanan

Aplikasi ini dikembangkan secara eksklusif untuk platform bisnis **Sirkelta**. Semua hak cipta dilindungi undang-undang.
