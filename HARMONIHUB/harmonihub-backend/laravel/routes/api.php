<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RelawanController;
use App\Http\Controllers\Api\OrganisasiController;
use App\Http\Controllers\Api\KegiatanController;
use App\Http\Controllers\Api\DonasiController;
use App\Http\Controllers\Api\ArtikelController;
use App\Http\Controllers\Api\SertifikatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IndeksHarmoniController;

/*
|--------------------------------------------------------------------------
| API Routes — HarmoniHub
|--------------------------------------------------------------------------
| Prefix  : /api
| Auth    : Laravel Sanctum (token-based)
*/

// ── PUBLIC ROUTES ─────────────────────────────────────────
Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register',       [AuthController::class, 'register']);
        Route::post('login',          [AuthController::class, 'login']);
        Route::post('forgot-password',[AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Kegiatan (public read)
    Route::prefix('kegiatan')->group(function () {
        Route::get('/',          [KegiatanController::class, 'index']);
        Route::get('/{slug}',    [KegiatanController::class, 'show']);
    });

    // Artikel (public read)
    Route::prefix('artikel')->group(function () {
        Route::get('/',          [ArtikelController::class, 'index']);
        Route::get('/{slug}',    [ArtikelController::class, 'show']);
    });

    // Organisasi (public read)
    Route::prefix('organisasi')->group(function () {
        Route::get('/',          [OrganisasiController::class, 'index']);
        Route::get('/{slug}',    [OrganisasiController::class, 'show']);
    });

    // Donasi (public read)
    Route::prefix('donasi')->group(function () {
        Route::get('/program',        [DonasiController::class, 'indexProgram']);
        Route::get('/program/{slug}', [DonasiController::class, 'showProgram']);
        Route::post('/callback',      [DonasiController::class, 'paymentCallback']); // webhook
    });

    // Indeks Harmoni (public)
    Route::get('indeks-harmoni',         [IndeksHarmoniController::class, 'index']);
    Route::get('indeks-harmoni/terkini', [IndeksHarmoniController::class, 'terkini']);

    // Dashboard statistik (public summary)
    Route::get('dashboard/statistik',   [DashboardController::class, 'statistikPublik']);

    // Verifikasi sertifikat (public)
    Route::get('sertifikat/verifikasi/{kode}', [SertifikatController::class, 'verifikasi']);

    // ── PROTECTED ROUTES ──────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::get('auth/me',       [AuthController::class, 'me']);
        Route::put('auth/password', [AuthController::class, 'ubahPassword']);

        // User / Profil
        Route::prefix('user')->group(function () {
            Route::get('profil',            [UserController::class, 'profil']);
            Route::put('profil',            [UserController::class, 'updateProfil']);
            Route::post('foto',             [UserController::class, 'uploadFoto']);
            Route::get('riwayat-poin',      [UserController::class, 'riwayatPoin']);
            Route::get('notifikasi',        [UserController::class, 'notifikasi']);
            Route::put('notifikasi/baca',   [UserController::class, 'bacaNotifikasi']);
        });

        // Relawan
        Route::prefix('relawan')->group(function () {
            Route::post('daftar',           [RelawanController::class, 'daftar']);
            Route::get('profil',            [RelawanController::class, 'profilSaya']);
            Route::put('profil',            [RelawanController::class, 'updateProfil']);
            Route::get('kegiatan',          [RelawanController::class, 'kegiatanSaya']);
            Route::get('sertifikat',        [RelawanController::class, 'sertifikatSaya']);
            Route::get('leaderboard',       [RelawanController::class, 'leaderboard']);
        });

        // Kegiatan (aksi)
        Route::prefix('kegiatan')->group(function () {
            Route::post('/',                [KegiatanController::class, 'store']);
            Route::put('/{id}',             [KegiatanController::class, 'update']);
            Route::delete('/{id}',          [KegiatanController::class, 'destroy']);
            Route::post('/{id}/daftar',     [KegiatanController::class, 'daftar']);
            Route::delete('/{id}/batal',    [KegiatanController::class, 'batalDaftar']);
            Route::get('/{id}/peserta',     [KegiatanController::class, 'daftarPeserta']);
            Route::put('/{id}/peserta/{userId}/status', [KegiatanController::class, 'updateStatusPeserta']);
            Route::post('/{id}/foto',       [KegiatanController::class, 'uploadFoto']);
        });

        // Organisasi (aksi)
        Route::prefix('organisasi')->group(function () {
            Route::post('/',                [OrganisasiController::class, 'store']);
            Route::put('/{id}',             [OrganisasiController::class, 'update']);
            Route::delete('/{id}',          [OrganisasiController::class, 'destroy']);
            Route::post('/{id}/bergabung',  [OrganisasiController::class, 'bergabung']);
            Route::delete('/{id}/keluar',   [OrganisasiController::class, 'keluar']);
            Route::get('/{id}/anggota',     [OrganisasiController::class, 'daftarAnggota']);
            Route::put('/{id}/anggota/{userId}', [OrganisasiController::class, 'updateAnggota']);
            Route::get('/{id}/dashboard',   [OrganisasiController::class, 'dashboard']);
        });

        // Donasi
        Route::prefix('donasi')->group(function () {
            Route::post('/buat',            [DonasiController::class, 'buat']);
            Route::get('/riwayat',          [DonasiController::class, 'riwayat']);
            Route::get('/{kode}',           [DonasiController::class, 'detail']);
        });

        // Artikel (aksi)
        Route::prefix('artikel')->group(function () {
            Route::post('/',                [ArtikelController::class, 'store']);
            Route::put('/{id}',             [ArtikelController::class, 'update']);
            Route::delete('/{id}',          [ArtikelController::class, 'destroy']);
            Route::post('/{id}/like',       [ArtikelController::class, 'toggleLike']);
            Route::post('/{id}/komentar',   [ArtikelController::class, 'komentar']);
            Route::delete('/komentar/{id}', [ArtikelController::class, 'hapusKomentar']);
        });

        // Sertifikat
        Route::prefix('sertifikat')->group(function () {
            Route::get('/',                 [SertifikatController::class, 'index']);
            Route::get('/{kode}',           [SertifikatController::class, 'show']);
            Route::post('/{kode}/unduh',    [SertifikatController::class, 'unduh']);
        });

        // Dashboard (user)
        Route::get('dashboard/saya',        [DashboardController::class, 'dashboardUser']);

        // ── ADMIN ROUTES ───────────────────────────────────
        Route::middleware('role:admin,superadmin')->prefix('admin')->group(function () {

            // User management
            Route::get('users',                         [UserController::class, 'index']);
            Route::get('users/{id}',                    [UserController::class, 'show']);
            Route::put('users/{id}/role',               [UserController::class, 'ubahRole']);
            Route::put('users/{id}/status',             [UserController::class, 'ubahStatus']);

            // Verifikasi relawan
            Route::get('relawan/pending',               [RelawanController::class, 'pending']);
            Route::put('relawan/{id}/verifikasi',       [RelawanController::class, 'verifikasi']);

            // Verifikasi organisasi
            Route::get('organisasi/pending',            [OrganisasiController::class, 'pending']);
            Route::put('organisasi/{id}/verifikasi',    [OrganisasiController::class, 'verifikasi']);

            // Moderasi artikel
            Route::get('artikel/review',                [ArtikelController::class, 'indexReview']);
            Route::put('artikel/{id}/publish',          [ArtikelController::class, 'publish']);

            // Indeks Harmoni
            Route::post('indeks-harmoni',               [IndeksHarmoniController::class, 'store']);
            Route::put('indeks-harmoni/{id}',           [IndeksHarmoniController::class, 'update']);

            // Dashboard admin
            Route::get('dashboard',                     [DashboardController::class, 'dashboardAdmin']);
            Route::get('dashboard/grafik',              [DashboardController::class, 'grafikAdmin']);

            // Sertifikat (generate)
            Route::post('sertifikat/generate',          [SertifikatController::class, 'generate']);
            Route::put('sertifikat/{id}/revoke',        [SertifikatController::class, 'revoke']);
        });
    });
});
