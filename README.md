# Antrian Disdukcapil

Sistem antrian (queue management) untuk **Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil)** dengan dua jalur layanan — **KTP** dan **KK (Kartu Keluarga)**.

Warga mengambil nomor di **kiosk**, petugas memanggil nomor dari **operator console**, dan **layar display** di ruang tunggu menampilkan papan panggilan lengkap dengan **pengumuman suara** (Bahasa Indonesia). Jumlah loket, nama petugas, serta tampilan (nama aplikasi, logo, warna) dikelola dari **dashboard admin**.

## Fitur

- **Kiosk** (`/kiosk`, publik) — layar sentuh; warga memilih KTP atau KK dan mendapat nomor antrian yang bisa dicetak. Penomoran berurutan per layanan dan reset setiap hari (`A-001` untuk KTP, `B-001` untuk KK).
- **Layar Display / pemanggil** (`/display`, publik) — untuk TV ruang tunggu: panel "sedang memanggil", papan per loket, running text, dan **pengumuman suara** via Web Speech API (`id-ID`) beserta nada bel; mengumumkan ulang saat nomor dipanggil ulang.
- **Operator console** (`/operator`, login) — petugas memilih loketnya lalu **Panggil Berikutnya / Panggil Ulang / Lewati / Selesai**. Antrian menyegar otomatis (polling tiap 3 detik).
- **Dashboard** (`/dashboard`, login) — statistik hari ini (total, menunggu, selesai, loket aktif) dan rincian per layanan.
- **Kelola Loket** (`/admin/lokets`, login) — CRUD loket; tambah loket sebanyak yang dibutuhkan, masing-masing dengan **nama loket + nama petugas**.
- **Kelola Layanan** (`/admin/services`, login) — ubah nama, prefix, dan warna jalur layanan (KTP/KK).
- **Tampilan / Branding** (`/admin/branding`, login) — nama aplikasi, unggah logo, warna primer/aksen, dan running text. Branding diterapkan ke seluruh aplikasi.

## Teknologi

Laravel 13 · Vue 3 · Inertia 3 · Laravel Fortify (auth) · Tailwind CSS v4 · Reka UI · TypeScript · Vite. Penomoran antrian bersifat transaksional dan menggunakan row-lock sehingga aman terhadap pengambilan nomor yang bersamaan. Pembaruan real-time memakai HTTP polling (tanpa infrastruktur websocket) — cocok untuk penempatan kiosk di jaringan lokal.

## Prasyarat

- PHP 8.3+
- Composer 2
- Node.js 20+ dan npm
- MySQL 8 (atau MariaDB) — atau SQLite untuk pengembangan cepat

## Instalasi

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Atur koneksi database di .env (lihat di bawah), buat databasenya,
#    lalu jalankan migrasi + seeder
php artisan migrate --seed

# 4. Symlink storage (agar logo branding bisa diakses publik)
php artisan storage:link

# 5. Jalankan (server + queue + vite secara bersamaan)
composer dev
```

Aplikasi berjalan di `http://localhost:8000` (atau `APP_URL` Anda).

### Konfigurasi database (`.env`)

Contoh untuk MySQL:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=antrian
DB_USERNAME=root
DB_PASSWORD=your_password
```

Buat database terlebih dahulu (`CREATE DATABASE antrian;`) sebelum `php artisan migrate`.
Untuk pengembangan cepat tanpa MySQL, set `DB_CONNECTION=sqlite` dan buat file `database/database.sqlite`.

## Akun admin (hasil seeder)

| Email | Password |
|---|---|
| `admin@disdukcapil.test` | `password` |

Seeder juga membuat layanan **KTP** & **KK**, beberapa loket contoh, dan pengaturan branding default. Ganti password admin setelah login pertama.

## Peta halaman

| Halaman | URL | Akses |
|---|---|---|
| Kiosk (ambil nomor) | `/kiosk` | publik |
| Display / pemanggil (TV) | `/display` | publik |
| Operator console | `/operator` | login |
| Dashboard | `/dashboard` | login |
| Kelola Loket | `/admin/lokets` | login |
| Kelola Layanan | `/admin/services` | login |
| Tampilan / Branding | `/admin/branding` | login |

## Catatan penempatan

- **Suara di layar Display**: browser memblokir audio sampai ada interaksi pengguna — klik tombol **"Aktifkan Suara"** sekali saat layar dibuka. Mesin display juga membutuhkan voice **`id-ID`** terpasang di sistem operasinya agar pengumuman terbaca dalam Bahasa Indonesia.
- **Reset harian** nomor antrian berbasis tanggal (`issued_date`) — otomatis mulai dari 1 setiap hari, tanpa perlu cron.
- **Peran pengguna**: saat ini setiap pengguna terautentikasi dapat mengoperasikan loket sekaligus mengelola admin (belum ada pemisahan peran operator vs admin).

## Pengujian

```bash
php artisan test        # 65 tests
npm run types:check     # vue-tsc
npm run lint:check      # eslint
```
