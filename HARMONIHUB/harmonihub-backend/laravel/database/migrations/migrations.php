<?php
// ════════════════════════════════════════════════════════════
//  MIGRATIONS — HarmoniHub
//  Semua migration dalam satu file dokumentasi.
//  Pisahkan ke file masing-masing saat implementasi.
//  Format nama file: YYYY_MM_DD_HHMMSS_nama_migration.php
//
//  Urutan eksekusi (php artisan migrate):
//  1. create_users_table
//  2. create_password_reset_tokens_table
//  3. create_personal_access_tokens_table
//  4. create_bidang_minat_table
//  5. create_organisasi_table
//  6. create_profil_relawan_table
//  7. create_relawan_bidang_minat_table
//  8. create_anggota_organisasi_table
//  9. create_kegiatan_table
//  10. create_pendaftaran_kegiatan_table
//  11. create_program_donasi_table
//  12. create_donasi_table
//  13. create_artikel_table
//  14. create_komentar_artikel_table
//  15. create_likes_artikel_table
//  16. create_sertifikat_table
//  17. create_indeks_harmoni_table
//  18. create_notifikasi_table
//  19. create_riwayat_poin_table
//  20. create_foto_kegiatan_table
//  21. create_activity_log_table
// ════════════════════════════════════════════════════════════

// ─── 1. USERS ─────────────────────────────────────────────
// File: 2026_01_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_depan', 100);
            $table->string('nama_belakang', 100);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('no_hp', 20)->nullable();
            $table->string('kota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->tinyInteger('usia')->unsigned()->nullable();
            $table->enum('jenis_kelamin', ['L','P'])->nullable();
            $table->string('foto_profil')->nullable();
            $table->text('bio')->nullable();
            $table->enum('role', ['user','relawan','admin','superadmin'])->default('user');
            $table->unsignedInteger('poin')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role');
            $table->index('kota');
            $table->index('is_active');
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};

// ─── 2. PASSWORD RESET TOKENS ─────────────────────────────
// File: 2026_01_01_000002_create_password_reset_tokens_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('password_reset_tokens'); }
};

// ─── 3. PERSONAL ACCESS TOKENS ────────────────────────────
// File: 2026_01_01_000003_create_personal_access_tokens_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('personal_access_tokens'); }
};

// ─── 4. BIDANG MINAT ──────────────────────────────────────
// File: 2026_01_01_000004_create_bidang_minat_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bidang_minat', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('icon', 10)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bidang_minat'); }
};

// ─── 5. ORGANISASI ────────────────────────────────────────
// File: 2026_01_01_000005_create_organisasi_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('organisasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->enum('bidang_fokus', ['lingkungan','pendidikan','kesehatan','sosial','budaya','infrastruktur','lainnya'])->default('sosial');
            $table->string('kota', 100);
            $table->string('provinsi', 100);
            $table->string('email_resmi')->nullable();
            $table->string('website')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->string('logo')->nullable();
            $table->string('dokumen_legalitas')->nullable();
            $table->enum('status', ['pending','aktif','nonaktif','ditolak'])->default('pending');
            $table->boolean('is_verified')->default(false);
            $table->unsignedInteger('total_anggota')->default(0);
            $table->unsignedInteger('total_kegiatan')->default(0);
            $table->foreignId('pendiri_user_id')->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('bidang_fokus');
            $table->index('kota');
        });
    }
    public function down(): void { Schema::dropIfExists('organisasi'); }
};

// ─── 6. PROFIL RELAWAN ────────────────────────────────────
// File: 2026_01_01_000006_create_profil_relawan_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('profil_relawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('keahlian')->nullable();
            $table->enum('ketersediaan', ['akhir_pekan','hari_kerja_sore','fleksibel','full_time'])->default('fleksibel');
            $table->text('motivasi')->nullable();
            $table->enum('status_verifikasi', ['pending','terverifikasi','ditolak'])->default('pending');
            $table->decimal('total_jam', 8, 2)->default(0);
            $table->unsignedInteger('total_kegiatan')->default(0);
            $table->text('catatan_admin')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('profil_relawan'); }
};

