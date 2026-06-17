<?php
// ════════════════════════════════════════════════════════════
//  SERVICES — HarmoniHub
// ════════════════════════════════════════════════════════════

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Sertifikat;
use Illuminate\Support\Str;

// ─── ActivityLogService ───────────────────────────────────
class ActivityLogService
{
    public function log(
        ?int    $userId,
        string  $action,
        ?string $modelType = null,
        ?int    $modelId   = null,
        ?string $ip        = null,
    ): void {
        ActivityLog::create([
            'user_id'    => $userId,
            'action'     => $action,
            'model_type' => $modelType,
            'model_id'   => $modelId,
            'keterangan' => null,
            'ip_address' => $ip ?? request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}

// ─── SertifikatService ────────────────────────────────────
class SertifikatService
{
    public function generate(
        int     $userId,
        ?int    $kegiatanId,
        string  $jenis,
        ?string $judul       = null,
        ?string $deskripsi   = null,
    ): Sertifikat {
        $user     = \App\Models\User::findOrFail($userId);
        $kegiatan = $kegiatanId ? \App\Models\Kegiatan::find($kegiatanId) : null;

        $judul = $judul ?? match ($jenis) {
            'partisipasi'   => "Sertifikat Partisipasi: " . ($kegiatan?->judul ?? 'Kegiatan HarmoniHub'),
            'relawan_aktif' => "Sertifikat Relawan Aktif",
            'penyelenggara' => "Sertifikat Penyelenggara: " . ($kegiatan?->judul ?? ''),
            'donatur'       => "Sertifikat Donatur Peduli",
            'organisasi'    => "Sertifikat Organisasi Mitra",
            default         => "Sertifikat HarmoniHub",
        };

        return Sertifikat::create([
            'user_id'         => $userId,
            'kegiatan_id'     => $kegiatanId,
            'jenis'           => $jenis,
            'judul'           => $judul,
            'deskripsi'       => $deskripsi,
            'kode_sertifikat' => 'HH-' . now()->format('Y') . '-' . strtoupper(Str::random(8)),
            'tanggal_terbit'  => now()->toDateString(),
            'is_valid'        => true,
        ]);
    }

    public function generatePdf(Sertifikat $sertifikat): string
    {
        // Implementasi dengan library (barryvdh/laravel-dompdf):
        // $pdf = PDF::loadView('sertifikat.template', ['sertifikat' => $sertifikat]);
        // $path = 'sertifikat/' . $sertifikat->kode_sertifikat . '.pdf';
        // Storage::put($path, $pdf->output());
        // return $path;

        // Placeholder — return path dummy
        return 'sertifikat/' . $sertifikat->kode_sertifikat . '.pdf';
    }
}

// ════════════════════════════════════════════════════════════
//  MIDDLEWARE
// ════════════════════════════════════════════════════════════

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage: Route::middleware('role:admin,superadmin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki izin.',
            ], 403);
        }

        return $next($request);
    }
}

// ════════════════════════════════════════════════════════════
//  FORM REQUESTS
// ════════════════════════════════════════════════════════════

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Data tidak valid.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}

// ─── RegisterRequest ──────────────────────────────────────
class RegisterRequest extends BaseRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nama_depan'   => 'required|string|min:2|max:100',
            'nama_belakang'=> 'required|string|min:2|max:100',
            'email'        => 'required|email|unique:users,email|max:191',
            'password'     => 'required|string|min:8|confirmed',
            'no_hp'        => 'nullable|string|max:20',
            'kota'         => 'nullable|string|max:100',
            'provinsi'     => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.min' => 'Password minimal 8 karakter.',
        ];
    }
}

// ─── LoginRequest ─────────────────────────────────────────
class LoginRequest extends BaseRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }
}

// ════════════════════════════════════════════════════════════
//  POLICIES
// ════════════════════════════════════════════════════════════

namespace App\Policies;

use App\Models\Kegiatan;
use App\Models\Organisasi;
use App\Models\User;

class KegiatanPolicy
{
    public function update(User $user, Kegiatan $kegiatan): bool
    {
        return $user->id === $kegiatan->pembuat_id
            || in_array($user->role, ['admin','superadmin']);
    }

    public function delete(User $user, Kegiatan $kegiatan): bool
    {
        return $user->id === $kegiatan->pembuat_id
            || in_array($user->role, ['admin','superadmin']);
    }

    public function viewPeserta(User $user, Kegiatan $kegiatan): bool
    {
        return $user->id === $kegiatan->pembuat_id
            || in_array($user->role, ['admin','superadmin'])
            || $user->anggotaOrganisasi()
                ->where('organisasi_id', $kegiatan->organisasi_id)
                ->whereIn('role', ['admin_org','pengurus'])
                ->exists();
    }
}

class OrganisasiPolicy
{
    public function update(User $user, Organisasi $org): bool
    {
        return $user->id === $org->pendiri_user_id
            || in_array($user->role, ['admin','superadmin'])
            || $user->anggotaOrganisasi()
                ->where('organisasi_id', $org->id)
                ->where('role', 'admin_org')
                ->where('status', 'aktif')
                ->exists();
    }
}
