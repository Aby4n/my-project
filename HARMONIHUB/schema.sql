-- ═══════════════════════════════════════════════════════════════
--  HarmoniHub — Database Schema (SQLite)
--  Platform harmoni sosial Indonesia
-- ═══════════════════════════════════════════════════════════════

PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

-- ─────────────────────────────────────────
--  1. USERS  (akun pengguna)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_depan    TEXT    NOT NULL,
    nama_belakang TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    avatar_initials TEXT  GENERATED ALWAYS AS (
                    UPPER(SUBSTR(nama_depan,1,1) || SUBSTR(nama_belakang,1,1))
                  ) VIRTUAL,
    kota          TEXT,
    usia          INTEGER,
    keahlian      TEXT,
    motivasi      TEXT,
    ketersediaan  TEXT    CHECK(ketersediaan IN (
                    'Akhir pekan saja','Hari kerja sore','Fleksibel','Full-time'
                  )),
    poin          INTEGER NOT NULL DEFAULT 0,
    jam_kontribusi REAL   NOT NULL DEFAULT 0,
    is_relawan    INTEGER NOT NULL DEFAULT 0, -- boolean
    is_active     INTEGER NOT NULL DEFAULT 1,
    oauth_provider TEXT,   -- 'google' | NULL
    oauth_id       TEXT,
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  2. BIDANG MINAT RELAWAN  (many-to-many)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS relawan_minat (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    minat   TEXT    NOT NULL CHECK(minat IN (
              'Lingkungan','Pendidikan','Kesehatan',
              'Pangan','Seni Budaya','Infrastruktur','Sosial','Budaya'
            )),
    UNIQUE(user_id, minat)
);

