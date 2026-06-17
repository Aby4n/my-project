"""
HarmoniHub — Backend Server
Flask + SQLite REST API
"""

import sqlite3
import os
import hashlib
import hmac
import json
import re
import uuid
from datetime import datetime, timedelta
from functools import wraps
from flask import Flask, request, jsonify, g
from flask_cors import CORS

# ─── CONFIG ───────────────────────────────────────────────────
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH  = os.path.join(BASE_DIR, "harmonihub.db")
SECRET_KEY = os.environ.get("SECRET_KEY", "harmonihub-secret-key-ganti-di-produksi")

app = Flask(__name__)
CORS(app, resources={r"/api/*": {"origins": "*"}})


# ─── DATABASE ─────────────────────────────────────────────────
def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON")
    return g.db

@app.teardown_appcontext
def close_db(e=None):
    db = g.pop("db", None)
    if db:
        db.close()

def init_db():
    """Buat tabel dan seed data dari schema.sql"""
    with app.app_context():
        db = get_db()
        schema_path = os.path.join(BASE_DIR, "schema.sql")
        with open(schema_path, "r") as f:
            db.executescript(f.read())
        db.commit()
        print("✅  Database siap:", DB_PATH)

def query(sql, params=(), one=False):
    db  = get_db()
    cur = db.execute(sql, params)
    rv  = cur.fetchall()
    return (rv[0] if rv else None) if one else rv

def mutate(sql, params=()):
    db  = get_db()
    cur = db.execute(sql, params)
    db.commit()
    return cur.lastrowid


# ─── AUTH (simple token — pakai JWT library opsional) ─────────
def make_token(user_id: int) -> str:
    payload = f"{user_id}:{SECRET_KEY}:{datetime.utcnow().isoformat()}"
    token   = hmac.new(
        SECRET_KEY.encode(),
        payload.encode(),
        hashlib.sha256
    ).hexdigest()
    # Simpan token session sederhana di tabel session (atau return langsung)
    # Untuk demo kita simpan langsung ke field terakhir, tapi di produksi gunakan JWT
    return f"{user_id}.{token}"

def hash_password(plain: str) -> str:
    salt = os.urandom(16).hex()
    h    = hashlib.pbkdf2_hmac("sha256", plain.encode(), salt.encode(), 260000)
    return f"pbkdf2:{salt}:{h.hex()}"

def verify_password(plain: str, stored: str) -> bool:
    if stored.startswith("pbkdf2:"):
        _, salt, hexdigest = stored.split(":", 2)
        h = hashlib.pbkdf2_hmac("sha256", plain.encode(), salt.encode(), 260000)
        return h.hex() == hexdigest
    # Legacy bcrypt hashes dari seed (nilai '$2b$…')
    try:
        import bcrypt
        return bcrypt.checkpw(plain.encode(), stored.encode())
    except Exception:
        return False

