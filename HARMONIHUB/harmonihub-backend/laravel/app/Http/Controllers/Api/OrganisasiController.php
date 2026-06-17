<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organisasi;
use App\Models\AnggotaOrganisasi;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganisasiController extends Controller
{
    public function __construct(private ActivityLogService $logService) {}

    // ─── GET /api/v1/organisasi ───────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Organisasi::with('pendiri:id,nama_depan,nama_belakang')
            ->where('status', 'aktif')
            ->whereNull('deleted_at');

        if ($request->filled('bidang'))    $query->where('bidang_fokus', $request->bidang);
        if ($request->filled('kota'))      $query->where('kota', 'like', '%'.$request->kota.'%');
        if ($request->filled('verified'))  $query->where('is_verified', $request->verified);
        if ($request->filled('q'))         $query->where('nama', 'like', '%'.$request->q.'%');

        $sort = $request->get('sort', 'total_anggota');
        $query->orderByDesc(in_array($sort, ['total_anggota','total_kegiatan','created_at']) ? $sort : 'total_anggota');

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 12)),
        ]);
    }

    // ─── GET /api/v1/organisasi/{slug} ────────────────────
    public function show(string $slug): JsonResponse
    {
        $org = Organisasi::with([
            'pendiri:id,nama_depan,nama_belakang,foto_profil',
            'kegiatan' => fn($q) => $q->where('status', 'aktif')->latest()->limit(5),
            'anggota'  => fn($q) => $q->where('status', 'aktif')
                                       ->with('user:id,nama_depan,nama_belakang,foto_profil')
                                       ->limit(10),
        ])->where('slug', $slug)->whereNull('deleted_at')->firstOrFail();

        return response()->json(['success' => true, 'data' => $org]);
    }

    // ─── POST /api/v1/organisasi ──────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama'              => 'required|string|max:255',
            'deskripsi'         => 'nullable|string',
            'bidang_fokus'      => 'required|in:lingkungan,pendidikan,kesehatan,sosial,budaya,infrastruktur,lainnya',
            'kota'              => 'required|string|max:100',
            'provinsi'          => 'required|string|max:100',
            'email_resmi'       => 'nullable|email|max:191',
            'website'           => 'nullable|url|max:255',
            'no_hp'             => 'nullable|string|max:20',
            'logo'              => 'nullable|image|max:2048',
            'dokumen_legalitas' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('organisasi/logo', 'public');
        }
        if ($request->hasFile('dokumen_legalitas')) {
            $validated['dokumen_legalitas'] = $request->file('dokumen_legalitas')
                ->store('organisasi/dokumen', 'public');
        }

        $validated['slug']           = Str::slug($validated['nama']) . '-' . Str::random(5);
        $validated['pendiri_user_id'] = $request->user()->id;
        $validated['status']          = 'pending';

        $org = Organisasi::create($validated);

        // Pendiri otomatis jadi admin_org
        AnggotaOrganisasi::create([
            'organisasi_id' => $org->id,
            'user_id'       => $request->user()->id,
            'jabatan'       => 'Pendiri',
            'role'          => 'admin_org',
            'status'        => 'aktif',
            'bergabung_at'  => now(),
        ]);

        $this->logService->log($request->user()->id, 'create_organisasi', 'organisasi', $org->id);

        return response()->json([
            'success' => true,
            'message' => 'Organisasi berhasil didaftarkan! Tim kami akan memverifikasi dalam 3-5 hari kerja.',
            'data'    => $org,
        ], 201);
    }

    // ─── PUT /api/v1/organisasi/{id} ──────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $org = Organisasi::findOrFail($id);
        $this->authorize('update', $org);

        $validated = $request->validate([
            'nama'        => 'sometimes|string|max:255',
            'deskripsi'   => 'nullable|string',
            'bidang_fokus'=> 'sometimes|in:lingkungan,pendidikan,kesehatan,sosial,budaya,infrastruktur,lainnya',
            'kota'        => 'sometimes|string|max:100',
            'provinsi'    => 'sometimes|string|max:100',
            'email_resmi' => 'nullable|email',
            'website'     => 'nullable|url',
            'no_hp'       => 'nullable|string|max:20',
            'logo'        => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('organisasi/logo', 'public');
        }

        $org->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil diperbarui.',
            'data'    => $org->fresh(),
        ]);
    }

    // ─── DELETE /api/v1/organisasi/{id} ───────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $org = Organisasi::findOrFail($id);
        $this->authorize('update', $org);
        $org->delete();

        return response()->json(['success' => true, 'message' => 'Organisasi berhasil dihapus.']);
    }

    // ─── POST /api/v1/organisasi/{id}/bergabung ───────────
    public function bergabung(Request $request, int $id): JsonResponse
    {
        $org  = Organisasi::findOrFail($id);
        $user = $request->user();

        if (AnggotaOrganisasi::where('organisasi_id', $id)->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah terdaftar di organisasi ini.',
            ], 422);
        }

        $anggota = AnggotaOrganisasi::create([
            'organisasi_id' => $id,
            'user_id'       => $user->id,
            'role'          => 'anggota',
            'status'        => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan bergabung berhasil dikirim. Menunggu persetujuan pengurus.',
            'data'    => $anggota,
        ], 201);
    }

    // ─── DELETE /api/v1/organisasi/{id}/keluar ────────────
    public function keluar(Request $request, int $id): JsonResponse
    {
        $anggota = AnggotaOrganisasi::where('organisasi_id', $id)
            ->where('user_id', $request->user()->id)->firstOrFail();

        if ($anggota->role === 'admin_org') {
            return response()->json([
                'success' => false,
                'message' => 'Admin organisasi tidak dapat keluar. Transferkan peran terlebih dahulu.',
            ], 422);
        }

        $anggota->delete();

        return response()->json(['success' => true, 'message' => 'Anda telah keluar dari organisasi.']);
    }

    // ─── GET /api/v1/organisasi/{id}/anggota ──────────────
    public function daftarAnggota(Request $request, int $id): JsonResponse
    {
        $anggota = AnggotaOrganisasi::with('user:id,nama_depan,nama_belakang,email,foto_profil,kota')
            ->where('organisasi_id', $id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $anggota]);
    }

    // ─── PUT /api/v1/organisasi/{id}/anggota/{userId} ─────
    public function updateAnggota(Request $request, int $id, int $userId): JsonResponse
    {
        $request->validate([
            'role'    => 'sometimes|in:anggota,pengurus,admin_org',
            'status'  => 'sometimes|in:aktif,nonaktif,pending',
            'jabatan' => 'nullable|string|max:100',
        ]);

        $anggota = AnggotaOrganisasi::where('organisasi_id', $id)
            ->where('user_id', $userId)->firstOrFail();

        $anggota->update($request->only('role','status','jabatan'));

        if ($request->status === 'aktif' && !$anggota->bergabung_at) {
            $anggota->update(['bergabung_at' => now()]);
        }

        return response()->json(['success' => true, 'message' => 'Data anggota diperbarui.']);
    }

    // ─── GET /api/v1/organisasi/{id}/dashboard ────────────
    public function dashboard(Request $request, int $id): JsonResponse
    {
        $org = Organisasi::findOrFail($id);

        // Statistik bulanan
        $stat = [
            'total_anggota'  => $org->total_anggota,
            'total_kegiatan' => $org->total_kegiatan,
            'anggota_baru'   => AnggotaOrganisasi::where('organisasi_id', $id)
                ->where('status', 'aktif')
                ->whereMonth('bergabung_at', now()->month)->count(),
            'total_donasi'   => DB::table('donasi as d')
                ->join('program_donasi as pd', 'pd.id', '=', 'd.program_donasi_id')
                ->where('pd.organisasi_id', $id)
                ->where('d.status', 'sukses')
                ->sum('d.jumlah'),
        ];

        // Tren anggota 6 bulan
        $trenAnggota = AnggotaOrganisasi::where('organisasi_id', $id)
            ->where('status', 'aktif')
            ->where('bergabung_at', '>=', now()->subMonths(6))
            ->selectRaw("DATE_FORMAT(bergabung_at,'%Y-%m') as bulan, COUNT(*) as total")
            ->groupBy('bulan')->orderBy('bulan')->get();

        // Anggota terbaru
        $anggotaTerbaru = AnggotaOrganisasi::with('user:id,nama_depan,nama_belakang,foto_profil')
            ->where('organisasi_id', $id)
            ->where('status', 'aktif')
            ->latest('bergabung_at')->limit(5)->get();

        // Kegiatan aktif
        $kegiatanAktif = DB::table('kegiatan')
            ->where('organisasi_id', $id)
            ->whereIn('status', ['aktif','berlangsung'])
            ->select('id','judul','tanggal_mulai','total_peserta','kuota','status')
            ->orderBy('tanggal_mulai')->limit(5)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'organisasi'     => $org->only(['id','nama','logo','kota','bidang_fokus','is_verified']),
                'statistik'      => $stat,
                'tren_anggota'   => $trenAnggota,
                'anggota_terbaru'=> $anggotaTerbaru,
                'kegiatan_aktif' => $kegiatanAktif,
            ],
        ]);
    }

    // ─── GET /api/v1/admin/organisasi/pending ─────────────
    public function pending(): JsonResponse
    {
        $pending = Organisasi::with('pendiri:id,nama_depan,nama_belakang,email')
            ->where('status', 'pending')
            ->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $pending]);
    }

    // ─── PUT /api/v1/admin/organisasi/{id}/verifikasi ─────
    public function verifikasi(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:aktif,ditolak',
            'catatan'=> 'nullable|string|max:500',
        ]);

        $org = Organisasi::findOrFail($id);
        $org->update([
            'status'      => $request->status,
            'is_verified' => $request->status === 'aktif',
            'verified_at' => $request->status === 'aktif' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status organisasi berhasil diperbarui.',
        ]);
    }
}
