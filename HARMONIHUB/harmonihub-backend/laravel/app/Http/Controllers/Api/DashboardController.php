<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IndeksHarmoni;
use App\Models\Kegiatan;
use App\Models\Donasi;
use App\Models\User;
use App\Models\Organisasi;
use App\Models\Sertifikat;
use App\Services\SertifikatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ═══════════════════════════════════════════════════════════
//  Dashboard Controller
// ═══════════════════════════════════════════════════════════
class DashboardController extends Controller
{
    // GET /api/v1/dashboard/statistik  (public)
    public function statistikPublik(): JsonResponse
    {
        $stats = DB::selectOne('SELECT * FROM v_statistik_platform');

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    // GET /api/v1/dashboard/saya  (auth)
    public function dashboardUser(Request $request): JsonResponse
    {
        $user = $request->user();

        $kegiatan = DB::table('pendaftaran_kegiatan as pk')
            ->join('kegiatan as k', 'k.id', '=', 'pk.kegiatan_id')
            ->where('pk.user_id', $user->id)
            ->select('k.id','k.judul','k.kategori','k.tanggal_mulai','k.status','pk.status as status_daftar','pk.jam_kontribusi')
            ->orderByDesc('k.tanggal_mulai')
            ->limit(5)
            ->get();

        $sertifikat = Sertifikat::where('user_id', $user->id)
            ->latest()->limit(3)->get();

        $totalDonasi = Donasi::where('user_id', $user->id)
            ->where('status', 'sukses')->sum('jumlah');

        $riwayatPoin = DB::table('riwayat_poin')
            ->where('user_id', $user->id)
            ->latest('created_at')->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'user'           => $user->only(['id','nama_depan','nama_belakang','poin','foto_profil','role']),
                'kegiatan_terkini' => $kegiatan,
                'sertifikat'       => $sertifikat,
                'total_donasi'     => $totalDonasi,
                'riwayat_poin'     => $riwayatPoin,
            ],
        ]);
    }

    // GET /api/v1/admin/dashboard  (admin)
    public function dashboardAdmin(): JsonResponse
    {
        $stats = DB::selectOne('SELECT * FROM v_statistik_platform');

        $kegiatanBulanIni = Kegiatan::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->count();

        $donasiSukses = Donasi::where('status','sukses')
            ->whereMonth('paid_at', now()->month)->sum('jumlah');

        $relawanBaru  = DB::table('profil_relawan')
            ->whereMonth('created_at', now()->month)->count();

        $orgPending   = Organisasi::where('status','pending')->count();
        $relPending   = DB::table('profil_relawan')->where('status_verifikasi','pending')->count();
        $artReview    = DB::table('artikel')->where('status','review')->count();

        return response()->json([
            'success' => true,
            'data'    => array_merge((array) $stats, [
                'kegiatan_bulan_ini' => $kegiatanBulanIni,
                'donasi_bulan_ini'   => $donasiSukses,
                'relawan_baru'       => $relawanBaru,
                'pending'            => [
                    'organisasi' => $orgPending,
                    'relawan'    => $relPending,
                    'artikel'    => $artReview,
                ],
            ]),
        ]);
    }

    // GET /api/v1/admin/dashboard/grafik  (admin)
    public function grafikAdmin(Request $request): JsonResponse
    {
        $bulan = $request->get('bulan', 6);

        // Tren donasi per bulan
        $donasiTren = DB::table('donasi')
            ->selectRaw("DATE_FORMAT(paid_at,'%Y-%m') as periode, SUM(jumlah) as total")
            ->where('status','sukses')
            ->where('paid_at', '>=', now()->subMonths($bulan))
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();

        // Tren relawan per bulan
        $relawanTren = DB::table('profil_relawan')
            ->selectRaw("DATE_FORMAT(created_at,'%Y-%m') as periode, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths($bulan))
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();

        // Distribusi kegiatan per kategori
        $kategoriDist = DB::table('kegiatan')
            ->selectRaw('kategori, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->groupBy('kategori')
            ->get();

        // Distribusi per provinsi
        $regionalDist = DB::table('users')
            ->selectRaw('provinsi, COUNT(*) as total')
            ->whereNotNull('provinsi')
            ->groupBy('provinsi')
            ->orderByDesc('total')
            ->limit(10)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'donasi_tren'   => $donasiTren,
                'relawan_tren'  => $relawanTren,
                'kategori_dist' => $kategoriDist,
                'regional_dist' => $regionalDist,
            ],
        ]);
    }
}

// ═══════════════════════════════════════════════════════════
//  Indeks Harmoni Controller
// ═══════════════════════════════════════════════════════════
class IndeksHarmoniController extends Controller
{
    // GET /api/v1/indeks-harmoni
    public function index(Request $request): JsonResponse
    {
        $query = IndeksHarmoni::query();
        if ($request->filled('kota'))   $query->where('kota', $request->kota);
        if ($request->filled('tahun'))  $query->where('periode', 'like', $request->tahun.'%');

        return response()->json([
            'success' => true,
            'data'    => $query->orderBy('periode')->get(),
        ]);
    }