// ─── 7. RELAWAN BIDANG MINAT ──────────────────────────────
// File: 2026_01_01_000007_create_relawan_bidang_minat_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('relawan_bidang_minat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('relawan_id')->constrained('profil_relawan')->cascadeOnDelete();
            $table->foreignId('bidang_minat_id')->constrained('bidang_minat')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['relawan_id','bidang_minat_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('relawan_bidang_minat'); }
};

// ─── 8. ANGGOTA ORGANISASI ────────────────────────────────
// File: 2026_01_01_000008_create_anggota_organisasi_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('anggota_organisasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->constrained('organisasi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jabatan', 100)->nullable()->default('Anggota');
            $table->enum('role', ['anggota','pengurus','admin_org'])->default('anggota');
            $table->enum('status', ['aktif','nonaktif','pending'])->default('pending');
            $table->timestamp('bergabung_at')->nullable();
            $table->timestamps();
            $table->unique(['organisasi_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('anggota_organisasi'); }
};

// ─── 9. KEGIATAN ──────────────────────────────────────────
// File: 2026_01_01_000009_create_kegiatan_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->nullable()->constrained('organisasi')->nullOnDelete();
            $table->foreignId('pembuat_id')->constrained('users');
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->enum('kategori', ['lingkungan','pendidikan','kesehatan','sosial','budaya','infrastruktur','lainnya'])->default('sosial');
            $table->string('kota', 100);
            $table->string('provinsi', 100);
            $table->string('lokasi_detail')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedInteger('kuota')->nullable();
            $table->unsignedInteger('total_peserta')->default(0);
            $table->enum('status', ['draft','aktif','berlangsung','selesai','dibatalkan'])->default('draft');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('link_online')->nullable();
            $table->text('syarat_peserta')->nullable();
            $table->text('perlengkapan')->nullable();
            $table->unsignedInteger('total_views')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('kategori');
            $table->index('kota');
            $table->index('tanggal_mulai');
        });
    }
    public function down(): void { Schema::dropIfExists('kegiatan'); }
};

// ─── 10. PENDAFTARAN KEGIATAN ─────────────────────────────
// File: 2026_01_01_000010_create_pendaftaran_kegiatan_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pendaftaran_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('jumlah_peserta')->unsigned()->default(1);
            $table->text('catatan')->nullable();
            $table->enum('status', ['pending','dikonfirmasi','hadir','tidak_hadir','dibatalkan'])->default('pending');
            $table->string('kode_konfirmasi', 20)->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->decimal('jam_kontribusi', 5, 2)->default(0);
            $table->timestamps();
            $table->unique(['kegiatan_id','user_id']);
            $table->index('status');
        });
    }
    public function down(): void { Schema::dropIfExists('pendaftaran_kegiatan'); }
};

// ─── 11. PROGRAM DONASI ───────────────────────────────────
// File: 2026_01_01_000011_create_program_donasi_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('program_donasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisasi_id')->nullable()->constrained('organisasi')->nullOnDelete();
            $table->foreignId('pembuat_id')->constrained('users');
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('deskripsi')->nullable();
            $table->enum('kategori', ['pendidikan','kesehatan','lingkungan','sosial','bencana','infrastruktur','lainnya'])->default('sosial');
            $table->decimal('target_dana', 15, 2)->default(0);
            $table->decimal('terkumpul', 15, 2)->default(0);
            $table->unsignedInteger('total_donatur')->default(0);
            $table->string('thumbnail')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->enum('status', ['draft','aktif','selesai','ditutup'])->default('draft');
            $table->unsignedInteger('penerima_manfaat')->default(0);
            $table->text('laporan_penggunaan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('program_donasi'); }
};

// ─── 12. DONASI ───────────────────────────────────────────
// File: 2026_01_01_000012_create_donasi_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('donasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_donasi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nama_donatur')->nullable();
            $table->string('email_donatur')->nullable();
            $table->decimal('jumlah', 15, 2);
            $table->text('pesan')->nullable();
            $table->boolean('is_anonim')->default(false);
            $table->enum('metode_pembayaran', ['transfer_bank','gopay','ovo','dana','qris','kartu_kredit','lainnya'])->default('transfer_bank');
            $table->string('kode_transaksi', 100)->unique();
            $table->enum('status', ['pending','sukses','gagal','dikembalikan'])->default('pending');
            $table->string('payment_gateway_id')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('paid_at');
        });
    }
    public function down(): void { Schema::dropIfExists('donasi'); }
};