-- ─────────────────────────────────────────
--  3. ORGANISASI  (komunitas / lembaga)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS organisasi (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    nama          TEXT    NOT NULL,
    deskripsi     TEXT,
    kategori      TEXT    NOT NULL CHECK(kategori IN (
                    'Keagamaan','Lingkungan','Pendidikan',
                    'Kesehatan','Sosial','Budaya','Lainnya'
                  )),
    kota          TEXT    NOT NULL,
    logo_emoji    TEXT    NOT NULL DEFAULT '🏛️',
    website       TEXT,
    email_kontak  TEXT,
    status        TEXT    NOT NULL DEFAULT 'pending' CHECK(status IN (
                    'pending','aktif','ditangguhkan'
                  )),
    verified      INTEGER NOT NULL DEFAULT 0,
    anggota_count INTEGER NOT NULL DEFAULT 0,
    founder_id    INTEGER REFERENCES users(id),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  4. ANGGOTA ORGANISASI
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS org_anggota (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    org_id       INTEGER NOT NULL REFERENCES organisasi(id) ON DELETE CASCADE,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    peran        TEXT    NOT NULL DEFAULT 'anggota' CHECK(peran IN (
                   'founder','admin','anggota'
                 )),
    joined_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(org_id, user_id)
);

-- ─────────────────────────────────────────
--  5. KEGIATAN  (social activities)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kegiatan (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    judul           TEXT    NOT NULL,
    deskripsi       TEXT    NOT NULL,
    kategori        TEXT    NOT NULL CHECK(kategori IN (
                      'Lingkungan','Pendidikan','Kesehatan',
                      'Sosial','Budaya','Infrastruktur','Lainnya'
                    )),
    kota            TEXT    NOT NULL,
    lokasi_detail   TEXT,
    emoji           TEXT    NOT NULL DEFAULT '🌿',
    tanggal_mulai   TEXT    NOT NULL,  -- ISO 8601
    tanggal_selesai TEXT,
    waktu_mulai     TEXT,
    kuota_max       INTEGER,
    peserta_count   INTEGER NOT NULL DEFAULT 0,
    status          TEXT    NOT NULL DEFAULT 'upcoming' CHECK(status IN (
                      'upcoming','ongoing','done','cancelled'
                    )),
    org_id          INTEGER REFERENCES organisasi(id),
    creator_id      INTEGER NOT NULL REFERENCES users(id),
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  6. PENDAFTARAN KEGIATAN
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS kegiatan_peserta (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    kegiatan_id INTEGER NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status      TEXT    NOT NULL DEFAULT 'terdaftar' CHECK(status IN (
                  'terdaftar','hadir','tidak_hadir'
                )),
    registered_at TEXT  NOT NULL DEFAULT (datetime('now')),
    UNIQUE(kegiatan_id, user_id)
);

-- ─────────────────────────────────────────
--  7. PROGRAM DONASI
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS donasi_program (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    judul           TEXT    NOT NULL,
    deskripsi       TEXT    NOT NULL,
    kategori        TEXT    NOT NULL CHECK(kategori IN (
                      'Pendidikan','Kesehatan','Lingkungan',
                      'Sosial','Infrastruktur','Budaya','Lainnya'
                    )),
    target_amount   REAL    NOT NULL,
    collected_amount REAL   NOT NULL DEFAULT 0,
    donatur_count   INTEGER NOT NULL DEFAULT 0,
    penerima_manfaat INTEGER,
    deadline        TEXT,   -- ISO 8601 date
    org_id          INTEGER REFERENCES organisasi(id),
    creator_id      INTEGER NOT NULL REFERENCES users(id),
    is_active       INTEGER NOT NULL DEFAULT 1,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  8. TRANSAKSI DONASI
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS donasi_transaksi (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    program_id      INTEGER NOT NULL REFERENCES donasi_program(id),
    user_id         INTEGER REFERENCES users(id),    -- NULL jika anonim
    jumlah          REAL    NOT NULL CHECK(jumlah > 0),
    metode_bayar    TEXT    NOT NULL CHECK(metode_bayar IN (
                      'transfer_bank','gopay','ovo','dana',
                      'qris','kartu_kredit','lainnya'
                    )),
    is_anonim       INTEGER NOT NULL DEFAULT 0,
    nama_donatur    TEXT,   -- diisi jika tidak anonim & tamu
    status_bayar    TEXT    NOT NULL DEFAULT 'pending' CHECK(status_bayar IN (
                      'pending','sukses','gagal','refund'
                    )),
    kode_transaksi  TEXT    UNIQUE,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  9. ARTIKEL
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS artikel (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    judul           TEXT    NOT NULL,
    konten          TEXT    NOT NULL,
    ringkasan       TEXT,
    kategori        TEXT    NOT NULL CHECK(kategori IN (
                      'Toleransi','Sosial','Lingkungan','Pendidikan','Lainnya'
                    )),
    emoji_thumb     TEXT    NOT NULL DEFAULT '📰',
    menit_baca      INTEGER NOT NULL DEFAULT 5,
    author_id       INTEGER NOT NULL REFERENCES users(id),
    is_featured     INTEGER NOT NULL DEFAULT 0,
    is_published    INTEGER NOT NULL DEFAULT 0,
    views           INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
    published_at    TEXT
);

-- ─────────────────────────────────────────
--  10. SERTIFIKAT DIGITAL
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sertifikat (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    kegiatan_id     INTEGER REFERENCES kegiatan(id),
    tipe            TEXT    NOT NULL CHECK(tipe IN (
                      'kegiatan','relawan_aktif','donatur','organisasi'
                    )),
    kode_verifikasi TEXT    NOT NULL UNIQUE,
    judul_sertifikat TEXT   NOT NULL,
    deskripsi       TEXT,
    issued_at       TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  11. INDEKS HARMONI  (log harian/bulanan)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS harmoni_index (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    periode         TEXT    NOT NULL UNIQUE,  -- 'YYYY-MM'
    skor_keseluruhan REAL   NOT NULL,
    kerukunan_agama REAL,
    partisipasi_sosial REAL,
    toleransi_budaya REAL,
    gotong_royong   REAL,
    harmoni_digital REAL,
    catatan         TEXT,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ─────────────────────────────────────────
--  12. AKTIVITAS LOG  (real-time feed)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS aktivitas_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    tipe        TEXT    NOT NULL CHECK(tipe IN (
                  'join_kegiatan','donasi','sertifikat',
                  'daftar_org','daftar_relawan','artikel_publish'
                )),
    deskripsi   TEXT    NOT NULL,
    user_id     INTEGER REFERENCES users(id),
    ref_id      INTEGER,   -- id kegiatan / program / dll
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ═══════════════════════════════
--  INDEXES
-- ═══════════════════════════════
CREATE INDEX IF NOT EXISTS idx_users_email         ON users(email);
CREATE INDEX IF NOT EXISTS idx_kegiatan_status     ON kegiatan(status);
CREATE INDEX IF NOT EXISTS idx_kegiatan_kota       ON kegiatan(kota);
CREATE INDEX IF NOT EXISTS idx_kegiatan_kategori   ON kegiatan(kategori);
CREATE INDEX IF NOT EXISTS idx_donasi_program_id   ON donasi_transaksi(program_id);
CREATE INDEX IF NOT EXISTS idx_sertifikat_user     ON sertifikat(user_id);
CREATE INDEX IF NOT EXISTS idx_aktivitas_created   ON aktivitas_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_artikel_kategori    ON artikel(kategori);
CREATE INDEX IF NOT EXISTS idx_artikel_featured    ON artikel(is_featured);

-- ═══════════════════════════════
--  SEED DATA
-- ═══════════════════════════════

-- Demo admin user (password: harmonihub2026)
INSERT OR IGNORE INTO users (
    nama_depan, nama_belakang, email, password_hash,
    kota, is_relawan, poin, jam_kontribusi
) VALUES (
    'Ahmad', 'Rizky',
    'ahmad@harmonihub.id',
    '$2b$12$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', -- 'password'
    'Surabaya', 1, 1240, 24.5
);

INSERT OR IGNORE INTO users (
    nama_depan, nama_belakang, email, password_hash,
    kota, is_relawan, poin, jam_kontribusi
) VALUES (
    'Sari', 'Rahayu',
    'sari@harmonihub.id',
    '$2b$12$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW',
    'Surabaya', 1, 980, 24
);

INSERT OR IGNORE INTO users (
    nama_depan, nama_belakang, email, password_hash,
    kota, is_relawan, poin, jam_kontribusi
) VALUES (
    'Dimas', 'Haryanto',
    'dimas@harmonihub.id',
    '$2b$12$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW',
    'Jakarta', 1, 742, 18
);

-- Organisasi seed
INSERT OR IGNORE INTO organisasi (nama, deskripsi, kategori, kota, logo_emoji, verified, status, founder_id) VALUES
('Komunitas Hijau Nusantara', 'Organisasi lingkungan lintas agama untuk menjaga bumi Indonesia', 'Lingkungan', 'Surabaya', '🌿', 1, 'aktif', 1),
('Yayasan Pintar Bersama', 'Beasiswa dan bimbingan belajar untuk anak-anak kurang mampu', 'Pendidikan', 'Jakarta', '📚', 1, 'aktif', 2),
('Dokter Peduli Indonesia', 'Layanan kesehatan gratis untuk masyarakat pelosok', 'Kesehatan', 'Bandung', '🩺', 1, 'aktif', 3),
('Forum Dialog Antariman', 'Membangun dialog dan toleransi antarkeyakinan', 'Keagamaan', 'Yogyakarta', '🕊️', 1, 'aktif', 1);

-- Kegiatan seed
INSERT OR IGNORE INTO kegiatan (judul, deskripsi, kategori, kota, emoji, tanggal_mulai, kuota_max, peserta_count, status, creator_id, org_id) VALUES
('Tanam 1000 Pohon Bersama', 'Kegiatan penghijauan lintas komunitas di Taman Kota Surabaya. Terbuka untuk semua.', 'Lingkungan', 'Surabaya', '🌱', '2026-06-15', 80, 47, 'upcoming', 1, 1),
('Bakti Sosial Kesehatan Gratis', 'Pemeriksaan kesehatan gratis meliputi tensi, gula darah, dan konsultasi dokter umum.', 'Kesehatan', 'Jakarta', '🩺', '2026-06-22', 200, 120, 'upcoming', 3, 3),
('Bimbel Gratis Anak Yatim', 'Program bimbingan belajar gratis untuk anak-anak yatim usia SD-SMP di wilayah Bandung.', 'Pendidikan', 'Bandung', '📖', '2026-06-20', 50, 30, 'upcoming', 2, 2),
('Berbagi Makanan untuk Dhuafa', 'Program berbagi makanan bergizi setiap minggu untuk keluarga kurang mampu.', 'Sosial', 'Jakarta', '🍱', '2026-06-08', 30, 15, 'upcoming', 2, 2),
('Festival Seni Lintas Budaya', 'Pameran seni dan pertunjukan budaya yang merayakan keberagaman Indonesia.', 'Budaya', 'Yogyakarta', '🎨', '2026-07-01', 500, 200, 'upcoming', 1, 4),
('Renovasi Rumah Warga Miskin', 'Gotong royong renovasi rumah tidak layak huni bagi keluarga berpenghasilan rendah.', 'Infrastruktur', 'Semarang', '🏠', '2026-07-05', 20, 8, 'upcoming', 3, 1),
('Kampanye Kebersihan Pantai', 'Aksi bersih pantai Kenjeran yang masih berlangsung hingga akhir bulan ini.', 'Lingkungan', 'Surabaya', '🌻', '2026-06-01', 100, 65, 'ongoing', 1, 1),
('Donor Darah Massal', 'Kegiatan donor darah berhasil mengumpulkan 248 kantong darah untuk PMI.', 'Kesehatan', 'Surabaya', '✅', '2026-06-01', 300, 248, 'done', 2, 3);

-- Program donasi seed
INSERT OR IGNORE INTO donasi_program (judul, deskripsi, kategori, target_amount, collected_amount, donatur_count, penerima_manfaat, deadline, creator_id, org_id) VALUES
('Beasiswa Anak Yatim Piatu', 'Program beasiswa pendidikan untuk anak-anak yatim piatu di seluruh Indonesia', 'Pendidikan', 250000000, 182400000, 1240, 280, '2026-06-19', 2, 2),
('Klinik Kesehatan Gratis Pelosok', 'Membangun klinik kesehatan gratis di daerah terpencil', 'Kesehatan', 500000000, 420000000, 2180, 1200, '2026-06-12', 3, 3),
('Hutan Mangrove Pesisir Jawa', 'Penanaman dan rehabilitasi hutan mangrove di pesisir Pulau Jawa', 'Lingkungan', 150000000, 78500000, 798, 50000, '2026-07-07', 1, 1);

-- Artikel seed
INSERT OR IGNORE INTO artikel (judul, konten, ringkasan, kategori, emoji_thumb, menit_baca, author_id, is_featured, is_published, published_at) VALUES
('Bhinneka Tunggal Ika di Era Digital: Membangun Jembatan Antar Generasi',
 'Di tengah derasnya arus informasi, keberagaman Indonesia menghadapi tantangan sekaligus peluang baru...',
 'Bagaimana teknologi bisa menjadi jembatan, bukan tembok pemisah di era digital.',
 'Toleransi', '🌏', 8, 2, 1, 1, '2026-06-02'),
('5 Cara Membangun Dialog Lintas Iman yang Bermakna',
 'Panduan praktis untuk memulai percakapan yang jujur dan saling menghormati...',
 'Panduan praktis membangun dialog antariman yang tulus dan bermakna.',
 'Toleransi', '✊', 5, 1, 0, 1, '2026-06-01'),
('Gotong Royong dan Kearifan Lokal dalam Menjaga Alam',
 'Nilai-nilai tradisi dari berbagai suku di Indonesia mengajarkan kita untuk merawat bumi...',
 'Kearifan lokal Nusantara sebagai fondasi menjaga kelestarian alam.',
 'Lingkungan', '🌍', 7, 3, 0, 1, '2026-05-30');

-- Harmoni index seed
INSERT OR IGNORE INTO harmoni_index (periode, skor_keseluruhan, kerukunan_agama, partisipasi_sosial, toleransi_budaya, gotong_royong, harmoni_digital) VALUES
('2026-06', 87.4, 91, 84, 88, 95, 93);

-- Aktivitas log seed
INSERT OR IGNORE INTO aktivitas_log (tipe, deskripsi, user_id, ref_id) VALUES
('join_kegiatan', 'Ahmad bergabung kegiatan Tanam Pohon', 1, 1),
('donasi', 'Donasi Rp 100K masuk ke Beasiswa Yatim', NULL, 1),
('sertifikat', 'Sari mendapat sertifikat Relawan Aktif', 2, NULL),
('daftar_org', 'Organisasi baru terdaftar dari Semarang', NULL, NULL);
