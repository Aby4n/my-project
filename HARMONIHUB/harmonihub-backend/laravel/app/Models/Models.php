<?php
// ════════════════════════════════════════════════════════════
//  MODELS — HarmoniHub
//  File ini berisi semua model Eloquent dalam satu file
//  Pisahkan ke file masing-masing saat implementasi:
//    app/Models/User.php, Kegiatan.php, dst.
// ════════════════════════════════════════════════════════════

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// ─── USER ─────────────────────────────────────────────────
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table    = 'users';
    protected $fillable = [
        'nama_depan','nama_belakang','email','password',
        'no_hp','kota','provinsi','usia','jenis_kelamin',
        'foto_profil','bio','role','poin','is_active','last_login_at',
    ];
    protected $hidden = ['password','remember_token'];
    protected $casts  = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'poin'              => 'integer',
    ];

    // Accessors
    public function getNamaLengkapAttribute(): string
    {
        return "{$this->nama_depan} {$this->nama_belakang}";
    }
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_profil ? asset('storage/'.$this->foto_profil) : null;
    }

    // Scopes
    public function scopeAktif($q)        { return $q->where('is_active', true); }
    public function scopeRelawan($q)       { return $q->where('role', 'relawan'); }

    // Relations
    public function profilRelawan()        { return $this->hasOne(ProfilRelawan::class); }
    public function kegiatan()             { return $this->hasMany(Kegiatan::class, 'pembuat_id'); }
    public function pendaftaranKegiatan()  { return $this->hasMany(PendaftaranKegiatan::class); }
    public function donasi()               { return $this->hasMany(Donasi::class); }
    public function artikel()              { return $this->hasMany(Artikel::class, 'penulis_id'); }
    public function sertifikat()           { return $this->hasMany(Sertifikat::class); }
    public function organisasi()           { return $this->hasMany(Organisasi::class, 'pendiri_user_id'); }
    public function riwayatPoin()          { return $this->hasMany(RiwayatPoin::class); }
    public function anggotaOrganisasi()    { return $this->hasMany(AnggotaOrganisasi::class); }
    public function notifikasi()           { return $this->morphMany(Notifikasi::class, 'notifiable'); }
}

// ─── PROFIL RELAWAN ───────────────────────────────────────
class ProfilRelawan extends Model
{
    use HasFactory;
    protected $table    = 'profil_relawan';
    protected $fillable = [
        'user_id','keahlian','ketersediaan','motivasi',
        'status_verifikasi','total_jam','total_kegiatan','catatan_admin','verified_at',
    ];
    protected $casts    = ['verified_at' => 'datetime', 'total_jam' => 'decimal:2'];

    public function user()         { return $this->belongsTo(User::class); }
    public function bidangMinat()  {
        return $this->belongsToMany(BidangMinat::class, 'relawan_bidang_minat', 'relawan_id', 'bidang_minat_id')
                    ->withPivot('created_at');
    }
}

// ─── BIDANG MINAT ─────────────────────────────────────────
class BidangMinat extends Model
{
    protected $table    = 'bidang_minat';
    protected $fillable = ['nama','icon'];

    public function relawan() {
        return $this->belongsToMany(ProfilRelawan::class, 'relawan_bidang_minat', 'bidang_minat_id', 'relawan_id');
    }
}

// ─── ORGANISASI ───────────────────────────────────────────
class Organisasi extends Model
{
    use HasFactory, SoftDeletes;
    protected $table    = 'organisasi';
    protected $fillable = [
        'nama','slug','deskripsi','bidang_fokus','kota','provinsi',
        'email_resmi','website','no_hp','logo','dokumen_legalitas',
        'status','is_verified','total_anggota','total_kegiatan','pendiri_user_id','verified_at',
    ];
    protected $casts = ['is_verified' => 'boolean', 'verified_at' => 'datetime'];

    public function pendiri()    { return $this->belongsTo(User::class, 'pendiri_user_id'); }
    public function anggota()    { return $this->hasMany(AnggotaOrganisasi::class); }
    public function kegiatan()   { return $this->hasMany(Kegiatan::class); }
    public function programDonasi() { return $this->hasMany(ProgramDonasi::class); }
}

