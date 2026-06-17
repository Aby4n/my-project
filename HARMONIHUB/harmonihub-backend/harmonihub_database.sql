-- ============================================================
--  HarmoniHub Database
--  Platform Harmoni Sosial Indonesia
--  "Berbeda keyakinan, bersatu dalam kegiatan"
-- ============================================================
--  Database  : MySQL 8.0+
--  Encoding  : utf8mb4_unicode_ci
--  Generated : 2026-06-07
-- ============================================================

CREATE DATABASE IF NOT EXISTS `harmonihub_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `harmonihub_db`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET TIME_ZONE = '+07:00';

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE `users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_depan`        VARCHAR(100)    NOT NULL,
    `nama_belakang`     VARCHAR(100)    NOT NULL,
    `email`             VARCHAR(191)    NOT NULL,
    `email_verified_at` TIMESTAMP       NULL DEFAULT NULL,
    `password`          VARCHAR(255)    NOT NULL,
    `no_hp`             VARCHAR(20)     NULL,
    `kota`              VARCHAR(100)    NULL,
    `provinsi`          VARCHAR(100)    NULL,
    `usia`              TINYINT UNSIGNED NULL,
    `jenis_kelamin`     ENUM('L','P')   NULL,
    `foto_profil`       VARCHAR(255)    NULL,
    `bio`               TEXT            NULL,
    `role`              ENUM('user','relawan','admin','superadmin') NOT NULL DEFAULT 'user',
    `poin`              INT UNSIGNED    NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `remember_token`    VARCHAR(100)    NULL,
    `last_login_at`     TIMESTAMP       NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_kota` (`kota`),
    KEY `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. PASSWORD RESET TOKENS
-- ============================================================
CREATE TABLE `password_reset_tokens` (
    `email`      VARCHAR(191) NOT NULL,
    `token`      VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. PERSONAL ACCESS TOKENS (Sanctum)
-- ============================================================
CREATE TABLE `personal_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255)    NOT NULL,
    `tokenable_id`   BIGINT UNSIGNED NOT NULL,
    `name`           VARCHAR(255)    NOT NULL,
    `token`          VARCHAR(64)     NOT NULL,
    `abilities`      TEXT            NULL,
    `last_used_at`   TIMESTAMP       NULL,
    `expires_at`     TIMESTAMP       NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `idx_pat_tokenable` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. PROFIL RELAWAN
-- ============================================================
CREATE TABLE `profil_relawan` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`             BIGINT UNSIGNED NOT NULL,
    `keahlian`            VARCHAR(255)    NULL,
    `ketersediaan`        ENUM('akhir_pekan','hari_kerja_sore','fleksibel','full_time') NOT NULL DEFAULT 'fleksibel',
    `motivasi`            TEXT            NULL,
    `status_verifikasi`   ENUM('pending','terverifikasi','ditolak') NOT NULL DEFAULT 'pending',
    `total_jam`           DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
    `total_kegiatan`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `catatan_admin`       TEXT            NULL,
    `verified_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`          TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `profil_relawan_user_id_unique` (`user_id`),
    CONSTRAINT `fk_profil_relawan_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. BIDANG MINAT RELAWAN (many-to-many pivot)
