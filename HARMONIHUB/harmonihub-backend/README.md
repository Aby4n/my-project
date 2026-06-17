# 🌿 HarmoniHub — Backend Laravel

> "Berbeda keyakinan, bersatu dalam kegiatan"

Backend REST API untuk platform HarmoniHub, dibangun dengan **Laravel 11** dan **MySQL 8.0**.

---

## 🗂️ Struktur Proyek

```
harmonihub-backend/
├── harmonihub_database.sql          ← File SQL database lengkap
└── laravel/
    ├── .env.example
    ├── routes/
    │   └── api.php                  ← Semua API routes (v1)
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/Api/
    │   │   │   ├── AuthController.php
    │   │   │   ├── KegiatanController.php
    │   │   │   ├── DonasiController.php
    │   │   │   ├── DashboardController.php   (+ IndeksHarmoni + Sertifikat)
    │   │   │   ├── RelawanController.php
    │   │   │   ├── OrganisasiController.php
    │   │   │   ├── ArtikelController.php
    │   │   │   └── UserController.php
    │   │   ├── Middleware/
    │   │   │   └── RoleMiddleware.php
    │   │   └── Requests/
    │   │       ├── RegisterRequest.php
    │   │       └── LoginRequest.php
    │   ├── Models/
    │   │   ├── User.php
    │   │   ├── ProfilRelawan.php
    │   │   ├── Organisasi.php
    │   │   ├── Kegiatan.php
    │   │   ├── ProgramDonasi.php
    │   │   ├── Donasi.php
    │   │   ├── Artikel.php
    │   │   ├── Sertifikat.php
    │   │   ├── IndeksHarmoni.php
    │   │   └── ... (dan model lainnya)
    │   ├── Services/
    │   │   ├── ActivityLogService.php
    │   │   └── SertifikatService.php
    │   └── Policies/
    │       ├── KegiatanPolicy.php
    │       └── OrganisasiPolicy.php
    └── database/
        ├── migrations/              ← (opsional, sudah ada SQL)
        └── seeders/
```

---

## 🚀 Cara Instalasi

### 1. Prasyarat

| Software | Versi Minimum |
|----------|--------------|
| PHP      | 8.2+         |
| Composer | 2.x          |
| MySQL    | 8.0+         |
| Node.js  | 18+ (opsional, untuk frontend) |

### 2. Clone & Install Dependencies

```bash
# Buat project Laravel baru
composer create-project laravel/laravel harmonihub-backend "11.*"
cd harmonihub-backend

# Install package tambahan
composer require laravel/sanctum
composer require barryvdh/laravel-dompdf     # untuk generate PDF sertifikat
composer require intervention/image          # untuk resize foto
composer require spatie/laravel-activitylog  # (opsional) activity log lebih lengkap
```

### 3. Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai konfigurasi database:

```env
DB_DATABASE=harmonihub_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Import Database SQL

```bash
# Buat database
mysql -u root -p -e "CREATE DATABASE harmonihub_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema + seed data
mysql -u root -p harmonihub_db < harmonihub_database.sql
```

> ⚠️ **Catatan:** File `harmonihub_database.sql` sudah mencakup:
> - DDL (CREATE TABLE) untuk semua 20 tabel
> - Views (v_statistik_platform, v_top_relawan, v_kegiatan_aktif, v_donasi_summary)
> - Stored Procedures (sp_tambah_poin_kehadiran, sp_update_donasi)
> - Triggers (auto update counter)
> - Indexes tambahan untuk optimasi
> - Seed data awal (admin user, organisasi, program donasi, indeks harmoni)

### 5. Setup Sanctum & Storage

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan storage:link
```

