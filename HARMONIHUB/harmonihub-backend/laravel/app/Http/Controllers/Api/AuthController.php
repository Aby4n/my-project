<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ActivityLogService $logService) {}

    // ─── POST /api/v1/auth/register ───────────────────────
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'nama_depan'   => $request->nama_depan,
            'nama_belakang'=> $request->nama_belakang,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'no_hp'        => $request->no_hp,
            'kota'         => $request->kota,
            'provinsi'     => $request->provinsi,
            'role'         => 'user',
        ]);

        $token = $user->createToken('harmonihub-token')->plainTextToken;

        $this->logService->log($user->id, 'register');

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil! Selamat bergabung di HarmoniHub.',
            'data'    => [
                'user'  => $user->only(['id','nama_depan','nama_belakang','email','kota','role']),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    // ─── POST /api/v1/auth/login ──────────────────────────
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        // Hapus token lama (single session)
        $user->tokens()->delete();

        $token = $user->createToken('harmonihub-token')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        $this->logService->log($user->id, 'login', null, null, request()->ip());

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'user'  => $user->only([
                    'id','nama_depan','nama_belakang','email',
                    'foto_profil','kota','role','poin',
                ]),
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    // ─── POST /api/v1/auth/logout ─────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    // ─── GET /api/v1/auth/me ──────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profilRelawan');

        return response()->json([
            'success' => true,
            'data'    => $user,
        ]);
    }

    // ─── POST /api/v1/auth/forgot-password ───────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'success' => $status === Password::RESET_LINK_SENT,
            'message' => __($status),
        ]);
    }

    // ─── POST /api/v1/auth/reset-password ────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email','password','password_confirmation','token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        return response()->json([
            'success' => $status === Password::PASSWORD_RESET,
            'message' => __($status),
        ]);
    }

    // ─── PUT /api/v1/auth/password ───────────────────────
    public function ubahPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_lama' => 'required',
            'password_baru' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password_baru)]);
        $user->tokens()->delete(); // paksa login ulang

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login kembali.',
        ]);
    }
}