-- ============================================================
CREATE TABLE `bidang_minat` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama`       VARCHAR(100)    NOT NULL,
    `icon`       VARCHAR(10)     NULL,
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    `updated_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `relawan_bidang_minat` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `relawan_id`     BIGINT UNSIGNED NOT NULL,
    `bidang_minat_id` BIGINT UNSIGNED NOT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_relawan_bidang` (`relawan_id`, `bidang_minat_id`),
    CONSTRAINT `fk_rbm_relawan`
        FOREIGN KEY (`relawan_id`) REFERENCES `profil_relawan` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rbm_bidang`
        FOREIGN KEY (`bidang_minat_id`) REFERENCES `bidang_minat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. ORGANISASI
-- ============================================================
CREATE TABLE `organisasi` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama`               VARCHAR(255)    NOT NULL,
    `slug`               VARCHAR(255)    NOT NULL,
    `deskripsi`          TEXT            NULL,
    `bidang_fokus`       ENUM('lingkungan','pendidikan','kesehatan','sosial','budaya','infrastruktur','lainnya') NOT NULL DEFAULT 'sosial',
    `kota`               VARCHAR(100)    NOT NULL,
    `provinsi`           VARCHAR(100)    NOT NULL,
    `email_resmi`        VARCHAR(191)    NULL,
    `website`            VARCHAR(255)    NULL,
    `no_hp`              VARCHAR(20)     NULL,
    `logo`               VARCHAR(255)    NULL,
    `dokumen_legalitas`  VARCHAR(255)    NULL,
    `status`             ENUM('pending','aktif','nonaktif','ditolak') NOT NULL DEFAULT 'pending',
    `is_verified`        TINYINT(1)      NOT NULL DEFAULT 0,
    `total_anggota`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_kegiatan`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `pendiri_user_id`    BIGINT UNSIGNED NOT NULL,
    `verified_at`        TIMESTAMP       NULL DEFAULT NULL,
    `created_at`         TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`         TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`         TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `organisasi_slug_unique` (`slug`),
    KEY `idx_org_status` (`status`),
    KEY `idx_org_bidang` (`bidang_fokus`),
    KEY `idx_org_kota` (`kota`),
    CONSTRAINT `fk_org_pendiri`
        FOREIGN KEY (`pendiri_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. ANGGOTA ORGANISASI
-- ============================================================
CREATE TABLE `anggota_organisasi` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organisasi_id` BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `jabatan`       VARCHAR(100)    NULL DEFAULT 'Anggota',
    `role`          ENUM('anggota','pengurus','admin_org') NOT NULL DEFAULT 'anggota',
    `status`        ENUM('aktif','nonaktif','pending') NOT NULL DEFAULT 'pending',
    `bergabung_at`  TIMESTAMP       NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_anggota_org_user` (`organisasi_id`, `user_id`),
    CONSTRAINT `fk_ao_organisasi`
        FOREIGN KEY (`organisasi_id`) REFERENCES `organisasi` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ao_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. KEGIATAN SOSIAL