### 6. Daftarkan Middleware di `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
    ]);
    $middleware->statefulApi();
})
```

### 7. Jalankan Server

```bash
php artisan serve
# API tersedia di: http://localhost:8000/api/v1
```

---

## 📡 API Endpoints

### Autentikasi

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| POST | `/api/v1/auth/register` | Daftar akun baru | ❌ |
| POST | `/api/v1/auth/login` | Login & dapat token | ❌ |
| POST | `/api/v1/auth/logout` | Logout (hapus token) | ✅ |
| GET  | `/api/v1/auth/me` | Data user login | ✅ |
| POST | `/api/v1/auth/forgot-password` | Kirim email reset | ❌ |
| PUT  | `/api/v1/auth/password` | Ubah password | ✅ |

### Kegiatan Sosial

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/kegiatan` | Daftar kegiatan (filter, paginate) | ❌ |
| GET    | `/api/v1/kegiatan/{slug}` | Detail kegiatan | ❌ |
| POST   | `/api/v1/kegiatan` | Buat kegiatan baru | ✅ |
| PUT    | `/api/v1/kegiatan/{id}` | Update kegiatan | ✅ Owner/Admin |
| DELETE | `/api/v1/kegiatan/{id}` | Hapus kegiatan | ✅ Owner/Admin |
| POST   | `/api/v1/kegiatan/{id}/daftar` | Daftar sebagai peserta | ✅ |
| DELETE | `/api/v1/kegiatan/{id}/batal` | Batal daftar | ✅ |
| GET    | `/api/v1/kegiatan/{id}/peserta` | Daftar peserta | ✅ Owner |
| PUT    | `/api/v1/kegiatan/{id}/peserta/{userId}/status` | Update status peserta + poin | ✅ Owner |

### Donasi

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/donasi/program` | Daftar program donasi | ❌ |
| GET    | `/api/v1/donasi/program/{slug}` | Detail + donatur terbaru | ❌ |
| POST   | `/api/v1/donasi/buat` | Buat transaksi donasi | ✅ (guest ok) |
| GET    | `/api/v1/donasi/riwayat` | Riwayat donasi saya | ✅ |
| POST   | `/api/v1/donasi/callback` | Webhook payment gateway | ❌ |

### Organisasi

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/organisasi` | Daftar organisasi | ❌ |
| GET    | `/api/v1/organisasi/{slug}` | Detail organisasi | ❌ |
| POST   | `/api/v1/organisasi` | Daftar organisasi baru | ✅ |
| POST   | `/api/v1/organisasi/{id}/bergabung` | Bergabung ke org | ✅ |
| GET    | `/api/v1/organisasi/{id}/dashboard` | Dashboard org | ✅ Pengurus |

### Dashboard & Statistik

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/dashboard/statistik` | Statistik publik | ❌ |
| GET    | `/api/v1/dashboard/saya` | Dashboard user | ✅ |
| GET    | `/api/v1/admin/dashboard` | Dashboard admin | ✅ Admin |
| GET    | `/api/v1/admin/dashboard/grafik` | Data grafik | ✅ Admin |

### Indeks Harmoni

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/indeks-harmoni` | Riwayat indeks | ❌ |
| GET    | `/api/v1/indeks-harmoni/terkini` | Data terkini + per kota | ❌ |
| POST   | `/api/v1/admin/indeks-harmoni` | Input data baru | ✅ Admin |

### Sertifikat

| Method | Endpoint | Deskripsi | Auth |
|--------|----------|-----------|------|
| GET    | `/api/v1/sertifikat` | Sertifikatku | ✅ |
| GET    | `/api/v1/sertifikat/verifikasi/{kode}` | Verifikasi publik | ❌ |
| POST   | `/api/v1/sertifikat/{kode}/unduh` | Download PDF | ✅ |
| POST   | `/api/v1/admin/sertifikat/generate` | Generate manual | ✅ Admin |

---

## 🗄️ Skema Database

### Tabel Utama (20 tabel)