def require_auth(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        token = request.headers.get("Authorization", "").replace("Bearer ", "")
        if not token or "." not in token:
            return jsonify({"error": "Autentikasi diperlukan"}), 401
        user_id = token.split(".")[0]
        user = query("SELECT * FROM users WHERE id=? AND is_active=1", (user_id,), one=True)
        if not user:
            return jsonify({"error": "Token tidak valid"}), 401
        g.current_user = dict(user)
        return f(*args, **kwargs)
    return decorated

def row_to_dict(row):
    if row is None:
        return None
    return dict(row)

def rows_to_list(rows):
    return [dict(r) for r in rows]


# ─────────────────────────────────────────────────────────────
#  ROUTES
# ─────────────────────────────────────────────────────────────

# ══ AUTH ══════════════════════════════════════════════════════

@app.route("/api/auth/register", methods=["POST"])
def register():
    d = request.get_json() or {}
    required = ["nama_depan", "nama_belakang", "email", "password"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400

    if query("SELECT id FROM users WHERE email=?", (d["email"],), one=True):
        return jsonify({"error": "Email sudah terdaftar"}), 409

    user_id = mutate(
        """INSERT INTO users (nama_depan, nama_belakang, email, password_hash, kota, usia, keahlian, ketersediaan)
           VALUES (?,?,?,?,?,?,?,?)""",
        (d["nama_depan"], d["nama_belakang"], d["email"],
         hash_password(d["password"]),
         d.get("kota"), d.get("usia"), d.get("keahlian"), d.get("ketersediaan"))
    )
    # Simpan minat
    for minat in (d.get("minat") or []):
        try:
            mutate("INSERT OR IGNORE INTO relawan_minat (user_id, minat) VALUES (?,?)", (user_id, minat))
        except Exception:
            pass

    token = make_token(user_id)
    user  = row_to_dict(query("SELECT id,nama_depan,nama_belakang,email,kota,poin,jam_kontribusi FROM users WHERE id=?", (user_id,), one=True))
    return jsonify({"token": token, "user": user}), 201


@app.route("/api/auth/login", methods=["POST"])
def login():
    d = request.get_json() or {}
    user = query("SELECT * FROM users WHERE email=? AND is_active=1", (d.get("email",""),), one=True)
    if not user or not verify_password(d.get("password",""), user["password_hash"]):
        return jsonify({"error": "Email atau password salah"}), 401
    token = make_token(user["id"])
    u = dict(user)
    u.pop("password_hash", None)
    return jsonify({"token": token, "user": u})


@app.route("/api/auth/me", methods=["GET"])
@require_auth
def me():
    u = g.current_user.copy()
    u.pop("password_hash", None)
    minat = rows_to_list(query("SELECT minat FROM relawan_minat WHERE user_id=?", (u["id"],)))
    u["minat"] = [m["minat"] for m in minat]
    return jsonify(u)


# ══ USERS / RELAWAN ═══════════════════════════════════════════

@app.route("/api/relawan/daftar", methods=["POST"])
@require_auth
def daftar_relawan():
    d = request.get_json() or {}
    uid = g.current_user["id"]
    mutate("UPDATE users SET is_relawan=1, kota=?, usia=?, keahlian=?, ketersediaan=?, motivasi=? WHERE id=?",
           (d.get("kota"), d.get("usia"), d.get("keahlian"), d.get("ketersediaan"), d.get("motivasi"), uid))
    for minat in (d.get("minat") or []):
        try:
            mutate("INSERT OR IGNORE INTO relawan_minat (user_id, minat) VALUES (?,?)", (uid, minat))
        except Exception:
            pass
    # Log aktivitas
    mutate("INSERT INTO aktivitas_log (tipe, deskripsi, user_id) VALUES (?,?,?)",
           ("daftar_relawan", f"{g.current_user['nama_depan']} mendaftar sebagai relawan", uid))
    return jsonify({"message": "Pendaftaran relawan berhasil!"})


@app.route("/api/relawan/leaderboard", methods=["GET"])
def leaderboard():
    rows = query("""
        SELECT id, nama_depan, nama_belakang, kota, poin, jam_kontribusi,
               avatar_initials
        FROM users WHERE is_relawan=1 AND is_active=1
        ORDER BY poin DESC LIMIT 10
    """)
    return jsonify(rows_to_list(rows))


# ══ ORGANISASI ════════════════════════════════════════════════

@app.route("/api/organisasi", methods=["GET"])
def list_organisasi():
    kategori = request.args.get("kategori")
    kota     = request.args.get("kota")
    sql = "SELECT * FROM organisasi WHERE status='aktif'"
    params = []
    if kategori:
        sql += " AND kategori=?"; params.append(kategori)
    if kota:
        sql += " AND kota=?"; params.append(kota)
    sql += " ORDER BY anggota_count DESC"
    return jsonify(rows_to_list(query(sql, params)))


@app.route("/api/organisasi/<int:oid>", methods=["GET"])
def get_organisasi(oid):
    org = row_to_dict(query("SELECT * FROM organisasi WHERE id=?", (oid,), one=True))
    if not org:
        return jsonify({"error": "Organisasi tidak ditemukan"}), 404
    anggota = rows_to_list(query("""
        SELECT u.id, u.nama_depan, u.nama_belakang, u.avatar_initials, oa.peran, oa.joined_at
        FROM org_anggota oa JOIN users u ON oa.user_id=u.id
        WHERE oa.org_id=? ORDER BY oa.joined_at
    """, (oid,)))
    org["anggota"] = anggota
    return jsonify(org)


@app.route("/api/organisasi", methods=["POST"])
@require_auth
def buat_organisasi():
    d   = request.get_json() or {}
    uid = g.current_user["id"]
    required = ["nama", "deskripsi", "kategori", "kota"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400
    oid = mutate("""
        INSERT INTO organisasi (nama, deskripsi, kategori, kota, logo_emoji, website, email_kontak, founder_id)
        VALUES (?,?,?,?,?,?,?,?)
    """, (d["nama"], d["deskripsi"], d["kategori"], d["kota"],
          d.get("logo_emoji","🏛️"), d.get("website"), d.get("email_kontak"), uid))
    # Founder otomatis jadi anggota
    mutate("INSERT INTO org_anggota (org_id, user_id, peran) VALUES (?,?,'founder')", (oid, uid))
    mutate("UPDATE organisasi SET anggota_count=1 WHERE id=?", (oid,))
    mutate("INSERT INTO aktivitas_log (tipe, deskripsi, user_id, ref_id) VALUES (?,?,?,?)",
           ("daftar_org", f"Organisasi '{d['nama']}' baru terdaftar dari {d['kota']}", uid, oid))
    return jsonify({"id": oid, "message": "Pengajuan organisasi dikirim!"}), 201


@app.route("/api/organisasi/<int:oid>/gabung", methods=["POST"])
@require_auth
def gabung_organisasi(oid):
    uid = g.current_user["id"]
    existing = query("SELECT id FROM org_anggota WHERE org_id=? AND user_id=?", (oid, uid), one=True)
    if existing:
        return jsonify({"error": "Sudah menjadi anggota"}), 409
    mutate("INSERT INTO org_anggota (org_id, user_id, peran) VALUES (?,?,'anggota')", (oid, uid))
    mutate("UPDATE organisasi SET anggota_count = anggota_count + 1 WHERE id=?", (oid,))
    return jsonify({"message": "Berhasil bergabung!"})


# ══ KEGIATAN ══════════════════════════════════════════════════

@app.route("/api/kegiatan", methods=["GET"])
def list_kegiatan():
    status   = request.args.get("status", "upcoming")
    kategori = request.args.get("kategori")
    kota     = request.args.get("kota")
    q        = request.args.get("q")
    sql = """
        SELECT k.*, u.nama_depan || ' ' || u.nama_belakang AS creator_name,
               o.nama AS org_nama
        FROM kegiatan k
        LEFT JOIN users u ON k.creator_id = u.id
        LEFT JOIN organisasi o ON k.org_id = o.id
        WHERE k.status = ?
    """
    params = [status]
    if kategori:
        sql += " AND k.kategori=?"; params.append(kategori)
    if kota:
        sql += " AND k.kota=?"; params.append(kota)
    if q:
        sql += " AND (k.judul LIKE ? OR k.deskripsi LIKE ?)";
        params += [f"%{q}%", f"%{q}%"]
    sql += " ORDER BY k.tanggal_mulai ASC"
    return jsonify(rows_to_list(query(sql, params)))


@app.route("/api/kegiatan/<int:kid>", methods=["GET"])
def get_kegiatan(kid):
    row = query("""
        SELECT k.*, u.nama_depan || ' ' || u.nama_belakang AS creator_name,
               o.nama AS org_nama
        FROM kegiatan k
        LEFT JOIN users u ON k.creator_id = u.id
        LEFT JOIN organisasi o ON k.org_id = o.id
        WHERE k.id=?
    """, (kid,), one=True)
    if not row:
        return jsonify({"error": "Kegiatan tidak ditemukan"}), 404
    return jsonify(row_to_dict(row))


@app.route("/api/kegiatan", methods=["POST"])
@require_auth
def buat_kegiatan():
    d   = request.get_json() or {}
    uid = g.current_user["id"]
    required = ["judul", "deskripsi", "kategori", "kota", "tanggal_mulai"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400
    kid = mutate("""
        INSERT INTO kegiatan
          (judul, deskripsi, kategori, kota, lokasi_detail, emoji,
           tanggal_mulai, tanggal_selesai, waktu_mulai, kuota_max,
           org_id, creator_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    """, (d["judul"], d["deskripsi"], d["kategori"], d["kota"],
          d.get("lokasi_detail"), d.get("emoji","🌿"),
          d["tanggal_mulai"], d.get("tanggal_selesai"),
          d.get("waktu_mulai"), d.get("kuota_max"), d.get("org_id"), uid))
    return jsonify({"id": kid, "message": "Kegiatan berhasil dipublikasikan!"}), 201


@app.route("/api/kegiatan/<int:kid>/daftar", methods=["POST"])
@require_auth
def daftar_kegiatan(kid):
    uid = g.current_user["id"]
    kegiatan = query("SELECT * FROM kegiatan WHERE id=?", (kid,), one=True)
    if not kegiatan:
        return jsonify({"error": "Kegiatan tidak ditemukan"}), 404
    if kegiatan["status"] not in ("upcoming", "ongoing"):
        return jsonify({"error": "Kegiatan tidak menerima pendaftar baru"}), 400
    if kegiatan["kuota_max"] and kegiatan["peserta_count"] >= kegiatan["kuota_max"]:
        return jsonify({"error": "Kuota peserta penuh"}), 409

    existing = query("SELECT id FROM kegiatan_peserta WHERE kegiatan_id=? AND user_id=?", (kid, uid), one=True)
    if existing:
        return jsonify({"error": "Sudah terdaftar"}), 409

    mutate("INSERT INTO kegiatan_peserta (kegiatan_id, user_id) VALUES (?,?)", (kid, uid))
    mutate("UPDATE kegiatan SET peserta_count = peserta_count + 1 WHERE id=?", (kid,))
    mutate("INSERT INTO aktivitas_log (tipe, deskripsi, user_id, ref_id) VALUES (?,?,?,?)",
           ("join_kegiatan",
            f"{g.current_user['nama_depan']} bergabung kegiatan {kegiatan['judul']}",
            uid, kid))
    return jsonify({"message": "Pendaftaran kegiatan berhasil! Cek email konfirmasi ✓"})


# ══ DONASI ════════════════════════════════════════════════════

@app.route("/api/donasi/program", methods=["GET"])
def list_program_donasi():
    kategori = request.args.get("kategori")
    sql = """
        SELECT dp.*, o.nama AS org_nama
        FROM donasi_program dp
        LEFT JOIN organisasi o ON dp.org_id = o.id
        WHERE dp.is_active = 1
    """
    params = []
    if kategori:
        sql += " AND dp.kategori=?"; params.append(kategori)
    sql += " ORDER BY dp.collected_amount DESC"
    return jsonify(rows_to_list(query(sql, params)))


@app.route("/api/donasi/program/<int:pid>", methods=["GET"])
def get_program_donasi(pid):
    row = query("""
        SELECT dp.*, o.nama AS org_nama
        FROM donasi_program dp
        LEFT JOIN organisasi o ON dp.org_id = o.id
        WHERE dp.id=?
    """, (pid,), one=True)
    if not row:
        return jsonify({"error": "Program tidak ditemukan"}), 404
    return jsonify(row_to_dict(row))


@app.route("/api/donasi/program", methods=["POST"])
@require_auth
def buat_program_donasi():
    d   = request.get_json() or {}
    uid = g.current_user["id"]
    required = ["judul", "deskripsi", "kategori", "target_amount"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400
    pid = mutate("""
        INSERT INTO donasi_program
          (judul, deskripsi, kategori, target_amount, penerima_manfaat, deadline, org_id, creator_id)
        VALUES (?,?,?,?,?,?,?,?)
    """, (d["judul"], d["deskripsi"], d["kategori"], d["target_amount"],
          d.get("penerima_manfaat"), d.get("deadline"), d.get("org_id"), uid))
    return jsonify({"id": pid, "message": "Program donasi berhasil dibuat!"}), 201


@app.route("/api/donasi/transaksi", methods=["POST"])
def buat_transaksi_donasi():
    d   = request.get_json() or {}
    required = ["program_id", "jumlah", "metode_bayar"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400

    program = query("SELECT * FROM donasi_program WHERE id=? AND is_active=1", (d["program_id"],), one=True)
    if not program:
        return jsonify({"error": "Program donasi tidak ditemukan"}), 404

    kode = f"TXN-{uuid.uuid4().hex[:12].upper()}"
    uid  = None
    auth = request.headers.get("Authorization","").replace("Bearer ","")
    if auth and "." in auth:
        uid = auth.split(".")[0]
        user = query("SELECT id FROM users WHERE id=?", (uid,), one=True)
        if not user:
            uid = None

    tid = mutate("""
        INSERT INTO donasi_transaksi
          (program_id, user_id, jumlah, metode_bayar, is_anonim, nama_donatur, status_bayar, kode_transaksi)
        VALUES (?,?,?,?,?,?,?,?)
    """, (d["program_id"], uid, d["jumlah"], d["metode_bayar"],
          1 if d.get("is_anonim") else 0,
          d.get("nama_donatur"), "sukses", kode))

    # Update aggregasi program
    mutate("""UPDATE donasi_program
              SET collected_amount = collected_amount + ?,
                  donatur_count    = donatur_count + 1
              WHERE id=?""", (d["jumlah"], d["program_id"]))

    # Log aktivitas
    jumlah_fmt = f"Rp {int(d['jumlah']):,}".replace(",",".")
    nama = "Anonim" if d.get("is_anonim") else (d.get("nama_donatur") or "Seseorang")
    mutate("INSERT INTO aktivitas_log (tipe, deskripsi, user_id, ref_id) VALUES (?,?,?,?)",
           ("donasi", f"Donasi {jumlah_fmt} masuk ke {program['judul']}", uid, d["program_id"]))

    return jsonify({"id": tid, "kode_transaksi": kode, "message": f"Donasi {jumlah_fmt} berhasil! Terima kasih 💚"})


@app.route("/api/donasi/riwayat", methods=["GET"])
@require_auth
def riwayat_donasi():
    uid  = g.current_user["id"]
    rows = query("""
        SELECT dt.*, dp.judul AS program_judul
        FROM donasi_transaksi dt
        JOIN donasi_program dp ON dt.program_id = dp.id
        WHERE dt.user_id = ?
        ORDER BY dt.created_at DESC
    """, (uid,))
    return jsonify(rows_to_list(rows))


@app.route("/api/donasi/terbaru", methods=["GET"])
def donasi_terbaru():
    rows = query("""
        SELECT dt.jumlah, dt.is_anonim, dt.nama_donatur,
               dt.created_at,
               u.nama_depan || ' ' || SUBSTR(u.nama_belakang,1,1) || '.' AS user_name
        FROM donasi_transaksi dt
        LEFT JOIN users u ON dt.user_id = u.id
        WHERE dt.status_bayar = 'sukses'
        ORDER BY dt.created_at DESC LIMIT 10
    """)
    return jsonify(rows_to_list(rows))


# ══ ARTIKEL ═══════════════════════════════════════════════════

@app.route("/api/artikel", methods=["GET"])
def list_artikel():
    kategori = request.args.get("kategori")
    featured = request.args.get("featured")
    sql = """
        SELECT a.*, u.nama_depan || ' ' || u.nama_belakang AS author_name
        FROM artikel a JOIN users u ON a.author_id = u.id
        WHERE a.is_published = 1
    """
    params = []
    if kategori:
        sql += " AND a.kategori=?"; params.append(kategori)
    if featured:
        sql += " AND a.is_featured=1"
    sql += " ORDER BY a.published_at DESC"
    rows = query(sql, params)
    # Jangan kirim konten penuh di listing
    result = []
    for r in rows:
        d = dict(r)
        d.pop("konten", None)
        result.append(d)
    return jsonify(result)


@app.route("/api/artikel/<int:aid>", methods=["GET"])
def get_artikel(aid):
    row = query("""
        SELECT a.*, u.nama_depan || ' ' || u.nama_belakang AS author_name
        FROM artikel a JOIN users u ON a.author_id = u.id
        WHERE a.id=? AND a.is_published=1
    """, (aid,), one=True)
    if not row:
        return jsonify({"error": "Artikel tidak ditemukan"}), 404
    mutate("UPDATE artikel SET views = views + 1 WHERE id=?", (aid,))
    return jsonify(row_to_dict(row))


@app.route("/api/artikel", methods=["POST"])
@require_auth
def buat_artikel():
    d   = request.get_json() or {}
    uid = g.current_user["id"]
    required = ["judul", "konten", "kategori"]
    for f in required:
        if not d.get(f):
            return jsonify({"error": f"Field '{f}' wajib diisi"}), 400
    now = datetime.utcnow().isoformat()
    aid = mutate("""
        INSERT INTO artikel
          (judul, konten, ringkasan, kategori, emoji_thumb, menit_baca,
           author_id, is_published, published_at)
        VALUES (?,?,?,?,?,?,?,?,?)
    """, (d["judul"], d["konten"], d.get("ringkasan"),
          d["kategori"], d.get("emoji_thumb","📰"),
          d.get("menit_baca", 5), uid, 1, now))
    mutate("INSERT INTO aktivitas_log (tipe, deskripsi, user_id, ref_id) VALUES (?,?,?,?)",
           ("artikel_publish", f"Artikel baru: {d['judul']}", uid, aid))
    return jsonify({"id": aid, "message": "Artikel berhasil dipublikasikan!"}), 201


# ══ SERTIFIKAT ════════════════════════════════════════════════

@app.route("/api/sertifikat/milik-saya", methods=["GET"])
@require_auth
def sertifikat_saya():
    uid  = g.current_user["id"]
    rows = query("""
        SELECT s.*, k.judul AS kegiatan_judul
        FROM sertifikat s
        LEFT JOIN kegiatan k ON s.kegiatan_id = k.id
        WHERE s.user_id = ?
        ORDER BY s.issued_at DESC
    """, (uid,))
    return jsonify(rows_to_list(rows))


@app.route("/api/sertifikat/verifikasi/<kode>", methods=["GET"])
def verifikasi_sertifikat(kode):
    row = query("""
        SELECT s.*, u.nama_depan || ' ' || u.nama_belakang AS penerima,
               k.judul AS kegiatan_judul
        FROM sertifikat s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN kegiatan k ON s.kegiatan_id = k.id
        WHERE s.kode_verifikasi = ?
    """, (kode,), one=True)
    if not row:
        return jsonify({"valid": False, "error": "Kode tidak ditemukan"}), 404
    return jsonify({"valid": True, **row_to_dict(row)})


def terbitkan_sertifikat(user_id, tipe, judul, kegiatan_id=None, deskripsi=None):
    kode = f"HH-{uuid.uuid4().hex[:8].upper()}"
    return mutate("""
        INSERT INTO sertifikat (user_id, kegiatan_id, tipe, kode_verifikasi, judul_sertifikat, deskripsi)
        VALUES (?,?,?,?,?,?)
    """, (user_id, kegiatan_id, tipe, kode, judul, deskripsi))


# ══ DASHBOARD ═════════════════════════════════════════════════

@app.route("/api/dashboard/stats", methods=["GET"])
def dashboard_stats():
    total_relawan = query("SELECT COUNT(*) AS c FROM users WHERE is_relawan=1", one=True)["c"]
    total_kegiatan = query("SELECT COUNT(*) AS c FROM kegiatan WHERE status != 'cancelled'", one=True)["c"]
    total_organisasi = query("SELECT COUNT(*) AS c FROM organisasi WHERE status='aktif'", one=True)["c"]
    total_donasi_row = query("SELECT SUM(collected_amount) AS s FROM donasi_program WHERE is_active=1", one=True)
    total_donasi = total_donasi_row["s"] or 0

    return jsonify({
        "total_relawan":    total_relawan,
        "total_kegiatan":   total_kegiatan,
        "total_organisasi": total_organisasi,
        "total_donasi":     total_donasi,
    })


@app.route("/api/dashboard/saya", methods=["GET"])
@require_auth
def dashboard_saya():
    uid = g.current_user["id"]
    user = row_to_dict(query(
        "SELECT id,nama_depan,nama_belakang,kota,poin,jam_kontribusi,is_relawan,avatar_initials FROM users WHERE id=?",
        (uid,), one=True
    ))
    kegiatan_diikuti = query(
        "SELECT COUNT(*) AS c FROM kegiatan_peserta WHERE user_id=?", (uid,), one=True
    )["c"]
    donasi_total = query(
        "SELECT COALESCE(SUM(jumlah),0) AS s FROM donasi_transaksi WHERE user_id=? AND status_bayar='sukses'",
        (uid,), one=True
    )["s"]
    sertifikat_count = query(
        "SELECT COUNT(*) AS c FROM sertifikat WHERE user_id=?", (uid,), one=True
    )["c"]
    return jsonify({
        **user,
        "kegiatan_diikuti": kegiatan_diikuti,
        "donasi_total":     donasi_total,
        "sertifikat_count": sertifikat_count,
    })


# ══ INDEKS HARMONI ════════════════════════════════════════════

@app.route("/api/harmoni", methods=["GET"])
def harmoni_index():
    periode = request.args.get("periode")
    if periode:
        row = query("SELECT * FROM harmoni_index WHERE periode=?", (periode,), one=True)
    else:
        row = query("SELECT * FROM harmoni_index ORDER BY periode DESC LIMIT 1", one=True)
    if not row:
        return jsonify({"error": "Data tidak ditemukan"}), 404
    return jsonify(row_to_dict(row))


# ══ AKTIVITAS REAL-TIME ═══════════════════════════════════════

@app.route("/api/aktivitas", methods=["GET"])
def aktivitas_feed():
    limit = min(int(request.args.get("limit", 20)), 50)
    rows  = query("""
        SELECT al.*, u.nama_depan || ' ' || SUBSTR(u.nama_belakang,1,1) || '.' AS user_name
        FROM aktivitas_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT ?
    """, (limit,))
    return jsonify(rows_to_list(rows))


# ══ HEALTH ════════════════════════════════════════════════════

@app.route("/api/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "service": "HarmoniHub API",
        "version": "1.0.0",
        "timestamp": datetime.utcnow().isoformat() + "Z"
    })


# ─── MAIN ─────────────────────────────────────────────────────
if __name__ == "__main__":
    init_db()
    print("🌿 HarmoniHub server berjalan di http://localhost:5000")
    print("📋 Endpoint tersedia:")
    for rule in sorted(app.url_map.iter_rules(), key=lambda r: r.rule):
        methods = ",".join(m for m in rule.methods if m not in ("HEAD","OPTIONS"))
        print(f"   {methods:20s}  {rule.rule}")
    app.run(host="0.0.0.0", port=5000, debug=True)
