<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artikel;
use App\Models\KomentarArtikel;
use App\Models\LikesArtikel;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArtikelController extends Controller
{
    public function __construct(private ActivityLogService $logService) {}

    // ─── GET /api/v1/artikel ──────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = Artikel::with('penulis:id,nama_depan,nama_belakang,foto_profil')
            ->where('status', 'published')
            ->whereNull('deleted_at');

        if ($request->filled('kategori'))
            $query->where('kategori', $request->kategori);

        if ($request->filled('q'))
            $query->whereRaw(
                'MATCH(judul,ringkasan,konten) AGAINST(? IN BOOLEAN MODE)',
                [$request->q . '*']
            );

        $sort = $request->get('sort', 'published_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['published_at', 'total_views', 'total_likes'];
        $query->orderBy(in_array($sort, $allowedSorts) ? $sort : 'published_at', $sortDir);

        $artikel = $query->select([
            'id','penulis_id','judul','slug','ringkasan','thumbnail',
            'kategori','estimasi_baca','total_views','total_likes','published_at',
        ])->paginate($request->get('per_page', 9));

        return response()->json(['success' => true, 'data' => $artikel]);
    }

    // ─── GET /api/v1/artikel/{slug} ───────────────────────
    public function show(Request $request, string $slug): JsonResponse
    {
        $artikel = Artikel::with([
            'penulis:id,nama_depan,nama_belakang,foto_profil,bio',
            'komentar' => fn($q) => $q->where('is_approved', 1)
                ->whereNull('parent_id')
                ->with([
                    'user:id,nama_depan,nama_belakang,foto_profil',
                    'replies.user:id,nama_depan,nama_belakang,foto_profil',
                ])
                ->latest()->limit(20),
        ])->where('slug', $slug)->where('status', 'published')->firstOrFail();

        $artikel->increment('total_views');

        // Apakah user sudah like?
        $sudahLike = false;
        if ($user = $request->user()) {
            $sudahLike = LikesArtikel::where('artikel_id', $artikel->id)
                ->where('user_id', $user->id)->exists();
        }

        // Artikel terkait (kategori sama)
        $terkait = Artikel::where('kategori', $artikel->kategori)
            ->where('status', 'published')
            ->where('id', '!=', $artikel->id)
            ->select('id','judul','slug','thumbnail','estimasi_baca','published_at')
            ->latest('published_at')->limit(3)->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge($artikel->toArray(), [
                'sudah_like' => $sudahLike,
                'terkait'    => $terkait,
            ]),
        ]);
    }

    // ─── POST /api/v1/artikel ─────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul'          => 'required|string|max:255',
            'ringkasan'      => 'nullable|string|max:500',
            'konten'         => 'required|string',
            'kategori'       => 'required|in:toleransi,sosial,lingkungan,pendidikan,kesehatan,inspirasi,lainnya',
            'estimasi_baca'  => 'nullable|integer|min:1|max:60',
            'thumbnail'      => 'nullable|image|max:2048',
            'status'         => 'in:draft,review',
        ]);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')
                ->store('artikel/thumbnail', 'public');
        }

        $validated['penulis_id'] = $request->user()->id;
        $validated['slug']       = Str::slug($validated['judul']) . '-' . Str::random(6);
        $validated['status']     = $validated['status'] ?? 'review';

        // Auto-hitung estimasi baca jika tidak diisi
        if (!isset($validated['estimasi_baca'])) {
            $wordCount = str_word_count(strip_tags($validated['konten']));
            $validated['estimasi_baca'] = max(1, (int) ceil($wordCount / 200));
        }

        $artikel = Artikel::create($validated);
        $this->logService->log($request->user()->id, 'create_artikel', 'artikel', $artikel->id);

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil dikirim dan sedang menunggu review.',
            'data'    => $artikel,
        ], 201);
    }

    // ─── PUT /api/v1/artikel/{id} ─────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);

        if ($artikel->penulis_id !== $request->user()->id &&
            !in_array($request->user()->role, ['admin','superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $validated = $request->validate([
            'judul'         => 'sometimes|string|max:255',
            'ringkasan'     => 'nullable|string|max:500',
            'konten'        => 'sometimes|string',
            'kategori'      => 'sometimes|in:toleransi,sosial,lingkungan,pendidikan,kesehatan,inspirasi,lainnya',
            'estimasi_baca' => 'nullable|integer|min:1',
            'thumbnail'     => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')
                ->store('artikel/thumbnail', 'public');
        }

        $artikel->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Artikel berhasil diperbarui.',
            'data'    => $artikel->fresh(),
        ]);
    }

    // ─── DELETE /api/v1/artikel/{id} ──────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);

        if ($artikel->penulis_id !== $request->user()->id &&
            !in_array($request->user()->role, ['admin','superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $artikel->delete();
        return response()->json(['success' => true, 'message' => 'Artikel berhasil dihapus.']);
    }

    // ─── POST /api/v1/artikel/{id}/like ───────────────────
    public function toggleLike(Request $request, int $id): JsonResponse
    {
        $artikel = Artikel::findOrFail($id);
        $userId  = $request->user()->id;

        $like = LikesArtikel::where('artikel_id', $id)->where('user_id', $userId)->first();

        if ($like) {
            $like->delete();
            $artikel->decrement('total_likes');
            $action = 'unlike';
        } else {
            LikesArtikel::create(['artikel_id' => $id, 'user_id' => $userId, 'created_at' => now()]);
            $artikel->increment('total_likes');
            $action = 'like';
        }

        return response()->json([
            'success'     => true,
            'action'      => $action,
            'total_likes' => $artikel->fresh()->total_likes,
        ]);
    }

    // ─── POST /api/v1/artikel/{id}/komentar ───────────────
    public function komentar(Request $request, int $id): JsonResponse
    {
        Artikel::findOrFail($id);

        $validated = $request->validate([
            'konten'    => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:komentar_artikel,id',
        ]);

        $komentar = KomentarArtikel::create([
            'artikel_id'  => $id,
            'user_id'     => $request->user()->id,
            'konten'      => $validated['konten'],
            'parent_id'   => $validated['parent_id'] ?? null,
            'is_approved' => 0, // moderasi dulu
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dikirim dan menunggu persetujuan.',
            'data'    => $komentar->load('user:id,nama_depan,nama_belakang,foto_profil'),
        ], 201);
    }

    // ─── DELETE /api/v1/artikel/komentar/{id} ─────────────
    public function hapusKomentar(Request $request, int $id): JsonResponse
    {
        $komentar = KomentarArtikel::findOrFail($id);

        if ($komentar->user_id !== $request->user()->id &&
            !in_array($request->user()->role, ['admin','superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Tidak diizinkan.'], 403);
        }

        $komentar->delete();
        return response()->json(['success' => true, 'message' => 'Komentar berhasil dihapus.']);
    }

    // ─── GET /api/v1/admin/artikel/review ─────────────────
    public function indexReview(): JsonResponse
    {
        $artikel = Artikel::with('penulis:id,nama_depan,nama_belakang,email')
            ->whereIn('status', ['review'])
            ->latest()->paginate(15);

        return response()->json(['success' => true, 'data' => $artikel]);
    }

    // ─── PUT /api/v1/admin/artikel/{id}/publish ───────────
    public function publish(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'  => 'required|in:published,archived,draft',
            'catatan' => 'nullable|string|max:500',
        ]);

        $artikel = Artikel::findOrFail($id);
        $artikel->update([
            'status'       => $request->status,
            'published_at' => $request->status === 'published' ? now() : $artikel->published_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status artikel berhasil diperbarui.',
        ]);
    }
}
