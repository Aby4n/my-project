<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donasi;
use App\Models\ProgramDonasi;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonasiController extends Controller
{
    public function __construct(private ActivityLogService $logService) {}

    // ─── GET /api/v1/donasi/program ───────────────────────
    public function indexProgram(Request $request): JsonResponse
    {
        $query = ProgramDonasi::with('organisasi:id,nama,logo')
            ->where('status', 'aktif')
            ->whereNull('deleted_at');

        if ($request->filled('kategori')) $query->where('kategori', $request->kategori);
        if ($request->filled('q'))        $query->where('judul', 'like', '%'.$request->q.'%');

        $sort = $request->get('sort', 'terkumpul'); // terkumpul | created_at | persen
        if ($sort === 'terkumpul') {
            $query->orderByDesc('terkumpul');
        } elseif ($sort === 'persen') {
            $query->orderByRaw('(terkumpul / NULLIF(target_dana,0)) DESC');
        } else {
            $query->latest();
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->get('per_page', 9)),
        ]);
    }

    // ─── GET /api/v1/donasi/program/{slug} ────────────────
    public function showProgram(string $slug): JsonResponse
    {
        $program = ProgramDonasi::with(['organisasi', 'donasi' => function ($q) {
                $q->where('status', 'sukses')
                  ->where('is_anonim', 0)
                  ->latest('paid_at')
                  ->limit(10)
                  ->select('id','program_donasi_id','nama_donatur','jumlah','pesan','paid_at');
            }])
            ->where('slug', $slug)
            ->firstOrFail();

        $persen = $program->target_dana > 0
            ? round(($program->terkumpul / $program->target_dana) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'data'    => array_merge($program->toArray(), [
                'persen_tercapai' => $persen,
                'sisa_hari'       => now()->diffInDays($program->tanggal_selesai, false),
            ]),
        ]);
    }

    // ─── POST /api/v1/donasi/buat ─────────────────────────
    public function buat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_donasi_id' => 'required|exists:program_donasi,id',
            'jumlah'            => 'required|numeric|min:1000|max:100000000',
            'metode_pembayaran' => 'required|in:transfer_bank,gopay,ovo,dana,qris,kartu_kredit,lainnya',
            'pesan'             => 'nullable|string|max:500',
            'is_anonim'         => 'boolean',
            'nama_donatur'      => 'nullable|string|max:255',
            'email_donatur'     => 'nullable|email',
        ]);

        $program = ProgramDonasi::findOrFail($validated['program_donasi_id']);

        if ($program->status !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Program donasi ini sudah tidak aktif.',
            ], 422);
        }

        $user = $request->user();

        $donasi = Donasi::create([
            'program_donasi_id' => $validated['program_donasi_id'],
            'user_id'           => $user?->id,
            'nama_donatur'      => $validated['nama_donatur'] ?? ($user ? "{$user->nama_depan} {$user->nama_belakang}" : 'Anonim'),
            'email_donatur'     => $validated['email_donatur'] ?? $user?->email,
            'jumlah'            => $validated['jumlah'],
            'pesan'             => $validated['pesan'],
            'is_anonim'         => $validated['is_anonim'] ?? false,
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'kode_transaksi'    => 'HH-' . strtoupper(Str::random(12)),
            'status'            => 'pending',
        ]);

        // Di sini integrasi Payment Gateway (Midtrans / Xendit)
        // $paymentUrl = $this->paymentGateway->createTransaction($donasi);

        if ($user) {
            $this->logService->log($user->id, 'create_donasi', 'donasi', $donasi->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat. Lanjutkan ke pembayaran.',
            'data'    => [
                'donasi'         => $donasi,
                'kode_transaksi' => $donasi->kode_transaksi,
                // 'payment_url' => $paymentUrl,
            ],
        ], 201);
    }

    // ─── GET /api/v1/donasi/riwayat ───────────────────────
    public function riwayat(Request $request): JsonResponse
    {
        $donasi = Donasi::with('programDonasi:id,judul,slug,thumbnail')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $donasi,
        ]);
    }

    // ─── GET /api/v1/donasi/{kode} ────────────────────────
    public function detail(string $kode): JsonResponse
    {
        $donasi = Donasi::with('programDonasi:id,judul,slug')
            ->where('kode_transaksi', $kode)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $donasi,
        ]);
    }

    // ─── POST /api/v1/donasi/callback (Webhook) ───────────
    public function paymentCallback(Request $request): JsonResponse
    {
        // Validasi signature dari payment gateway
        // $this->verifySignature($request);

        $kode   = $request->input('order_id'); // sesuaikan key payment gateway
        $status = $request->input('transaction_status');

        $donasi = Donasi::where('kode_transaksi', $kode)->first();
        if (!$donasi) return response()->json(['ok' => false], 404);

        if (in_array($status, ['capture','settlement'])) {
            $donasi->update([
                'status'  => 'sukses',
                'paid_at' => now(),
            ]);
            // Update terkumpul via stored procedure
            DB::statement('CALL sp_update_donasi(?)', [$donasi->id]);
        } elseif (in_array($status, ['deny','cancel','expire'])) {
            $donasi->update(['status' => 'gagal']);
        }

        return response()->json(['ok' => true]);
    }
}