    // GET /api/v1/indeks-harmoni/terkini
    public function terkini(): JsonResponse
    {
        $terkini    = IndeksHarmoni::whereNull('kota')->latest('periode')->first();
        $perKota    = IndeksHarmoni::whereNotNull('kota')
            ->where('periode', $terkini?->periode)
            ->orderByDesc('skor_total')->get();
        $historikal = IndeksHarmoni::whereNull('kota')
            ->orderBy('periode')->get(['periode','skor_total']);

        return response()->json([
            'success' => true,
            'data'    => [
                'nasional'   => $terkini,
                'per_kota'   => $perKota,
                'historikal' => $historikal,
            ],
        ]);
    }

    // POST /api/v1/admin/indeks-harmoni  (admin only)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'periode'                  => 'required|date_format:Y-m',
            'skor_total'               => 'required|numeric|min:0|max:100',
            'toleransi_antar_agama'    => 'required|numeric|min:0|max:100',
            'kerukunan_antar_suku'     => 'required|numeric|min:0|max:100',
            'partisipasi_sosial'       => 'required|numeric|min:0|max:100',
            'kepercayaan_komunitas'    => 'required|numeric|min:0|max:100',
            'kolaborasi_lintas_budaya' => 'required|numeric|min:0|max:100',
            'kota'                     => 'nullable|string',
            'provinsi'                 => 'nullable|string',
        ]);

        $indeks = IndeksHarmoni::updateOrCreate(
            ['periode' => $validated['periode'], 'kota' => $validated['kota'] ?? null],
            array_merge($validated, [
                'total_relawan'  => User::count(),
                'total_kegiatan' => Kegiatan::where('status','selesai')->count(),
                'total_donasi'   => Donasi::where('status','sukses')->sum('jumlah'),
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Indeks Harmoni berhasil disimpan.',
            'data'    => $indeks,
        ], 201);
    }

    // PUT /api/v1/admin/indeks-harmoni/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $indeks = IndeksHarmoni::findOrFail($id);
        $indeks->update($request->validate([
            'skor_total'               => 'sometimes|numeric|min:0|max:100',
            'toleransi_antar_agama'    => 'sometimes|numeric|min:0|max:100',
            'kerukunan_antar_suku'     => 'sometimes|numeric|min:0|max:100',
            'partisipasi_sosial'       => 'sometimes|numeric|min:0|max:100',
            'kepercayaan_komunitas'    => 'sometimes|numeric|min:0|max:100',
            'kolaborasi_lintas_budaya' => 'sometimes|numeric|min:0|max:100',
        ]));

        return response()->json(['success' => true, 'data' => $indeks->fresh()]);
    }
}

// ═══════════════════════════════════════════════════════════
//  Sertifikat Controller
// ═══════════════════════════════════════════════════════════
class SertifikatController extends Controller
{
    public function __construct(private SertifikatService $service) {}

    // GET /api/v1/sertifikat  (auth)
    public function index(Request $request): JsonResponse
    {
        $sertifikat = Sertifikat::with('kegiatan:id,judul,tanggal_mulai')
            ->where('user_id', $request->user()->id)
            ->latest()->get();

        return response()->json(['success' => true, 'data' => $sertifikat]);
    }

    // GET /api/v1/sertifikat/{kode}  (auth)
    public function show(string $kode): JsonResponse
    {
        $sertifikat = Sertifikat::with(['user:id,nama_depan,nama_belakang','kegiatan:id,judul'])
            ->where('kode_sertifikat', $kode)->firstOrFail();

        return response()->json(['success' => true, 'data' => $sertifikat]);
    }

    // GET /api/v1/sertifikat/verifikasi/{kode}  (public)
    public function verifikasi(string $kode): JsonResponse
    {
        $sertifikat = Sertifikat::with(['user:id,nama_depan,nama_belakang','kegiatan:id,judul,tanggal_mulai'])
            ->where('kode_sertifikat', $kode)->first();

        if (!$sertifikat) {
            return response()->json([
                'success' => false,
                'valid'   => false,
                'message' => 'Sertifikat tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'valid'   => (bool) $sertifikat->is_valid,
            'data'    => $sertifikat,
        ]);
    }

    // POST /api/v1/sertifikat/{kode}/unduh
    public function unduh(Request $request, string $kode)
    {
        $sertifikat = Sertifikat::where('kode_sertifikat', $kode)
            ->where('user_id', $request->user()->id)->firstOrFail();

        // Generate PDF on-the-fly jika belum ada file
        if (!$sertifikat->file_path) {
            $path = $this->service->generatePdf($sertifikat);
            $sertifikat->update(['file_path' => $path]);
        }

        return response()->download(storage_path('app/'.$sertifikat->file_path));
    }

    // POST /api/v1/admin/sertifikat/generate  (admin)
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'kegiatan_id' => 'nullable|exists:kegiatan,id',
            'jenis'       => 'required|in:partisipasi,relawan_aktif,penyelenggara,donatur,organisasi',
            'judul'       => 'required|string|max:255',
            'deskripsi'   => 'nullable|string',
        ]);

        $sertifikat = $this->service->generate(
            $validated['user_id'],
            $validated['kegiatan_id'],
            $validated['jenis'],
            $validated['judul'],
            $validated['deskripsi'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Sertifikat berhasil dibuat.',
            'data'    => $sertifikat,
        ], 201);
    }

    // PUT /api/v1/admin/sertifikat/{id}/revoke  (admin)
    public function revoke(int $id): JsonResponse
    {
        Sertifikat::findOrFail($id)->update(['is_valid' => false]);
        return response()->json(['success' => true, 'message' => 'Sertifikat dicabut.']);
    }
}
