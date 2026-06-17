<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\PendaftaranKegiatan;
use App\Models\Sertifikat;
use App\Services\ActivityLogService;
use App\Services\SertifikatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KegiatanController extends Controller
{
    public function __construct(
        private ActivityLogService $logService,
        private SertifikatService  $sertifikatService,
    ) {}

    // ─── GET /api/v1/kegiatan ─────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Kegiatan::with(['pembuat:id,nama_depan,nama_belakang,foto_profil', 'organisasi:id,nama,logo'])
            ->whereNull('deleted_at');

        // Filter
        if ($request->filled('status'))    $query->where('status', $request->status);
        if ($request->filled('kategori'))  $query->where('kategori', $request->kategori);
        if ($request->filled('kota'))      $query->where('kota', 'like', '%'.$request->kota.'%');
        if ($request->filled('q'))         $query->where('judul', 'like', '%'.$request->q.'%');

        // Sort
        $sortBy  = in_array($request->sort, ['tanggal_mulai','total_peserta','created_at'])
                    ? $request->sort : 'tanggal_mulai';
        $sortDir = $request->dir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        $kegiatan = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data'    => $kegiatan,
        ]);
    }

    // ─── GET /api/v1/kegiatan/{slug} ──────────────────────
    public function show(string $slug): JsonResponse
    {
        $kegiatan = Kegiatan::with([
            'pembuat:id,nama_depan,nama_belakang,foto_profil',
            'organisasi:id,nama,logo,kota',
            'foto',
        ])->where('slug', $slug)->whereNull('deleted_at')->firstOrFail();

        $kegiatan->increment('total_views');

        return response()->json([
            'success' => true,
            'data'    => $kegiatan,
        ]);
    }

    // ─── POST /api/v1/kegiatan ────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul'           => 'required|string|max:255',
            'deskripsi'       => 'nullable|string',
            'kategori'        => 'required|in:lingkungan,pendidikan,kesehatan,sosial,budaya,infrastruktur,lainnya',
            'kota'            => 'required|string|max:100',
            'provinsi'        => 'required|string|max:100',
            'lokasi_detail'   => 'nullable|string|max:255',
            'tanggal_mulai'   => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'jam_mulai'       => 'nullable|date_format:H:i',
            'jam_selesai'     => 'nullable|date_format:H:i',
            'kuota'           => 'nullable|integer|min:1',
            'organisasi_id'   => 'nullable|exists:organisasi,id',
            'is_online'       => 'boolean',
            'link_online'     => 'nullable|url',
            'syarat_peserta'  => 'nullable|string',
            'perlengkapan'    => 'nullable|string',
            'thumbnail'       => 'nullable|image|max:2048',
        ]);

        $validated['pembuat_id'] = $request->user()->id;
        $validated['slug']       = Str::slug($validated['judul']) . '-' . Str::random(6);
        $validated['status']     = 'aktif';

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')
                ->store('kegiatan/thumbnails', 'public');
        }

        $kegiatan = Kegiatan::create($validated);

        $this->logService->log($request->user()->id, 'create_kegiatan', 'kegiatan', $kegiatan->id);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil dibuat.',
            'data'    => $kegiatan,
        ], 201);
    }

    // ─── PUT /api/v1/kegiatan/{id} ────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);

        $this->authorize('update', $kegiatan);

        $validated = $request->validate([
            'judul'           => 'sometimes|string|max:255',
            'deskripsi'       => 'nullable|string',
            'kategori'        => 'sometimes|in:lingkungan,pendidikan,kesehatan,sosial,budaya,infrastruktur,lainnya',
            'kota'            => 'sometimes|string|max:100',
            'provinsi'        => 'sometimes|string|max:100',
            'lokasi_detail'   => 'nullable|string',
            'tanggal_mulai'   => 'sometimes|date',
            'tanggal_selesai' => 'nullable|date',
            'kuota'           => 'nullable|integer|min:1',
            'status'          => 'sometimes|in:draft,aktif,berlangsung,selesai,dibatalkan',
        ]);

        $kegiatan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil diperbarui.',
            'data'    => $kegiatan->fresh(),
        ]);
    }

    // ─── DELETE /api/v1/kegiatan/{id} ─────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $this->authorize('delete', $kegiatan);
        $kegiatan->delete(); // soft delete

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil dihapus.',
        ]);
    }

    // ─── POST /api/v1/kegiatan/{id}/daftar ───────────────
    public function daftar(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);

        // Cek sudah daftar
        if (PendaftaranKegiatan::where('kegiatan_id', $id)
            ->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mendaftar di kegiatan ini.',
            ], 422);
        }

        // Cek kuota
        if ($kegiatan->kuota && $kegiatan->total_peserta >= $kegiatan->kuota) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota kegiatan sudah penuh.',
            ], 422);
        }

        $validated = $request->validate([
            'jumlah_peserta' => 'integer|min:1|max:10',
            'catatan'        => 'nullable|string|max:500',
        ]);

        $pendaftaran = PendaftaranKegiatan::create([
            'kegiatan_id'    => $id,
            'user_id'        => $request->user()->id,
            'jumlah_peserta' => $validated['jumlah_peserta'] ?? 1,
            'catatan'        => $validated['catatan'] ?? null,
            'status'         => 'dikonfirmasi',
            'kode_konfirmasi'=> strtoupper(Str::random(8)),
        ]);

        // Update counter
        $kegiatan->increment('total_peserta', $pendaftaran->jumlah_peserta);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil! Cek email untuk konfirmasi.',
            'data'    => $pendaftaran,
        ], 201);
    }

    // ─── DELETE /api/v1/kegiatan/{id}/batal ──────────────
    public function batalDaftar(Request $request, int $id): JsonResponse
    {
        $pendaftaran = PendaftaranKegiatan::where('kegiatan_id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $jumlah = $pendaftaran->jumlah_peserta;
        $pendaftaran->update(['status' => 'dibatalkan']);
        Kegiatan::find($id)->decrement('total_peserta', $jumlah);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil dibatalkan.',
        ]);
    }

    // ─── GET /api/v1/kegiatan/{id}/peserta ───────────────
    public function daftarPeserta(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::findOrFail($id);
        $this->authorize('viewPeserta', $kegiatan);

        $peserta = PendaftaranKegiatan::with('user:id,nama_depan,nama_belakang,email,no_hp,foto_profil')
            ->where('kegiatan_id', $id)
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $peserta,
        ]);
    }

    // ─── PUT /api/v1/kegiatan/{id}/peserta/{userId}/status ─
    public function updateStatusPeserta(Request $request, int $id, int $userId): JsonResponse
    {
        $request->validate([
            'status'        => 'required|in:dikonfirmasi,hadir,tidak_hadir,dibatalkan',
            'jam_kontribusi'=> 'nullable|numeric|min:0|max:24',
        ]);

        $pendaftaran = PendaftaranKegiatan::where('kegiatan_id', $id)
            ->where('user_id', $userId)->firstOrFail();

        $pendaftaran->update($request->only('status','jam_kontribusi'));

        // Jika hadir & ada jam kontribusi → tambah poin
        if ($request->status === 'hadir' && $request->jam_kontribusi > 0) {
            DB::statement('CALL sp_tambah_poin_kehadiran(?,?,?)', [
                $userId, $id, $request->jam_kontribusi
            ]);

            // Generate sertifikat
            $this->sertifikatService->generate($userId, $id, 'partisipasi');
        }

        return response()->json([
            'success' => true,
            'message' => 'Status peserta diperbarui.',
        ]);
    }

    // ─── POST /api/v1/kegiatan/{id}/foto ─────────────────
    public function uploadFoto(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'foto'    => 'required|image|max:4096',
            'caption' => 'nullable|string|max:255',
        ]);

        $kegiatan = Kegiatan::findOrFail($id);
        $path = $request->file('foto')->store('kegiatan/foto', 'public');

        $foto = $kegiatan->foto()->create([
            'uploader_id' => $request->user()->id,
            'file_path'   => $path,
            'caption'     => $request->caption,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto berhasil diunggah.',
            'data'    => $foto,
        ], 201);
    }
}