// ─── ANGGOTA ORGANISASI ───────────────────────────────────
class AnggotaOrganisasi extends Model
{
    protected $table    = 'anggota_organisasi';
    protected $fillable = ['organisasi_id','user_id','jabatan','role','status','bergabung_at'];
    protected $casts    = ['bergabung_at' => 'datetime'];

    public function organisasi() { return $this->belongsTo(Organisasi::class); }
    public function user()       { return $this->belongsTo(User::class); }
}

// ─── KEGIATAN ─────────────────────────────────────────────
class Kegiatan extends Model
{
    use HasFactory, SoftDeletes;
    protected $table    = 'kegiatan';
    protected $fillable = [
        'organisasi_id','pembuat_id','judul','slug','deskripsi','kategori',
        'kota','provinsi','lokasi_detail','latitude','longitude',
        'tanggal_mulai','tanggal_selesai','jam_mulai','jam_selesai',
        'kuota','total_peserta','status','thumbnail','is_online','link_online',
        'syarat_peserta','perlengkapan','total_views',
    ];
    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'is_online'       => 'boolean',
        'latitude'        => 'decimal:8',
        'longitude'       => 'decimal:8',
    ];

    // Scopes
    public function scopeAktif($q)       { return $q->where('status', 'aktif'); }
    public function scopeByKategori($q, $kat) { return $q->where('kategori', $kat); }

    public function getPersen(): float {
        if (!$this->kuota) return 0;
        return round(($this->total_peserta / $this->kuota) * 100, 1);
    }

    // Relations
    public function pembuat()       { return $this->belongsTo(User::class, 'pembuat_id'); }
    public function organisasi()    { return $this->belongsTo(Organisasi::class); }
    public function pendaftaran()   { return $this->hasMany(PendaftaranKegiatan::class); }
    public function foto()          { return $this->hasMany(FotoKegiatan::class); }
    public function sertifikat()    { return $this->hasMany(Sertifikat::class); }
}

// ─── PENDAFTARAN KEGIATAN ─────────────────────────────────
class PendaftaranKegiatan extends Model
{
    protected $table    = 'pendaftaran_kegiatan';
    protected $fillable = [
        'kegiatan_id','user_id','jumlah_peserta','catatan',
        'status','kode_konfirmasi','check_in_at','jam_kontribusi',
    ];
    protected $casts = ['check_in_at' => 'datetime', 'jam_kontribusi' => 'decimal:2'];

    public function kegiatan() { return $this->belongsTo(Kegiatan::class); }
    public function user()     { return $this->belongsTo(User::class); }
}

// ─── PROGRAM DONASI ───────────────────────────────────────
class ProgramDonasi extends Model
{
    use HasFactory, SoftDeletes;
    protected $table    = 'program_donasi';
    protected $fillable = [
        'organisasi_id','pembuat_id','judul','slug','deskripsi','kategori',
        'target_dana','terkumpul','total_donatur','thumbnail',
        'tanggal_mulai','tanggal_selesai','status','penerima_manfaat','laporan_penggunaan',
    ];
    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'target_dana'     => 'decimal:2',
        'terkumpul'       => 'decimal:2',
    ];

    public function getPersenAttribute(): float {
        if (!$this->target_dana) return 0;
        return round(($this->terkumpul / $this->target_dana) * 100, 1);
    }

    public function organisasi() { return $this->belongsTo(Organisasi::class); }
    public function pembuat()    { return $this->belongsTo(User::class, 'pembuat_id'); }
    public function donasi()     { return $this->hasMany(Donasi::class); }
}

// ─── DONASI ───────────────────────────────────────────────
class Donasi extends Model
{
    protected $table    = 'donasi';
    protected $fillable = [
        'program_donasi_id','user_id','nama_donatur','email_donatur',
        'jumlah','pesan','is_anonim','metode_pembayaran',
        'kode_transaksi','status','payment_gateway_id','payment_proof','paid_at',
    ];
    protected $casts = [
        'is_anonim' => 'boolean',
        'jumlah'    => 'decimal:2',
        'paid_at'   => 'datetime',
    ];

    public function programDonasi() { return $this->belongsTo(ProgramDonasi::class); }
    public function user()          { return $this->belongsTo(User::class); }
}

