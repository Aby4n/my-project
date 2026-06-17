<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // ─── GET /api/v1/user/profil ──────────────────────────
    public function profil(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profilRelawan.bidangMinat', 'sertifikat']);

        $statistik = [
            'total_kegiatan' => DB::table('pendaftaran_kegiatan')
                ->where('user_id', $user->id)
                ->whereIn('status', ['hadir','dikonfirmasi'])->count(),
            'total_donasi'   => DB::table('donasi')
                ->where('user_id', $user->id)
                ->where('status', 'sukses')->sum('jumlah'),
            'total_jam'      => DB::table('pendaftaran_kegiatan')
                ->where('user_id', $user->id)
                ->where('status', 'hadir')->sum('jam_kontribusi'),
            'total_sertifikat' => DB::table('sertifikat')
                ->where('user_id', $user->id)
                ->where('is_valid', 1)->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => array_merge($user->toArray(), ['statistik' => $statistik]),
        ]);
    }

    // ─── PUT /api/v1/user/profil ──────────────────────────
    public function updateProfil(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'nama_depan'    => 'sometimes|string|min:2|max:100',
            'nama_belakang' => 'sometimes|string|min:2|max:100',
            'no_hp'         => 'nullable|string|max:20',
            'kota'          => 'nullable|string|max:100',
            'provinsi'      => 'nullable|string|max:100',
            'usia'          => 'nullable|integer|min:10|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'bio'           => 'nullable|string|max:500',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $user->fresh(),
        ]);
    }

    // ─── POST /api/v1/user/foto ───────────────────────────
    public function uploadFoto(Request $request): JsonResponse
    {
        $request->validate(['foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);

        $path = $request->file('foto')->store('user/foto-profil', 'public');
        $request->user()->update(['foto_profil' => $path]);

        return response()->json([
            'success'  => true,
            'message'  => 'Foto profil berhasil diperbarui.',
            'foto_url' => asset('storage/'.$path),
        ]);
    }

    // ─── GET /api/v1/user/riwayat-poin ───────────────────
    public function riwayatPoin(Request $request): JsonResponse
    {
        $riwayat = DB::table('riwayat_poin')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $totalPoin = $request->user()->poin;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_poin' => $totalPoin,
                'riwayat'    => $riwayat,
            ],
        ]);
    }

    // ─── GET /api/v1/user/notifikasi ─────────────────────
    public function notifikasi(Request $request): JsonResponse
    {
        $notif = DB::table('notifikasi')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        $belumDibaca = DB::table('notifikasi')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'belum_dibaca' => $belumDibaca,
                'notifikasi'   => $notif,
            ],
        ]);
    }

    // ─── PUT /api/v1/user/notifikasi/baca ─────────────────
    public function bacaNotifikasi(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'nullable|array', 'ids.*' => 'string']);

        $query = DB::table('notifikasi')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at');

        if ($request->filled('ids')) {
            $query->whereIn('id', $request->ids);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'success'  => true,
            'message'  => "{$updated} notifikasi ditandai sebagai dibaca.",
            'updated'  => $updated,
        ]);
    }

    // ─── GET /api/v1/admin/users ──────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = User::withCount(['pendaftaranKegiatan','donasi','sertifikat'])
            ->whereNull('deleted_at');

        if ($request->filled('role'))   $query->where('role', $request->role);
        if ($request->filled('kota'))   $query->where('kota', 'like', '%'.$request->kota.'%');
        if ($request->filled('status')) $query->where('is_active', $request->status === 'aktif');
        if ($request->filled('q'))      $query->where(function ($q) use ($request) {
            $q->where('nama_depan','like','%'.$request->q.'%')
              ->orWhere('nama_belakang','like','%'.$request->q.'%')
              ->orWhere('email','like','%'.$request->q.'%');
        });

        return response()->json([
            'success' => true,
            'data'    => $query->latest()->paginate($request->get('per_page', 20)),
        ]);
    }

    // ─── GET /api/v1/admin/users/{id} ─────────────────────
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'profilRelawan.bidangMinat',
            'sertifikat',
            'anggotaOrganisasi.organisasi:id,nama,logo',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $user]);
    }

    // ─── PUT /api/v1/admin/users/{id}/role ────────────────
    public function ubahRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:user,relawan,admin,superadmin']);

        User::findOrFail($id)->update(['role' => $request->role]);

        return response()->json(['success' => true, 'message' => 'Role pengguna berhasil diubah.']);
    }

    // ─── PUT /api/v1/admin/users/{id}/status ──────────────
    public function ubahStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user = User::findOrFail($id);
        $user->update(['is_active' => $request->is_active]);

        if (!$request->is_active) {
            // Hapus semua token jika dinonaktifkan
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Status akun berhasil diubah.',
        ]);
    }
}
