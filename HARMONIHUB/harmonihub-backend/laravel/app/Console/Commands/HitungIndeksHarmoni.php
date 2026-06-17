<?php

namespace App\Console\Commands;

use App\Models\IndeksHarmoni;
use App\Models\User;
use App\Models\Kegiatan;
use App\Models\Donasi;
use App\Models\PendaftaranKegiatan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HitungIndeksHarmoni extends Command
{
    protected $signature   = 'harmonihub:hitung-indeks {--periode= : Format YYYY-MM (default: bulan ini)}';
    protected $description = 'Hitung dan simpan Indeks Harmoni bulan ini secara otomatis';

    public function handle(): int
    {
        $periode = $this->option('periode') ?? now()->format('Y-m');
        $this->info("🌿 Menghitung Indeks Harmoni periode: {$periode}");

        [$tahun, $bulan] = explode('-', $periode);

        // ── Data dasar ──
        $totalRelawan  = DB::table('profil_relawan')->where('status_verifikasi','terverifikasi')->count();
        $totalKegiatan = Kegiatan::whereRaw("DATE_FORMAT(created_at,'%Y-%m') = ?", [$periode])->count();
        $totalDonasi   = Donasi::where('status','sukses')
            ->whereYear('paid_at', $tahun)->whereMonth('paid_at', $bulan)->sum('jumlah');

        // ── Hitung sub-indeks (formula sederhana berbasis data platform) ──

        // 1. Toleransi antar agama: berbasis jumlah kegiatan lintas organisasi
        $kegiatanLintas = Kegiatan::whereNotNull('organisasi_id')
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m') <= ?", [$periode])
            ->count();
        $toleransi = min(100, 70 + ($kegiatanLintas / 100));

        // 2. Kerukunan antar suku: berbasis distribusi kota relawan
        $distribusiKota = DB::table('users')->whereNotNull('kota')->distinct('kota')->count();
        $kerukunan = min(100, 60 + ($distribusiKota * 2));

        // 3. Partisipasi sosial: berbasis total pendaftaran kegiatan yang hadir
        $hadir = PendaftaranKegiatan::where('status','hadir')
            ->whereYear('created_at', $tahun)->whereMonth('created_at', $bulan)->count();
        $partisipasi = min(100, 50 + ($hadir / 10));

        // 4. Kepercayaan komunitas: berbasis % donasi yang berhasil vs total
        $totalTrx  = Donasi::whereYear('created_at',$tahun)->whereMonth('created_at',$bulan)->count();
        $suksesTrx = Donasi::where('status','sukses')->whereYear('paid_at',$tahun)->whereMonth('paid_at',$bulan)->count();
        $kepercayaan = $totalTrx > 0 ? min(100, ($suksesTrx / $totalTrx) * 90 + 10) : 70;

        // 5. Kolaborasi lintas budaya: berbasis kegiatan kategori budaya & sosial
        $budayaSosial = Kegiatan::whereIn('kategori',['budaya','sosial'])
            ->whereRaw("DATE_FORMAT(created_at,'%Y-%m') <= ?", [$periode])->count();
        $kolaborasi = min(100, 65 + ($budayaSosial / 50));

        // ── Total ──
        $skor = round(($toleransi + $kerukunan + $partisipasi + $kepercayaan + $kolaborasi) / 5, 2);

        $indeks = IndeksHarmoni::updateOrCreate(
            ['periode' => $periode, 'kota' => null],
            [
                'skor_total'               => $skor,
                'toleransi_antar_agama'    => round($toleransi, 2),
                'kerukunan_antar_suku'     => round($kerukunan, 2),
                'partisipasi_sosial'       => round($partisipasi, 2),
                'kepercayaan_komunitas'    => round($kepercayaan, 2),
                'kolaborasi_lintas_budaya' => round($kolaborasi, 2),
                'total_relawan'            => $totalRelawan,
                'total_kegiatan'           => $totalKegiatan,
                'total_donasi'             => $totalDonasi,
            ]
        );

        $this->table(
            ['Dimensi', 'Skor'],
            [
                ['Toleransi Antar Agama',    round($toleransi, 2)],
                ['Kerukunan Antar Suku',     round($kerukunan, 2)],
                ['Partisipasi Sosial',       round($partisipasi, 2)],
                ['Kepercayaan Komunitas',    round($kepercayaan, 2)],
                ['Kolaborasi Lintas Budaya', round($kolaborasi, 2)],
                ['═══ TOTAL ═══',            $skor],
            ]
        );

        $this->info("✅ Indeks Harmoni {$periode}: {$skor}/100 berhasil disimpan.");
        return Command::SUCCESS;
    }
}

// ════════════════════════════════════════════════════════════
//  routes/console.php — Artisan schedules
// ════════════════════════════════════════════════════════════

// File: routes/console.php
/*
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\HitungIndeksHarmoni;
use App\Models\Kegiatan;

// Hitung indeks harmoni otomatis setiap awal bulan pukul 01:00
Schedule::command('harmonihub:hitung-indeks')->monthlyOn(1, '01:00');

// Update status kegiatan yang sudah lewat tanggal selesai
Schedule::call(function () {
    Kegiatan::where('status', 'aktif')
        ->where('tanggal_selesai', '<', now()->toDateString())
        ->update(['status' => 'selesai']);
})->daily()->at('00:05')->name('update-status-kegiatan');

// Kirim notifikasi reminder 3 hari sebelum kegiatan
Schedule::command('harmonihub:reminder-kegiatan')->daily()->at('08:00');
*/