// ─── ARTIKEL ──────────────────────────────────────────────
class Artikel extends Model
{
    use HasFactory, SoftDeletes;
    protected $table    = 'artikel';
    protected $fillable = [
        'penulis_id','judul','slug','ringkasan','konten','thumbnail',
        'kategori','estimasi_baca','total_views','total_likes','status','published_at',
    ];
    protected $casts = ['published_at' => 'datetime'];

    public function penulis()   { return $this->belongsTo(User::class, 'penulis_id'); }
    public function komentar()  { return $this->hasMany(KomentarArtikel::class); }
    public function likes()     { return $this->hasMany(LikesArtikel::class); }

    public function scopePublished($q) { return $q->where('status','published'); }
}

// ─── KOMENTAR ARTIKEL ─────────────────────────────────────
class KomentarArtikel extends Model
{
    use SoftDeletes;
    protected $table    = 'komentar_artikel';
    protected $fillable = ['artikel_id','user_id','parent_id','konten','is_approved'];
    protected $casts    = ['is_approved' => 'boolean'];

    public function artikel()  { return $this->belongsTo(Artikel::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function parent()   { return $this->belongsTo(KomentarArtikel::class, 'parent_id'); }
    public function replies()  { return $this->hasMany(KomentarArtikel::class, 'parent_id'); }
}

// ─── LIKES ARTIKEL ────────────────────────────────────────
class LikesArtikel extends Model
{
    public $timestamps = false;
    protected $table    = 'likes_artikel';
    protected $fillable = ['artikel_id','user_id'];
    protected $casts    = ['created_at' => 'datetime'];

    public function artikel() { return $this->belongsTo(Artikel::class); }
    public function user()    { return $this->belongsTo(User::class); }
}

// ─── SERTIFIKAT ───────────────────────────────────────────
class Sertifikat extends Model
{
    protected $table    = 'sertifikat';
    protected $fillable = [
        'user_id','kegiatan_id','jenis','judul','deskripsi',
        'kode_sertifikat','tanggal_terbit','file_path','is_valid',
    ];
    protected $casts = ['tanggal_terbit' => 'date', 'is_valid' => 'boolean'];

    public function user()     { return $this->belongsTo(User::class); }
    public function kegiatan() { return $this->belongsTo(Kegiatan::class); }
}

// ─── INDEKS HARMONI ───────────────────────────────────────
class IndeksHarmoni extends Model
{
    protected $table    = 'indeks_harmoni';
    protected $fillable = [
        'periode','skor_total','toleransi_antar_agama','kerukunan_antar_suku',
        'partisipasi_sosial','kepercayaan_komunitas','kolaborasi_lintas_budaya',
        'total_relawan','total_kegiatan','total_donasi','kota','provinsi',
    ];
    protected $casts = ['skor_total' => 'decimal:2', 'total_donasi' => 'decimal:2'];
}

// ─── RIWAYAT POIN ─────────────────────────────────────────
class RiwayatPoin extends Model
{
    public $timestamps = false;
    protected $table    = 'riwayat_poin';
    protected $fillable = ['user_id','poin','keterangan','referensi_type','referensi_id'];
    protected $casts    = ['created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}

// ─── FOTO KEGIATAN ────────────────────────────────────────
class FotoKegiatan extends Model
{
    public $timestamps = false;
    protected $table    = 'foto_kegiatan';
    protected $fillable = ['kegiatan_id','uploader_id','file_path','caption','urutan'];

    public function getUrlAttribute(): string { return asset('storage/'.$this->file_path); }
    public function kegiatan()  { return $this->belongsTo(Kegiatan::class); }
    public function uploader()  { return $this->belongsTo(User::class, 'uploader_id'); }
}

// ─── NOTIFIKASI ───────────────────────────────────────────
class Notifikasi extends Model
{
    protected $table    = 'notifikasi';
    protected $keyType  = 'string';
    public $incrementing = false;
    protected $fillable  = ['id','type','notifiable_type','notifiable_id','data','read_at'];
    protected $casts     = ['data' => 'array', 'read_at' => 'datetime'];

    public function notifiable() { return $this->morphTo(); }
}

// ─── ACTIVITY LOG ─────────────────────────────────────────
class ActivityLog extends Model
{
    public $timestamps = false;
    protected $table    = 'activity_log';
    protected $fillable = ['user_id','action','model_type','model_id','keterangan','ip_address','user_agent'];
    protected $casts    = ['created_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