```
users                   → Akun pengguna (role: user/relawan/admin/superadmin)
profil_relawan          → Data tambahan relawan terverifikasi
bidang_minat            → Master bidang minat (Lingkungan, Pendidikan, dst)
relawan_bidang_minat    → Pivot relawan ↔ bidang minat
organisasi              → Data organisasi mitra
anggota_organisasi      → Pivot user ↔ organisasi (dengan role)
kegiatan                → Event/kegiatan sosial
pendaftaran_kegiatan    → Pendaftaran peserta ke kegiatan
program_donasi          → Kampanye penggalangan dana
donasi                  → Transaksi donasi individual
artikel                 → Konten edukasi
komentar_artikel        → Komentar bersarang (nested)
likes_artikel           → Like artikel
sertifikat              → Sertifikat digital terverifikasi
indeks_harmoni          → Data indeks harmoni per periode/wilayah
notifikasi              → Notifikasi in-app (polymorphic)
riwayat_poin            → Log penambahan/pengurangan poin
foto_kegiatan           → Gallery foto per kegiatan
activity_log            → Audit trail semua aksi
password_reset_tokens   → Token reset password
personal_access_tokens  → Sanctum API tokens
```

### Views

```sql
v_statistik_platform   → Ringkasan statistik platform
v_top_relawan          → Leaderboard relawan berdasarkan poin
v_kegiatan_aktif       → Kegiatan aktif + % terisi kuota
v_donasi_summary       → Program donasi + % tercapai + sisa hari
```

### Stored Procedures

```sql
sp_tambah_poin_kehadiran(user_id, kegiatan_id, jam_kontribusi)
  → Tambah poin otomatis 10 poin/jam + update profil relawan

sp_update_donasi(donasi_id)
  → Update terkumpul + total_donatur setelah pembayaran sukses
```

---

## 🔐 Sistem Autentikasi

- **Laravel Sanctum** — Token-based API authentication
- **Role System** — `user` | `relawan` | `admin` | `superadmin`
- **Policy** — KegiatanPolicy, OrganisasiPolicy untuk otorisasi per resource
- **Middleware** — `RoleMiddleware` untuk proteksi route per role

### Format Response

```json
{
  "success": true,
  "message": "Kegiatan berhasil dibuat.",
  "data": { ... },
  "meta": {            // hanya untuk paginated response
    "current_page": 1,
    "per_page": 12,
    "total": 48
  }
}
```

### Format Error

```json
{
  "success": false,
  "message": "Data tidak valid.",
  "errors": {
    "email": ["Email ini sudah terdaftar."]
  }
}
```

---

## 📦 Package yang Diperlukan

```json
{
  "require": {
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "laravel/sanctum": "^4.0",
    "barryvdh/laravel-dompdf": "^2.0",
    "intervention/image": "^3.0"
  },
  "require-dev": {
    "fakerphp/faker": "^1.23",
    "laravel/pint": "^1.0",
    "phpunit/phpunit": "^11.0"
  }
}
```

---

## 🔄 Alur Sistem Penting

### Relawan Mendapat Poin
```
Peserta hadir kegiatan
  → Admin update status: "hadir" + jam_kontribusi
  → Controller panggil sp_tambah_poin_kehadiran()
  → Stored Procedure: UPDATE users.poin += jam × 10
  → Stored Procedure: UPDATE profil_relawan (total_jam, total_kegiatan)
  → INSERT riwayat_poin (log detail)
  → SertifikatService::generate() → buat sertifikat otomatis
```

### Donasi Masuk
```
User klik "Bayar" → POST /api/v1/donasi/buat
  → Buat record donasi (status: pending)
  → Redirect ke Payment Gateway (Midtrans/Xendit)
  → Gateway callback → POST /api/v1/donasi/callback
  → Update donasi.status = sukses
  → sp_update_donasi() → UPDATE program_donasi.terkumpul
  → (opsional) Generate sertifikat donatur
```

---

*Dibuat untuk HarmoniHub — Platform Harmoni Sosial Indonesia 🌿*