// ─── 13. ARTIKEL ──────────────────────────────────────────
// File: 2026_01_01_000013_create_artikel_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penulis_id')->constrained('users');
            $table->string('judul');
            $table->string('slug')->unique();
            $table->text('ringkasan')->nullable();
            $table->longText('konten');
            $table->string('thumbnail')->nullable();
            $table->enum('kategori', ['toleransi','sosial','lingkungan','pendidikan','kesehatan','inspirasi','lainnya'])->default('sosial');
            $table->tinyInteger('estimasi_baca')->unsigned()->nullable();
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('total_likes')->default(0);
            $table->enum('status', ['draft','review','published','archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status','published_at']);
            $table->index('kategori');
            $table->fullText(['judul','ringkasan','konten']);
        });
    }
    public function down(): void { Schema::dropIfExists('artikel'); }
};

// ─── 14. KOMENTAR ARTIKEL ─────────────────────────────────
// File: 2026_01_01_000014_create_komentar_artikel_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('komentar_artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('komentar_artikel')->cascadeOnDelete();
            $table->text('konten');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('komentar_artikel'); }
};

// ─── 15. LIKES ARTIKEL ────────────────────────────────────
// File: 2026_01_01_000015_create_likes_artikel_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('likes_artikel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artikel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['artikel_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('likes_artikel'); }
};

// ─── 16. SERTIFIKAT ───────────────────────────────────────
// File: 2026_01_01_000016_create_sertifikat_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sertifikat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('jenis', ['partisipasi','relawan_aktif','penyelenggara','donatur','organisasi'])->default('partisipasi');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->string('kode_sertifikat', 50)->unique();
            $table->date('tanggal_terbit');
            $table->string('file_path')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamps();

            $table->index(['user_id','jenis']);
        });
    }
    public function down(): void { Schema::dropIfExists('sertifikat'); }
};

// ─── 17. INDEKS HARMONI ───────────────────────────────────
// File: 2026_01_01_000017_create_indeks_harmoni_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('indeks_harmoni', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 7); // YYYY-MM
            $table->decimal('skor_total', 5, 2)->default(0);
            $table->decimal('toleransi_antar_agama', 5, 2)->default(0);
            $table->decimal('kerukunan_antar_suku', 5, 2)->default(0);
            $table->decimal('partisipasi_sosial', 5, 2)->default(0);
            $table->decimal('kepercayaan_komunitas', 5, 2)->default(0);
            $table->decimal('kolaborasi_lintas_budaya', 5, 2)->default(0);
            $table->unsignedInteger('total_relawan')->default(0);
            $table->unsignedInteger('total_kegiatan')->default(0);
            $table->decimal('total_donasi', 15, 2)->default(0);
            $table->string('kota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->timestamps();

            $table->unique(['periode','kota']);
            $table->index('periode');
        });
    }
    public function down(): void { Schema::dropIfExists('indeks_harmoni'); }
};

// ─── 18. NOTIFIKASI ───────────────────────────────────────
// File: 2026_01_01_000018_create_notifikasi_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('notifikasi'); }
};

// ─── 19. RIWAYAT POIN ─────────────────────────────────────
// File: 2026_01_01_000019_create_riwayat_poin_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('riwayat_poin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('poin');
            $table->string('keterangan');
            $table->string('referensi_type', 100)->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('riwayat_poin'); }
};

// ─── 20. FOTO KEGIATAN ────────────────────────────────────
// File: 2026_01_01_000020_create_foto_kegiatan_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('foto_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_id')->constrained('users');
            $table->string('file_path');
            $table->string('caption')->nullable();
            $table->tinyInteger('urutan')->unsigned()->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('foto_kegiatan'); }
};

// ─── 21. ACTIVITY LOG ─────────────────────────────────────
// File: 2026_01_01_000021_create_activity_log_table.php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('action');
            $table->index(['model_type','model_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('activity_log'); }
};
