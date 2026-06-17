<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ProfilRelawan;
use App\Models\BidangMinat;
use App\Models\Organisasi;
use App\Models\AnggotaOrganisasi;
use App\Models\Kegiatan;
use App\Models\PendaftaranKegiatan;
use App\Models\ProgramDonasi;
use App\Models\Donasi;
use App\Models\Artikel;
use App\Models\IndeksHarmoni;
use App\Models\Sertifikat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BidangMinatSeeder::class,
            UserSeeder::class,
            OrganisasiSeeder::class,
            KegiatanSeeder::class,
            ProgramDonasiSeeder::class,
            ArtikelSeeder::class,
            IndeksHarmoniSeeder::class,
        ]);
    }
}

// ─── BidangMinatSeeder ────────────────────────────────────
class BidangMinatSeeder extends Seeder
{
    public function run(): void
    {
        $bidang = [
            ['nama' => 'Lingkungan',    'icon' => '🌱'],
            ['nama' => 'Pendidikan',    'icon' => '📚'],
            ['nama' => 'Kesehatan',     'icon' => '🩺'],
            ['nama' => 'Pangan',        'icon' => '🍽️'],
            ['nama' => 'Seni Budaya',   'icon' => '🎨'],
            ['nama' => 'Infrastruktur', 'icon' => '🏠'],
            ['nama' => 'Sosial',        'icon' => '🤝'],
            ['nama' => 'Teknologi',     'icon' => '💻'],
        ];

        foreach ($bidang as $b) {
            BidangMinat::firstOrCreate(['nama' => $b['nama']], ['icon' => $b['icon']]);
        }
    }
}

