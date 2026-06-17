<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilRelawan;
use App\Models\BidangMinat;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelawanController extends Controller
{
    public function __construct(private ActivityLogService $logService) {}

    // ─── POST /api/v1/relawan/daftar ──────────────────────
    public function daftar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (ProfilRelawan::where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah terdaftar sebagai relawan.',
            ], 422);
        }

        $validated = $request->validate([
            'keahlian'       => 'nullable|string|max:255',
            'ketersediaan'   => 'required|in:akhir_pekan,hari_kerja_sore,fleksibel,full_time',
            'motivasi'       => 'nullable|string|max:2000',
            'bidang_minat'   => 'nullable|array',
            'bidang_minat.*' => 'exists:bidang_minat,id',
        ]);

        $profil = ProfilRelawan::create([
            'user_id'           => $user->id,
            'keahlian'          => $validated['keahlian'] ?? null,
            'ketersediaan'      => $validated['ketersediaan'],
            'motivasi'          => $validated['motivasi'] ?? null,
            'status_verifikasi' => 'pending',
        ]);

        // Simpan bidang minat
        if (!empty($validated['bidang_minat'])) {
            $pivot = array_map(fn($id) => [
                'relawan_id'      => $profil->id,
                'bidang_minat_id' => $id,
                'created_at'      => now(),
            ], $validated['bidang_minat']);
            DB::table('relawan_bidang_minat')->insert($pivot);
        }

        // Update role user → relawan
        $user->update(['role' => 'relawan']);

        $this->logService->log($user->id, 'daftar_relawan', 'profil_relawan', $profil->id);

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran relawan berhasil! Tim kami akan memverifikasi dalam 1-3 hari kerja.',
            'data'    => $profil->load('bidangMinat'),
        ], 201);
    }

    // ─── GET /api/v1/relawan/profil ───────────────────────
    public function profilSaya(Request $request): JsonResponse
    {
        $profil = ProfilRelawan::with(['user', 'bidangMinat'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $profil]);
    }

    // ─── PUT /api/v1/relawan/profil ───────────────────────
    public function updateProfil(Request $request): JsonResponse
    {
        $profil = ProfilRelawan::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'keahlian'       => 'nullable|string|max:255',
            'ketersediaan'   => 'in:akhir_pekan,hari_kerja_sore,fleksibel,full_time',
            'motivasi'       => 'nullable|string|max:2000',
            'bidang_minat'   => 'nullable|array',
            'bidang_minat.*' => 'exists:bidang_minat,id',
        ]);

        $profil->update(collect($validated)->except('bidang_minat')->toArray());

        if (isset($validated['bidang_minat'])) {
            DB::table('relawan_bidang_minat')->where('relawan_id', $profil->id)->delete();
            $pivot = array_map(fn($id) => [
                'relawan_id'      => $profil->id,
                'bidang_minat_id' => $id,
                'created_at'      => now(),
            ], $validated['bidang_minat']);
            DB::table('relawan_bidang_minat')->insert($pivot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil relawan berhasil diperbarui.',
            'data'    => $profil->fresh()->load('bidangMinat'),
        ]);
    }

    // ─── GET /api/v1/relawan/kegiatan ─────────────────────
    public function kegiatanSaya(Request $request): JsonResponse
    {
        $kegiatan = DB::table('pendaftaran_kegiatan as pk')
            ->join('kegiatan as k', 'k.id', '=', 'pk.kegiatan_id')
            ->where('pk.user_id', $request->user()->id)
            ->select(
                'k.id', 'k.judul', 'k.slug', 'k.kategori',
                'k.kota', 'k.tanggal_mulai', 'k.status as status_kegiatan',
                'pk.status as status_daftar', 'pk.jam_kontribusi',
                'pk.kode_konfirmasi', 'pk.created_at as daftar_at'
            )
            ->orderByDesc('k.tanggal_mulai')
            ->paginate(10);

        return response()->json(['success' => true, 'data' => $kegiatan]);
    }

    // ─── GET /api/v1/relawan/sertifikat ───────────────────
    public function sertifikatSaya(Request $request): JsonResponse
    {
        $sertifikat = DB::table('sertifikat as s')
            ->leftJoin('kegiatan as k', 'k.id', '=', 's.kegiatan_id')
            ->where('s.user_id', $request->user()->id)
            ->where('s.is_valid', 1)
            ->select('s.*', 'k.judul as judul_kegiatan', 'k.tanggal_mulai')
            ->orderByDesc('s.tanggal_terbit')
            ->get();

        return response()->json(['success' => true, 'data' => $sertifikat]);
    }

    // ─── GET /api/v1/relawan/leaderboard ──────────────────
    public function leaderboard(Request $request): JsonResponse
    {
        $periode = $request->get('periode', 'bulan'); // bulan | semua

        $query = DB::table('users as u')
            ->join('profil_relawan as pr', 'pr.user_id', '=', 'u.id')
            ->where('pr.status_verifikasi', 'terverifikasi')
            ->select(
                'u.id', 'u.nama_depan', 'u.nama_belakang',
                'u.foto_profil', 'u.kota', 'u.poin',
                'pr.total_jam', 'pr.total_kegiatan'
            );

        if ($periode === 'bulan') {
            // Poin bulan ini saja
            $query = DB::table('users as u')
                ->join('profil_relawan as pr', 'pr.user_id', '=', 'u.id')
                ->leftJoin('riwayat_poin as rp', function ($join) {
                    $join->on('rp.user_id', '=', 'u.id')
                         ->whereRaw("MONTH(rp.created_at) = MONTH(NOW())")
                         ->whereRaw("YEAR(rp.created_at) = YEAR(NOW())");
                })
                ->where('pr.status_verifikasi', 'terverifikasi')
                ->select(
                    'u.id', 'u.nama_depan', 'u.nama_belakang',
                    'u.foto_profil', 'u.kota',
                    DB::raw('COALESCE(SUM(rp.poin),0) as poin_bulan'),
                    'pr.total_jam', 'pr.total_kegiatan'
                )
                ->groupBy('u.id','u.nama_depan','u.nama_belakang','u.foto_profil','u.kota','pr.total_jam','pr.total_kegiatan')
                ->orderByDesc('poin_bulan');
        } else {
            $query->orderByDesc('u.poin');
        }

        $leaderboard = $query->limit(20)->get()->map(function ($r, $i) {
            $r->peringkat = $i + 1;
            return $r;
        });

        return response()->json(['success' => true, 'data' => $leaderboard]);
    }

    // ─── GET /api/v1/admin/relawan/pending ────────────────
    public function pending(): JsonResponse
    {
        $pending = ProfilRelawan::with(['user:id,nama_depan,nama_belakang,email,kota,created_at', 'bidangMinat'])
            ->where('status_verifikasi', 'pending')
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $pending]);
    }

    // ─── PUT /api/v1/admin/relawan/{id}/verifikasi ────────
    public function verifikasi(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'        => 'required|in:terverifikasi,ditolak',
            'catatan_admin' => 'nullable|string|max:500',
        ]);

        $profil = ProfilRelawan::findOrFail($id);
        $profil->update([
            'status_verifikasi' => $request->status,
            'catatan_admin'     => $request->catatan_admin,
            'verified_at'       => $request->status === 'terverifikasi' ? now() : null,
        ]);

        // TODO: kirim notifikasi ke user

        return response()->json([
            'success' => true,
            'message' => 'Status verifikasi relawan berhasil diperbarui.',
        ]);
    }
}