-- ============================================================
CREATE TABLE `kegiatan` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organisasi_id`   BIGINT UNSIGNED NULL,
    `pembuat_id`      BIGINT UNSIGNED NOT NULL,
    `judul`           VARCHAR(255)    NOT NULL,
    `slug`            VARCHAR(255)    NOT NULL,
    `deskripsi`       TEXT            NULL,
    `kategori`        ENUM('lingkungan','pendidikan','kesehatan','sosial','budaya','infrastruktur','lainnya') NOT NULL DEFAULT 'sosial',
    `kota`            VARCHAR(100)    NOT NULL,
    `provinsi`        VARCHAR(100)    NOT NULL,
    `lokasi_detail`   VARCHAR(255)    NULL,
    `latitude`        DECIMAL(10,8)   NULL,
    `longitude`       DECIMAL(11,8)   NULL,
    `tanggal_mulai`   DATE            NOT NULL,
    `tanggal_selesai` DATE            NULL,
    `jam_mulai`       TIME            NULL,
    `jam_selesai`     TIME            NULL,
    `kuota`           INT UNSIGNED    NULL,
    `total_peserta`   INT UNSIGNED    NOT NULL DEFAULT 0,
    `status`          ENUM('draft','aktif','berlangsung','selesai','dibatalkan') NOT NULL DEFAULT 'draft',
    `thumbnail`       VARCHAR(255)    NULL,
    `is_online`       TINYINT(1)      NOT NULL DEFAULT 0,
    `link_online`     VARCHAR(255)    NULL,
    `syarat_peserta`  TEXT            NULL,
    `perlengkapan`    TEXT            NULL,
    `total_views`     INT UNSIGNED    NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `kegiatan_slug_unique` (`slug`),
    KEY `idx_kegiatan_status` (`status`),
    KEY `idx_kegiatan_kategori` (`kategori`),
    KEY `idx_kegiatan_kota` (`kota`),
    KEY `idx_kegiatan_tanggal` (`tanggal_mulai`),
    CONSTRAINT `fk_kegiatan_org`
        FOREIGN KEY (`organisasi_id`) REFERENCES `organisasi` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_kegiatan_pembuat`
        FOREIGN KEY (`pembuat_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. PENDAFTARAN KEGIATAN (Peserta)
-- ============================================================
CREATE TABLE `pendaftaran_kegiatan` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kegiatan_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `jumlah_peserta` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `catatan`       TEXT            NULL,
    `status`        ENUM('pending','dikonfirmasi','hadir','tidak_hadir','dibatalkan') NOT NULL DEFAULT 'pending',
    `kode_konfirmasi` VARCHAR(20)   NULL,
    `check_in_at`   TIMESTAMP       NULL DEFAULT NULL,
    `jam_kontribusi` DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
    `created_at`    TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_daftar_kegiatan_user` (`kegiatan_id`, `user_id`),
    KEY `idx_pk_status` (`status`),
    CONSTRAINT `fk_pk_kegiatan`
        FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pk_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. PROGRAM DONASI
-- ============================================================
CREATE TABLE `program_donasi` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organisasi_id`    BIGINT UNSIGNED NULL,
    `pembuat_id`       BIGINT UNSIGNED NOT NULL,
    `judul`            VARCHAR(255)    NOT NULL,
    `slug`             VARCHAR(255)    NOT NULL,
    `deskripsi`        TEXT            NULL,
    `kategori`         ENUM('pendidikan','kesehatan','lingkungan','sosial','bencana','infrastruktur','lainnya') NOT NULL DEFAULT 'sosial',
    `target_dana`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `terkumpul`        DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total_donatur`    INT UNSIGNED    NOT NULL DEFAULT 0,
    `thumbnail`        VARCHAR(255)    NULL,
    `tanggal_mulai`    DATE            NOT NULL,
    `tanggal_selesai`  DATE            NULL,
    `status`           ENUM('draft','aktif','selesai','ditutup') NOT NULL DEFAULT 'draft',
    `penerima_manfaat` INT UNSIGNED    NOT NULL DEFAULT 0,
    `laporan_penggunaan` TEXT          NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `program_donasi_slug_unique` (`slug`),
    KEY `idx_pd_status` (`status`),
    KEY `idx_pd_kategori` (`kategori`),
    CONSTRAINT `fk_pd_organisasi`
        FOREIGN KEY (`organisasi_id`) REFERENCES `organisasi` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pd_pembuat`
        FOREIGN KEY (`pembuat_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. TRANSAKSI DONASI
-- ============================================================
CREATE TABLE `donasi` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_donasi_id` BIGINT UNSIGNED NOT NULL,
    `user_id`           BIGINT UNSIGNED NULL,
    `nama_donatur`      VARCHAR(255)    NULL,
    `email_donatur`     VARCHAR(191)    NULL,
    `jumlah`            DECIMAL(15,2)   NOT NULL,
    `pesan`             TEXT            NULL,
    `is_anonim`         TINYINT(1)      NOT NULL DEFAULT 0,
    `metode_pembayaran` ENUM('transfer_bank','gopay','ovo','dana','qris','kartu_kredit','lainnya') NOT NULL DEFAULT 'transfer_bank',
    `kode_transaksi`    VARCHAR(100)    NOT NULL,
    `status`            ENUM('pending','sukses','gagal','dikembalikan') NOT NULL DEFAULT 'pending',
    `payment_gateway_id` VARCHAR(255)   NULL,
    `payment_proof`     VARCHAR(255)    NULL,
    `paid_at`           TIMESTAMP       NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `donasi_kode_transaksi_unique` (`kode_transaksi`),
    KEY `idx_donasi_status` (`status`),
    KEY `idx_donasi_program` (`program_donasi_id`),
    KEY `idx_donasi_user` (`user_id`),
    CONSTRAINT `fk_donasi_program`
        FOREIGN KEY (`program_donasi_id`) REFERENCES `program_donasi` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_donasi_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. ARTIKEL EDUKASI
-- ============================================================
CREATE TABLE `artikel` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `penulis_id`   BIGINT UNSIGNED NOT NULL,
    `judul`        VARCHAR(255)    NOT NULL,
    `slug`         VARCHAR(255)    NOT NULL,
    `ringkasan`    TEXT            NULL,
    `konten`       LONGTEXT        NOT NULL,
    `thumbnail`    VARCHAR(255)    NULL,
    `kategori`     ENUM('toleransi','sosial','lingkungan','pendidikan','kesehatan','inspirasi','lainnya') NOT NULL DEFAULT 'sosial',
    `estimasi_baca` TINYINT UNSIGNED NULL COMMENT 'dalam menit',
    `total_views`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_likes`  INT UNSIGNED    NOT NULL DEFAULT 0,
    `status`       ENUM('draft','review','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` TIMESTAMP       NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`   TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `artikel_slug_unique` (`slug`),
    KEY `idx_artikel_status` (`status`),
    KEY `idx_artikel_kategori` (`kategori`),
    KEY `idx_artikel_penulis` (`penulis_id`),
    FULLTEXT KEY `ft_artikel_search` (`judul`, `ringkasan`, `konten`),
    CONSTRAINT `fk_artikel_penulis`
        FOREIGN KEY (`penulis_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. SERTIFIKAT DIGITAL
-- ============================================================
CREATE TABLE `sertifikat` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT UNSIGNED NOT NULL,
    `kegiatan_id`   BIGINT UNSIGNED NULL,
    `jenis`         ENUM('partisipasi','relawan_aktif','penyelenggara','donatur','organisasi') NOT NULL DEFAULT 'partisipasi',
    `judul`         VARCHAR(255)    NOT NULL,
    `deskripsi`     TEXT            NULL,
    `kode_sertifikat` VARCHAR(50)   NOT NULL,
    `tanggal_terbit` DATE           NOT NULL,
    `file_path`     VARCHAR(255)    NULL,
    `is_valid`      TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sertifikat_kode_unique` (`kode_sertifikat`),
    KEY `idx_sert_user` (`user_id`),
    CONSTRAINT `fk_sert_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sert_kegiatan`
        FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. INDEKS HARMONI
-- ============================================================
CREATE TABLE `indeks_harmoni` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `periode`              VARCHAR(7)      NOT NULL COMMENT 'Format: YYYY-MM',
    `skor_total`           DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `toleransi_antar_agama` DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
    `kerukunan_antar_suku` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `partisipasi_sosial`   DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `kepercayaan_komunitas` DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
    `kolaborasi_lintas_budaya` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `total_relawan`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_kegiatan`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `total_donasi`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `kota`                 VARCHAR(100)    NULL COMMENT 'NULL = nasional',
    `provinsi`             VARCHAR(100)    NULL,
    `created_at`           TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_harmoni_periode_wilayah` (`periode`, `kota`),
    KEY `idx_ih_periode` (`periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. NOTIFIKASI
-- ============================================================
CREATE TABLE `notifikasi` (
    `id`          CHAR(36)        NOT NULL,
    `type`        VARCHAR(255)    NOT NULL,
    `notifiable_type` VARCHAR(255) NOT NULL,
    `notifiable_id`   BIGINT UNSIGNED NOT NULL,
    `data`        JSON            NOT NULL,
    `read_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notif_notifiable` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. RIWAYAT POIN
-- ============================================================
CREATE TABLE `riwayat_poin` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `poin`        INT             NOT NULL COMMENT 'positif = tambah, negatif = kurang',
    `keterangan`  VARCHAR(255)    NOT NULL,
    `referensi_type` VARCHAR(100) NULL,
    `referensi_id`   BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_rp_user` (`user_id`),
    CONSTRAINT `fk_rp_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. FOTO KEGIATAN (Gallery)
-- ============================================================
CREATE TABLE `foto_kegiatan` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kegiatan_id` BIGINT UNSIGNED NOT NULL,
    `uploader_id` BIGINT UNSIGNED NOT NULL,
    `file_path`   VARCHAR(255)    NOT NULL,
    `caption`     VARCHAR(255)    NULL,
    `urutan`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fk_kegiatan` (`kegiatan_id`),
    CONSTRAINT `fk_foto_kegiatan`
        FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_foto_uploader`
        FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. KOMENTAR ARTIKEL
-- ============================================================
CREATE TABLE `komentar_artikel` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artikel_id`  BIGINT UNSIGNED NOT NULL,
    `user_id`     BIGINT UNSIGNED NOT NULL,
    `parent_id`   BIGINT UNSIGNED NULL,
    `konten`      TEXT            NOT NULL,
    `is_approved` TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ka_artikel` (`artikel_id`),
    CONSTRAINT `fk_ka_artikel`
        FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ka_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ka_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `komentar_artikel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. LIKES ARTIKEL
-- ============================================================
CREATE TABLE `likes_artikel` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `artikel_id` BIGINT UNSIGNED NOT NULL,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_like_artikel_user` (`artikel_id`, `user_id`),
    CONSTRAINT `fk_la_artikel`
        FOREIGN KEY (`artikel_id`) REFERENCES `artikel` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_la_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. ACTIVITY LOG (audit trail)
-- ============================================================
CREATE TABLE `activity_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      BIGINT UNSIGNED NULL,
    `action`       VARCHAR(100)    NOT NULL,
    `model_type`   VARCHAR(100)    NULL,
    `model_id`     BIGINT UNSIGNED NULL,
    `keterangan`   TEXT            NULL,
    `ip_address`   VARCHAR(45)     NULL,
    `user_agent`   VARCHAR(500)    NULL,
    `created_at`   TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_al_user` (`user_id`),
    KEY `idx_al_action` (`action`),
    KEY `idx_al_model` (`model_type`, `model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  SEED DATA
-- ============================================================

-- Bidang Minat
INSERT INTO `bidang_minat` (`nama`, `icon`, `created_at`, `updated_at`) VALUES
('Lingkungan',    '🌱', NOW(), NOW()),
('Pendidikan',    '📚', NOW(), NOW()),
('Kesehatan',     '🩺', NOW(), NOW()),
('Pangan',        '🍽️', NOW(), NOW()),
('Seni Budaya',   '🎨', NOW(), NOW()),
('Infrastruktur', '🏠', NOW(), NOW()),
('Sosial',        '🤝', NOW(), NOW()),
('Teknologi',     '💻', NOW(), NOW());

-- Admin user (password: Admin@12345)
INSERT INTO `users`
    (`nama_depan`,`nama_belakang`,`email`,`password`,`role`,`kota`,`is_active`,`created_at`,`updated_at`)
VALUES
    ('Super','Admin','admin@harmonihub.id',
     '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012',
     'superadmin','Surabaya',1, NOW(), NOW()),
    ('Ahmad','Rizky','ahmad@harmonihub.id',
     '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ013',
     'relawan','Surabaya',1, NOW(), NOW()),
    ('Maria','Wulandari','maria@harmonihub.id',
     '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ014',
     'user','Jakarta',1, NOW(), NOW()),
    ('Budi','Santoso','budi@harmonihub.id',
     '$2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ015',
     'user','Bandung',1, NOW(), NOW());

-- Organisasi
INSERT INTO `organisasi`
    (`nama`,`slug`,`deskripsi`,`bidang_fokus`,`kota`,`provinsi`,`email_resmi`,`status`,`is_verified`,`total_anggota`,`pendiri_user_id`,`created_at`,`updated_at`)
VALUES
    ('Komunitas Hijau Nusantara','komunitas-hijau-nusantara',
     'Organisasi lingkungan yang berdedikasi untuk pelestarian alam Indonesia.',
     'lingkungan','Surabaya','Jawa Timur','khg@email.com','aktif',1,240,2,NOW(),NOW()),
    ('Yayasan Pintar Bersama','yayasan-pintar-bersama',
     'Mendorong pemerataan pendidikan untuk anak-anak kurang mampu.',
     'pendidikan','Jakarta','DKI Jakarta','ypb@email.com','aktif',1,580,3,NOW(),NOW()),
    ('Dokter Peduli Indonesia','dokter-peduli-indonesia',
     'Jaringan dokter sukarela untuk layanan kesehatan gratis pelosok.',
     'kesehatan','Jakarta','DKI Jakarta','dpi@email.com','aktif',1,1200,2,NOW(),NOW());

-- Indeks Harmoni (data historis)
INSERT INTO `indeks_harmoni`
    (`periode`,`skor_total`,`toleransi_antar_agama`,`kerukunan_antar_suku`,`partisipasi_sosial`,`kepercayaan_komunitas`,`kolaborasi_lintas_budaya`,`total_relawan`,`total_kegiatan`,`total_donasi`,`created_at`,`updated_at`)
VALUES
    ('2026-01', 82.10, 88.00, 84.00, 78.00, 75.00, 88.00, 9200,  1420, 1800000000.00, NOW(), NOW()),
    ('2026-02', 83.20, 89.00, 85.00, 79.00, 76.00, 89.00, 9800,  1520, 1950000000.00, NOW(), NOW()),
    ('2026-03', 84.50, 89.50, 86.00, 81.00, 77.00, 90.00, 10400, 1620, 2050000000.00, NOW(), NOW()),
    ('2026-04', 85.30, 90.00, 86.50, 82.00, 78.00, 91.00, 11000, 1700, 2150000000.00, NOW(), NOW()),
    ('2026-05', 86.10, 90.50, 87.00, 83.00, 78.50, 92.00, 11800, 1780, 2300000000.00, NOW(), NOW()),
    ('2026-06', 87.40, 91.00, 88.00, 84.00, 79.00, 93.00, 12480, 1847, 2467000000.00, NOW(), NOW());

-- Program Donasi
INSERT INTO `program_donasi`
    (`organisasi_id`,`pembuat_id`,`judul`,`slug`,`deskripsi`,`kategori`,`target_dana`,`terkumpul`,`total_donatur`,`tanggal_mulai`,`tanggal_selesai`,`status`,`penerima_manfaat`,`created_at`,`updated_at`)
VALUES
    (2,3,'Beasiswa Anak Yatim Piatu','beasiswa-anak-yatim-piatu',
     'Program beasiswa untuk anak yatim piatu berprestasi.',
     'pendidikan',250000000.00,182400000.00,820,'2026-01-01','2026-06-30','aktif',280,NOW(),NOW()),
    (3,2,'Klinik Kesehatan Gratis Pelosok','klinik-kesehatan-gratis-pelosok',
     'Layanan kesehatan gratis untuk masyarakat terpencil.',
     'kesehatan',500000000.00,420000000.00,1580,'2026-02-01','2026-07-31','aktif',1200,NOW(),NOW()),
    (1,2,'Hutan Mangrove Pesisir Jawa','hutan-mangrove-pesisir-jawa',
     'Penanaman 50.000 bibit mangrove di pesisir Jawa.',
     'lingkungan',150000000.00,78500000.00,340,'2026-03-01','2026-09-30','aktif',50000,NOW(),NOW());

-- ============================================================
--  VIEWS BERGUNA
-- ============================================================

CREATE OR REPLACE VIEW `v_statistik_platform` AS
SELECT
    (SELECT COUNT(*) FROM users WHERE role != 'superadmin' AND deleted_at IS NULL) AS total_pengguna,
    (SELECT COUNT(*) FROM profil_relawan WHERE status_verifikasi = 'terverifikasi')  AS total_relawan,
    (SELECT COUNT(*) FROM kegiatan WHERE status != 'dibatalkan' AND deleted_at IS NULL) AS total_kegiatan,
    (SELECT COUNT(*) FROM organisasi WHERE status = 'aktif' AND deleted_at IS NULL)    AS total_organisasi,
    (SELECT COALESCE(SUM(jumlah),0) FROM donasi WHERE status = 'sukses')               AS total_donasi,
    (SELECT COUNT(*) FROM donasi WHERE status = 'sukses')                               AS total_donatur,
    (SELECT COUNT(*) FROM sertifikat WHERE is_valid = 1)                                AS total_sertifikat,
    (SELECT skor_total FROM indeks_harmoni ORDER BY periode DESC LIMIT 1)              AS indeks_harmoni_terkini;

CREATE OR REPLACE VIEW `v_top_relawan` AS
SELECT
    u.id,
    CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_lengkap,
    u.foto_profil,
    u.kota,
    u.poin,
    pr.total_jam,
    pr.total_kegiatan,
    pr.status_verifikasi
FROM users u
JOIN profil_relawan pr ON pr.user_id = u.id
WHERE pr.status_verifikasi = 'terverifikasi'
ORDER BY u.poin DESC;

CREATE OR REPLACE VIEW `v_kegiatan_aktif` AS
SELECT
    k.*,
    CONCAT(u.nama_depan,' ',u.nama_belakang) AS nama_pembuat,
    o.nama AS nama_organisasi,
    CASE
        WHEN k.kuota IS NULL THEN NULL
        ELSE ROUND((k.total_peserta / k.kuota) * 100, 1)
    END AS persen_terisi
FROM kegiatan k
LEFT JOIN users u ON u.id = k.pembuat_id
LEFT JOIN organisasi o ON o.id = k.organisasi_id
WHERE k.status IN ('aktif','berlangsung')
  AND k.deleted_at IS NULL
ORDER BY k.tanggal_mulai ASC;

CREATE OR REPLACE VIEW `v_donasi_summary` AS
SELECT
    pd.id,
    pd.judul,
    pd.kategori,
    pd.target_dana,
    pd.terkumpul,
    pd.total_donatur,
    ROUND((pd.terkumpul / NULLIF(pd.target_dana,0)) * 100, 1) AS persen_tercapai,
    pd.tanggal_selesai,
    DATEDIFF(pd.tanggal_selesai, CURDATE())                   AS sisa_hari,
    o.nama AS nama_organisasi
FROM program_donasi pd
LEFT JOIN organisasi o ON o.id = pd.organisasi_id
WHERE pd.status = 'aktif' AND pd.deleted_at IS NULL
ORDER BY pd.terkumpul DESC;

-- ============================================================
--  STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- Tambah poin relawan otomatis setelah hadir kegiatan
CREATE PROCEDURE `sp_tambah_poin_kehadiran` (
    IN p_user_id       BIGINT UNSIGNED,
    IN p_kegiatan_id   BIGINT UNSIGNED,
    IN p_jam_kontribusi DECIMAL(5,2)
)
BEGIN
    DECLARE v_poin INT DEFAULT 0;
    SET v_poin = FLOOR(p_jam_kontribusi * 10); -- 10 poin per jam

    UPDATE users SET poin = poin + v_poin WHERE id = p_user_id;

    UPDATE profil_relawan
    SET total_jam = total_jam + p_jam_kontribusi,
        total_kegiatan = total_kegiatan + 1
    WHERE user_id = p_user_id;

    INSERT INTO riwayat_poin (user_id, poin, keterangan, referensi_type, referensi_id, created_at)
    VALUES (p_user_id, v_poin,
            CONCAT('Kontribusi ', p_jam_kontribusi, ' jam di kegiatan #', p_kegiatan_id),
            'kegiatan', p_kegiatan_id, NOW());
END$$

-- Update terkumpul donasi setelah transaksi sukses
CREATE PROCEDURE `sp_update_donasi` (
    IN p_donasi_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_jumlah    DECIMAL(15,2);
    DECLARE v_program_id BIGINT UNSIGNED;

    SELECT jumlah, program_donasi_id
    INTO v_jumlah, v_program_id
    FROM donasi WHERE id = p_donasi_id;

    UPDATE program_donasi
    SET terkumpul    = terkumpul + v_jumlah,
        total_donatur = total_donatur + 1
    WHERE id = v_program_id;
END$$

DELIMITER ;

-- ============================================================
--  TRIGGERS
-- ============================================================

DELIMITER $$

-- Update total_peserta saat pendaftaran dikonfirmasi
CREATE TRIGGER `trg_after_daftar_kegiatan_insert`
AFTER INSERT ON `pendaftaran_kegiatan`
FOR EACH ROW
BEGIN
    IF NEW.status = 'dikonfirmasi' THEN
        UPDATE kegiatan
        SET total_peserta = total_peserta + NEW.jumlah_peserta
        WHERE id = NEW.kegiatan_id;
    END IF;
END$$

-- Update total_anggota saat ada anggota baru aktif
CREATE TRIGGER `trg_after_anggota_org_update`
AFTER UPDATE ON `anggota_organisasi`
FOR EACH ROW
BEGIN
    IF NEW.status = 'aktif' AND OLD.status != 'aktif' THEN
        UPDATE organisasi SET total_anggota = total_anggota + 1
        WHERE id = NEW.organisasi_id;
    ELSEIF NEW.status != 'aktif' AND OLD.status = 'aktif' THEN
        UPDATE organisasi SET total_anggota = GREATEST(total_anggota - 1, 0)
        WHERE id = NEW.organisasi_id;
    END IF;
END$$

DELIMITER ;

-- ============================================================
--  INDEXES TAMBAHAN (optimasi query umum)
-- ============================================================
CREATE INDEX `idx_donasi_paid_at`       ON `donasi` (`paid_at`);
CREATE INDEX `idx_kegiatan_composite`   ON `kegiatan` (`status`, `tanggal_mulai`, `kota`);
CREATE INDEX `idx_artikel_published`    ON `artikel` (`status`, `published_at`);
CREATE INDEX `idx_sertifikat_user_jenis` ON `sertifikat` (`user_id`, `jenis`);

-- ============================================================
--  END OF SCRIPT
-- ============================================================