// ─── UserSeeder ───────────────────────────────────────────
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin
        $admin = User::create([
            'nama_depan'   => 'Super',
            'nama_belakang'=> 'Admin',
            'email'        => 'admin@harmonihub.id',
            'password'     => Hash::make('Admin@12345'),
            'role'         => 'superadmin',
            'kota'         => 'Surabaya',
            'provinsi'     => 'Jawa Timur',
            'is_active'    => true,
        ]);

        // Sample users
        $users = [
            ['nama_depan' => 'Ahmad',  'nama_belakang' => 'Rizky',     'email' => 'ahmad@harmonihub.id',   'kota' => 'Surabaya',  'role' => 'relawan'],
            ['nama_depan' => 'Maria',  'nama_belakang' => 'Wulandari', 'email' => 'maria@harmonihub.id',   'kota' => 'Jakarta',   'role' => 'user'],
            ['nama_depan' => 'Budi',   'nama_belakang' => 'Santoso',   'email' => 'budi@harmonihub.id',    'kota' => 'Bandung',   'role' => 'user'],
            ['nama_depan' => 'Sari',   'nama_belakang' => 'Rahayu',    'email' => 'sari@harmonihub.id',    'kota' => 'Surabaya',  'role' => 'relawan'],
            ['nama_depan' => 'Dimas',  'nama_belakang' => 'Haryanto',  'email' => 'dimas@harmonihub.id',   'kota' => 'Bandung',   'role' => 'relawan'],
            ['nama_depan' => 'Liana',  'nama_belakang' => 'Putri',     'email' => 'liana@harmonihub.id',   'kota' => 'Jakarta',   'role' => 'relawan'],
            ['nama_depan' => 'Reza',   'nama_belakang' => 'Firmansyah','email' => 'reza@harmonihub.id',    'kota' => 'Yogyakarta','role' => 'user'],
            ['nama_depan' => 'Dewi',   'nama_belakang' => 'Kusuma',    'email' => 'dewi@harmonihub.id',    'kota' => 'Semarang',  'role' => 'user'],
        ];

        $createdUsers = [];
        foreach ($users as $data) {
            $createdUsers[] = User::create(array_merge($data, [
                'password' => Hash::make('User@12345'),
                'provinsi' => match($data['kota']) {
                    'Surabaya','Malang' => 'Jawa Timur',
                    'Jakarta'           => 'DKI Jakarta',
                    'Bandung'           => 'Jawa Barat',
                    'Yogyakarta'        => 'DI Yogyakarta',
                    'Semarang'          => 'Jawa Tengah',
                    default             => 'Jawa Timur',
                },
                'is_active' => true,
                'poin'      => rand(100, 980),
            ]));
        }

        // Profil relawan untuk user dengan role relawan
        $bidangMinat = BidangMinat::all()->pluck('id')->toArray();
        foreach ($createdUsers as $user) {
            if ($user->role === 'relawan') {
                $profil = ProfilRelawan::create([
                    'user_id'           => $user->id,
                    'keahlian'          => fake()->randomElement(['Guru','Dokter','Programmer','Designer','Fotografer','Chef']),
                    'ketersediaan'      => fake()->randomElement(['akhir_pekan','fleksibel','hari_kerja_sore']),
                    'motivasi'          => 'Ingin berkontribusi untuk masyarakat dan membangun harmoni sosial.',
                    'status_verifikasi' => 'terverifikasi',
                    'total_jam'         => rand(5, 48),
                    'total_kegiatan'    => rand(2, 15),
                    'verified_at'       => now()->subDays(rand(10, 90)),
                ]);

                // Tambah 2-4 bidang minat
                $selected = array_slice($bidangMinat, 0, rand(2, 4));
                $pivot = array_map(fn($id) => [
                    'relawan_id'      => $profil->id,
                    'bidang_minat_id' => $id,
                    'created_at'      => now(),
                ], $selected);
                DB::table('relawan_bidang_minat')->insert($pivot);

                // Riwayat poin
                DB::table('riwayat_poin')->insert([
                    'user_id'     => $user->id,
                    'poin'        => $profil->total_jam * 10,
                    'keterangan'  => "Poin dari {$profil->total_kegiatan} kegiatan",
                    'created_at'  => now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('✅ Users & relawan selesai di-seed.');
    }
}

// ─── OrganisasiSeeder ─────────────────────────────────────
class OrganisasiSeeder extends Seeder
{
    public function run(): void
    {
        $adminId  = User::where('email','admin@harmonihub.id')->value('id');
        $ahmadId  = User::where('email','ahmad@harmonihub.id')->value('id');
        $mariaId  = User::where('email','maria@harmonihub.id')->value('id');

        $organisasi = [
            [
                'nama'          => 'Komunitas Hijau Nusantara',
                'slug'          => 'komunitas-hijau-nusantara',
                'deskripsi'     => 'Organisasi lingkungan yang berdedikasi untuk pelestarian alam Indonesia melalui pendekatan lintas komunitas.',
                'bidang_fokus'  => 'lingkungan',
                'kota'          => 'Surabaya',
                'provinsi'      => 'Jawa Timur',
                'email_resmi'   => 'khg@email.com',
                'website'       => 'https://komunitas-hijau.id',
                'status'        => 'aktif',
                'is_verified'   => true,
                'total_anggota' => 240,
                'total_kegiatan'=> 18,
                'pendiri_user_id'=> $ahmadId,
                'verified_at'   => now()->subMonths(12),
            ],
            [
                'nama'          => 'Yayasan Pintar Bersama',
                'slug'          => 'yayasan-pintar-bersama',
                'deskripsi'     => 'Mendorong pemerataan pendidikan berkualitas untuk anak-anak kurang mampu di seluruh Indonesia.',
                'bidang_fokus'  => 'pendidikan',
                'kota'          => 'Jakarta',
                'provinsi'      => 'DKI Jakarta',
                'email_resmi'   => 'ypb@email.com',
                'status'        => 'aktif',
                'is_verified'   => true,
                'total_anggota' => 580,
                'total_kegiatan'=> 42,
                'pendiri_user_id'=> $mariaId,
                'verified_at'   => now()->subMonths(18),
            ],
            [
                'nama'          => 'Dokter Peduli Indonesia',
                'slug'          => 'dokter-peduli-indonesia',
                'deskripsi'     => 'Jaringan dokter dan tenaga kesehatan sukarela untuk layanan kesehatan gratis di daerah terpencil.',
                'bidang_fokus'  => 'kesehatan',
                'kota'          => 'Jakarta',
                'provinsi'      => 'DKI Jakarta',
                'email_resmi'   => 'dpi@email.com',
                'status'        => 'aktif',
                'is_verified'   => true,
                'total_anggota' => 1200,
                'total_kegiatan'=> 87,
                'pendiri_user_id'=> $adminId,
                'verified_at'   => now()->subMonths(24),
            ],
            [
                'nama'          => 'Sanggar Budaya Nusantara',
                'slug'          => 'sanggar-budaya-nusantara',
                'deskripsi'     => 'Melestarikan dan mempromosikan kekayaan budaya Indonesia melalui seni, pertunjukan, dan pendidikan budaya.',
                'bidang_fokus'  => 'budaya',
                'kota'          => 'Yogyakarta',
                'provinsi'      => 'DI Yogyakarta',
                'email_resmi'   => 'sbn@email.com',
                'status'        => 'aktif',
                'is_verified'   => true,
                'total_anggota' => 320,
                'total_kegiatan'=> 28,
                'pendiri_user_id'=> $adminId,
                'verified_at'   => now()->subMonths(6),
            ],
        ];

        foreach ($organisasi as $data) {
            $org = Organisasi::create($data);
            // Pendiri jadi admin_org
            AnggotaOrganisasi::create([
                'organisasi_id' => $org->id,
                'user_id'       => $data['pendiri_user_id'],
                'jabatan'       => 'Pendiri',
                'role'          => 'admin_org',
                'status'        => 'aktif',
                'bergabung_at'  => $data['verified_at'],
            ]);
        }

        $this->command->info('✅ Organisasi selesai di-seed.');
    }
}

// ─── KegiatanSeeder ───────────────────────────────────────
class KegiatanSeeder extends Seeder
{
    public function run(): void
    {
        $pembuat = User::where('role','relawan')->first();
        $org     = Organisasi::first();

        $kegiatan = [
            [
                'judul'          => 'Tanam 1000 Pohon Bersama',
                'slug'           => 'tanam-1000-pohon-bersama',
                'deskripsi'      => 'Kegiatan penghijauan lintas komunitas di Taman Kota Surabaya. Terbuka untuk semua kalangan tanpa memandang latar belakang.',
                'kategori'       => 'lingkungan',
                'kota'           => 'Surabaya',
                'provinsi'       => 'Jawa Timur',
                'lokasi_detail'  => 'Taman Bungkul, Jl. Raya Darmo, Surabaya',
                'tanggal_mulai'  => now()->addDays(7)->toDateString(),
                'jam_mulai'      => '07:00',
                'jam_selesai'    => '12:00',
                'kuota'          => 80,
                'total_peserta'  => 47,
                'status'         => 'aktif',
            ],
            [
                'judul'          => 'Bakti Sosial Kesehatan Gratis',
                'slug'           => 'bakti-sosial-kesehatan-gratis',
                'deskripsi'      => 'Pemeriksaan kesehatan gratis meliputi tensi, gula darah, dan konsultasi dokter umum untuk masyarakat.',
                'kategori'       => 'kesehatan',
                'kota'           => 'Jakarta',
                'provinsi'       => 'DKI Jakarta',
                'lokasi_detail'  => 'Balai Warga RW 05 Cempaka Putih',
                'tanggal_mulai'  => now()->addDays(14)->toDateString(),
                'jam_mulai'      => '08:00',
                'jam_selesai'    => '14:00',
                'kuota'          => 200,
                'total_peserta'  => 120,
                'status'         => 'aktif',
            ],
            [
                'judul'          => 'Bimbel Gratis Anak Yatim',
                'slug'           => 'bimbel-gratis-anak-yatim',
                'deskripsi'      => 'Program bimbingan belajar gratis untuk anak-anak yatim usia SD-SMP. Pengajar sukarela dari berbagai latar belakang.',
                'kategori'       => 'pendidikan',
                'kota'           => 'Bandung',
                'provinsi'       => 'Jawa Barat',
                'lokasi_detail'  => 'Panti Asuhan Al-Ikhlas, Jl. Dago Bandung',
                'tanggal_mulai'  => now()->addDays(12)->toDateString(),
                'jam_mulai'      => '09:00',
                'jam_selesai'    => '12:00',
                'kuota'          => 50,
                'total_peserta'  => 30,
                'status'         => 'aktif',
            ],
            [
                'judul'          => 'Donor Darah Massal',
                'slug'           => 'donor-darah-massal',
                'deskripsi'      => 'Program donor darah untuk memenuhi kebutuhan stok darah PMI yang menipis.',
                'kategori'       => 'kesehatan',
                'kota'           => 'Surabaya',
                'provinsi'       => 'Jawa Timur',
                'lokasi_detail'  => 'Aula Universitas Airlangga',
                'tanggal_mulai'  => now()->subDays(10)->toDateString(),
                'jam_mulai'      => '08:00',
                'jam_selesai'    => '14:00',
                'kuota'          => 300,
                'total_peserta'  => 248,
                'status'         => 'selesai',
            ],
        ];

        foreach ($kegiatan as $data) {
            Kegiatan::create(array_merge($data, [
                'pembuat_id'   => $pembuat->id,
                'organisasi_id'=> $org->id,
            ]));
        }

        $this->command->info('✅ Kegiatan selesai di-seed.');
    }
}

// ─── ProgramDonasiSeeder ──────────────────────────────────
class ProgramDonasiSeeder extends Seeder
{
    public function run(): void
    {
        $org1 = Organisasi::where('slug','komunitas-hijau-nusantara')->first();
        $org2 = Organisasi::where('slug','yayasan-pintar-bersama')->first();
        $org3 = Organisasi::where('slug','dokter-peduli-indonesia')->first();
        $user = User::where('email','admin@harmonihub.id')->first();

        $programs = [
            [
                'organisasi_id'  => $org2->id,
                'pembuat_id'     => $user->id,
                'judul'          => 'Beasiswa Anak Yatim Piatu',
                'slug'           => 'beasiswa-anak-yatim-piatu',
                'deskripsi'      => 'Program beasiswa penuh untuk 280 anak yatim piatu berprestasi dari keluarga tidak mampu.',
                'kategori'       => 'pendidikan',
                'target_dana'    => 250_000_000,
                'terkumpul'      => 182_400_000,
                'total_donatur'  => 820,
                'tanggal_mulai'  => now()->subMonths(3)->toDateString(),
                'tanggal_selesai'=> now()->addDays(12)->toDateString(),
                'status'         => 'aktif',
                'penerima_manfaat'=> 280,
            ],
            [
                'organisasi_id'  => $org3->id,
                'pembuat_id'     => $user->id,
                'judul'          => 'Klinik Kesehatan Gratis Pelosok',
                'slug'           => 'klinik-kesehatan-gratis-pelosok',
                'deskripsi'      => 'Membangun dan mengoperasikan klinik kesehatan gratis di 12 desa terpencil.',
                'kategori'       => 'kesehatan',
                'target_dana'    => 500_000_000,
                'terkumpul'      => 420_000_000,
                'total_donatur'  => 1580,
                'tanggal_mulai'  => now()->subMonths(2)->toDateString(),
                'tanggal_selesai'=> now()->addDays(5)->toDateString(),
                'status'         => 'aktif',
                'penerima_manfaat'=> 1200,
            ],
            [
                'organisasi_id'  => $org1->id,
                'pembuat_id'     => $user->id,
                'judul'          => 'Hutan Mangrove Pesisir Jawa',
                'slug'           => 'hutan-mangrove-pesisir-jawa',
                'deskripsi'      => 'Penanaman 50.000 bibit mangrove untuk memulihkan ekosistem pesisir Jawa yang rusak.',
                'kategori'       => 'lingkungan',
                'target_dana'    => 150_000_000,
                'terkumpul'      => 78_500_000,
                'total_donatur'  => 340,
                'tanggal_mulai'  => now()->subMonths(1)->toDateString(),
                'tanggal_selesai'=> now()->addMonths(2)->toDateString(),
                'status'         => 'aktif',
                'penerima_manfaat'=> 50000,
            ],
        ];

        foreach ($programs as $data) {
            ProgramDonasi::create($data);
        }

        $this->command->info('✅ Program donasi selesai di-seed.');
    }
}

// ─── ArtikelSeeder ────────────────────────────────────────
class ArtikelSeeder extends Seeder
{
    public function run(): void
    {
        $penulis = User::where('email','admin@harmonihub.id')->first();

        $artikelData = [
            [
                'judul'         => 'Bhinneka Tunggal Ika di Era Digital',
                'slug'          => 'bhinneka-tunggal-ika-di-era-digital',
                'ringkasan'     => 'Di tengah derasnya arus informasi, keberagaman Indonesia menghadapi tantangan sekaligus peluang baru.',
                'konten'        => '<p>Indonesia adalah negara yang kaya akan keberagaman. Dengan lebih dari 17.000 pulau, 300 suku bangsa, dan 6 agama yang diakui, kita memiliki modal luar biasa untuk membangun peradaban yang harmonis...</p><p>Namun di era digital, tantangan baru muncul. Hoaks, ujaran kebencian, dan polarisasi yang diperkuat algoritma media sosial mengancam kohesi sosial yang telah kita bangun selama puluhan tahun...</p>',
                'kategori'      => 'toleransi',
                'estimasi_baca' => 8,
                'status'        => 'published',
                'total_views'   => 1240,
                'total_likes'   => 89,
                'published_at'  => now()->subDays(6),
            ],
            [
                'judul'         => '5 Cara Membangun Dialog Lintas Iman yang Bermakna',
                'slug'          => '5-cara-membangun-dialog-lintas-iman',
                'ringkasan'     => 'Panduan praktis untuk memulai percakapan yang jujur dan saling menghormati dengan mereka yang berbeda keyakinan.',
                'konten'        => '<p>Dialog antar iman bukan sekadar formalitas. Ketika dilakukan dengan sungguh-sungguh, ia bisa menjadi jembatan yang menghubungkan hati manusia melampaui perbedaan keyakinan...</p>',
                'kategori'      => 'toleransi',
                'estimasi_baca' => 5,
                'status'        => 'published',
                'total_views'   => 876,
                'total_likes'   => 64,
                'published_at'  => now()->subDays(7),
            ],
            [
                'judul'         => 'Gotong Royong dan Kearifan Lokal dalam Menjaga Alam',
                'slug'          => 'gotong-royong-dan-kearifan-lokal',
                'ringkasan'     => 'Bagaimana nilai-nilai tradisi dari berbagai suku di Indonesia mengajarkan kita untuk merawat bumi bersama-sama.',
                'konten'        => '<p>Jauh sebelum ada undang-undang lingkungan hidup, nenek moyang kita sudah memiliki kearifan lokal yang mengatur hubungan manusia dengan alam...</p>',
                'kategori'      => 'lingkungan',
                'estimasi_baca' => 7,
                'status'        => 'published',
                'total_views'   => 654,
                'total_likes'   => 47,
                'published_at'  => now()->subDays(9),
            ],
            [
                'judul'         => 'Indeks Harmoni Sosial: Mengukur Kedamaian dengan Data',
                'slug'          => 'indeks-harmoni-sosial-mengukur-kedamaian',
                'ringkasan'     => 'Metodologi di balik Indeks Harmoni HarmoniHub dan bagaimana angka-angka ini mencerminkan kondisi sosial nyata.',
                'konten'        => '<p>Harmoni sosial adalah kondisi yang sulit diukur — ia terasa namun sulit dikuantifikasi. Itulah mengapa HarmoniHub mengembangkan Indeks Harmoni, sebuah metrik komposit yang menggabungkan lima dimensi kunci...</p>',
                'kategori'      => 'sosial',
                'estimasi_baca' => 9,
                'status'        => 'published',
                'total_views'   => 432,
                'total_likes'   => 38,
                'published_at'  => now()->subDays(18),
            ],
        ];

        foreach ($artikelData as $data) {
            Artikel::create(array_merge($data, ['penulis_id' => $penulis->id]));
        }

        $this->command->info('✅ Artikel selesai di-seed.');
    }
}

// ─── IndeksHarmoniSeeder ──────────────────────────────────
class IndeksHarmoniSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['periode' => '2026-01', 'skor_total' => 82.10, 'toleransi_antar_agama' => 88.00, 'kerukunan_antar_suku' => 84.00, 'partisipasi_sosial' => 78.00, 'kepercayaan_komunitas' => 75.00, 'kolaborasi_lintas_budaya' => 88.00, 'total_relawan' => 9200,  'total_kegiatan' => 1420, 'total_donasi' => 1800000000],
            ['periode' => '2026-02', 'skor_total' => 83.20, 'toleransi_antar_agama' => 89.00, 'kerukunan_antar_suku' => 85.00, 'partisipasi_sosial' => 79.00, 'kepercayaan_komunitas' => 76.00, 'kolaborasi_lintas_budaya' => 89.00, 'total_relawan' => 9800,  'total_kegiatan' => 1520, 'total_donasi' => 1950000000],
            ['periode' => '2026-03', 'skor_total' => 84.50, 'toleransi_antar_agama' => 89.50, 'kerukunan_antar_suku' => 86.00, 'partisipasi_sosial' => 81.00, 'kepercayaan_komunitas' => 77.00, 'kolaborasi_lintas_budaya' => 90.00, 'total_relawan' => 10400, 'total_kegiatan' => 1620, 'total_donasi' => 2050000000],
            ['periode' => '2026-04', 'skor_total' => 85.30, 'toleransi_antar_agama' => 90.00, 'kerukunan_antar_suku' => 86.50, 'partisipasi_sosial' => 82.00, 'kepercayaan_komunitas' => 78.00, 'kolaborasi_lintas_budaya' => 91.00, 'total_relawan' => 11000, 'total_kegiatan' => 1700, 'total_donasi' => 2150000000],
            ['periode' => '2026-05', 'skor_total' => 86.10, 'toleransi_antar_agama' => 90.50, 'kerukunan_antar_suku' => 87.00, 'partisipasi_sosial' => 83.00, 'kepercayaan_komunitas' => 78.50, 'kolaborasi_lintas_budaya' => 92.00, 'total_relawan' => 11800, 'total_kegiatan' => 1780, 'total_donasi' => 2300000000],
            ['periode' => '2026-06', 'skor_total' => 87.40, 'toleransi_antar_agama' => 91.00, 'kerukunan_antar_suku' => 88.00, 'partisipasi_sosial' => 84.00, 'kepercayaan_komunitas' => 79.00, 'kolaborasi_lintas_budaya' => 93.00, 'total_relawan' => 12480, 'total_kegiatan' => 1847, 'total_donasi' => 2467000000],
        ];

        foreach ($data as $d) {
            IndeksHarmoni::create($d);
        }

        $this->command->info('✅ Indeks Harmoni selesai di-seed.');
    }
}
