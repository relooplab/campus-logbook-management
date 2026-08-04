# Spesifikasi Mode Aplikasi — Individual & Institusi

**Project:** Thesis Logbook Management
**Dokumen:** Desain mode penggunaan aplikasi (individual default / institusi)
**Status:** Proposal desain — acuan implementasi

---

## 1. Tujuan

Aplikasi dapat dipakai dalam **dua mode**:

- **Mode Individual (default)** — untuk **satu dosen** yang mencatat bimbingan **dan pengujian** mahasiswanya (termasuk menguji mahasiswa lain), tanpa struktur institusi/prodi. Mahasiswa dapat mendaftar sendiri (dengan opsi menjadi penguji) lalu disetujui dosen.
- **Mode Institusi** — untuk program studi / institusi yang mengelola **banyak dosen**, mahasiswa, pembimbing, penguji, sidang, dan laporan resmi.

Keduanya berbagi **satu codebase, satu database, satu deployment**. Perbedaan perilaku dikendalikan oleh **konfigurasi mode**, bukan dengan menyalin kode.

---

## 2. Prinsip Desain

1. **Single codebase, single DB, single deploy** — tidak ada dua branch/dua aplikasi.
2. **Multitenancy ringan** via kolom `institution_id` (nullable) + **Global Scope** Laravel, aktif hanya di mode institusi.
3. **Satu titik kontrol fitur** — kelas `Feature` membaca `config('app.mode')`; tidak ada `if mode` tersebar di banyak controller.
4. **Data pribadi bisa dibawa ke institusi** — mekanisme adopsi yang aman.
5. **Branding tetap ada di kedua mode** — mode individual memakai satu record `institutions` bawaan agar logika seragam.
6. **Individual = self-contained oleh satu dosen** — dosen bertindak sebagai pembimbing DAN penguji untuk mahasiswanya (semua peran dipegang satu orang). Fitur prodi (multi-dosen, bulk import, koordinator) yang dikunci di individual — bukan sidang/penguji.

---

## 3. Model Data

### 3.1 Kolom `institution_id` (nullable)

| Tabel | Kolom | Keterangan |
|---|---|---|
| `users` | `institution_id` (nullable) | Dosen/staff yang bergabung ke institusi. `NULL` = pemilik data pribadi (mode individual). |
| `mahasiswa_ta` | `institution_id` (nullable) | TA milik pribadi (`NULL`) atau milik institusi. |
| `sidangs` | `institution_id` (nullable) | Riwayat menguji — ada di kedua mode. |
| `institutions` | — (existing) | Satu record bawaan dipakai oleh mode individual. |

> Konvensi: **`institution_id = NULL` berarti "milik pemilik data" (individual)**. Saat data **diadopsi** ke institusi, kolom diisi `institution_id` dari institusi tujuan.

### 3.2 Konfigurasi mode

```env
# .env
APP_MODE=individual        # individual (default) | institution
```

Dibaca di `config/app.php`:

```php
'mode' => env('APP_MODE', 'individual'),
```

### 3.3 Kelas `Feature` (pintu cek fitur)

```php
// app/Support/Feature.php
class Feature
{
    public static function mode(): string
    {
        return config('app.mode');
    }

    public static function isInstitution(): bool
    {
        return self::mode() === 'institution';
    }

    /**
     * Fitur prodi (multi-dosen & manajemen institusi) hanya aktif di mode institusi.
     * Fitur "inti" (logbook, revisi, sidang, penguji, workspace) tersedia di KEDUA mode.
     */
    public static function has(string $feature): bool
    {
        $institutionOnly = ['bulk_import', 'koordinator', 'laporan_institusi', 'multi_dosen'];
        return in_array($feature, $institutionOnly, true)
            ? self::isInstitution()
            : true;
    }
}
```

Controller memanggil `Feature::has('bulk_import')` / `Feature::isInstitution()` — satu titik, mudah dirawat.

---

## 4. Perbandingan Fitur

| Fitur | 🌱 Individual (default) | 🏛️ Institusi |
|---|---|---|
| Mode / tenant | Implisit — semua milik **satu dosen** | Eksplisit via `institution_id` + Global Scope |
| Dosen berperan sebagai | **Pembimbing + Penguji** (satu orang, semua peran) | Bisa pembimbing/penguji per-role resmi |
| Pengguna | 1 dosen + mahasiswa pribadinya | Banyak dosen, admin prodi, koordinator, mahasiswa |
| Profil institusi | Satu record bawaan (`Perguruan Tinggi`) | Profil institusi resmi (nama, fakultas, prodi, logo, kontak) |
| Data TA | Hanya yang dibuat/register oleh dosen tsb | Terpusat lintas dosen di institusi |
| Registrasi mahasiswa | Mahasiswa **register sederhana** → dosen **approve** | Via admin / import / register + approve |
| Peran/roles | `dosen` + `mahasiswa` | `admin` prodi + `dosen` + `penguji` + `mahasiswa` (+ koordinator) |
| Pembimbing | ✅ Pembimbing 1/2 (dosen tsb) | Pembimbing 1/2 lintas dosen |
| **Penguji** | ✅ **Bisa jadi penguji** (dosen tsb) | ✅ Penguji 1/2 resmi |
| **Sidang / riwayat menguji** | ✅ **Ada** (riwayat menguji dosen tsb) | ✅ CRUD sidang, riwayat, BKD, export PDF |
| Status siklus TA (aktif/tamat/nonaktif) | ✅ Sederhana | ✅ Penuh + otomatis tamat saat sidang lulus |
| Dashboard dosen | Kartu statistik (bimbingan + pengujian) | Kartu + agregasi lintas dosen (koordinator) |
| Health indicator | Per mahasiswa sendiri | Per mahasiswa + agregat prodi |
| Weekly digest / notifikasi | Per dosen | Per dosen + digest institusi |
| Workspace file | Per mahasiswa | Per mahasiswa (tetap) + kontrol institusi |
| **Import mahasiswa (Excel)** | 🔒 (manual per-mahasiswa) | ✅ Bulk import + assign massal |
| Global search (Cmd+K) | Terbatas data sendiri | Mencakup seluruh institusi |
| Laporan/export | Rekap per mahasiswa + riwayat menguji | Rekap per mahasiswa + per dosen/institusi |
| **Bawa data pribadi ke institusi** | — | ✅ Command adopsi |
| Manajemen multi-dosen / koordinator | 🔒 | ✅ |

> 🔒 = fitur **prodi** (bulk import, koordinator, multi-dosen, laporan institusi) — tidak tersedia (atau ditampilkan "Tidak tersedia di mode individual") di mode individual.
>
> **Penting**: sidang, penguji, dan riwayat menguji **TIDAK dikunci** — tersedia di kedua mode, karena satu dosen individual bisa bertindak sebagai pembimbing maupun penguji.

---

## 5. Global Scope Tenant (mode institusi saja)

```php
// app/Models/Scopes/InstitutionScope.php
class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Feature::isInstitution()) {
            $builder->where($model->qualifyColumn('institution_id'), tenant()->id);
        }
        // Mode individual: tanpa filter (data pribadi pemilik).
    }
}
```

Dipakai di model yang punya `institution_id` (mis. `MahasiswaTa`). Di mode institusi, semua query otomatis hanya mengambil data institusi aktif.

---

## 5A. Alur Registrasi Mahasiswa & Attachment Dosen

Di mode individual, **mahasiswa dapat mendaftar sendiri** dengan form sederhana, **memverifikasi email**, lalu **memilih dosen** (pembimbing/penguji) yang akan menyetujui atau menolak permintaan attachment.

### 5A.1 Alur

```
1. Mahasiswa membuka halaman "Daftar" (register).
2. Form sederhana: NAMA, EMAIL, PASSWORD.
   → Akun dibuat ber-role `mahasiswa`, status ACTIVE (sudah verifikasi email, belum attach dosen).
3. Sistem mengirim email verifikasi; mahasiswa harus verifikasi sebelum login penuh.
4. Setelah login, mahasiswa melihat banner "Pilih Dosen untuk Memulai Program" di dashboard.
5. Mahasiswa membuka halaman "Pilih Dosen" (/profil/pilih-dosen):
   - pilih jenis program (TA/KP),
   - pilih Pembimbing 1 (wajib), Pembimbing 2, Penguji 1, Penguji 2 (opsional).
   → Sistem membuat `mahasiswa_ta` dengan status `pending_approval`.
6. Dosen yang dipilih menerima notifikasi & melihat daftar "Permintaan Menunggu" di /approval.
7. Dosen klik "Setujui & Assign":
   - pilih peran dosen terhadap TA-nya:
       • Pembimbing 1 / Pembimbing 2
       • Penguji 1 / Penguji 2
   - isi data TA (judul/tempat KP, target sesi) bila perlu.
   → `mahasiswa_ta` menjadi `aktif`, mahasiswa menjadi `verified`.
   Atau dosen klik "Tolak" → `mahasiswa_ta` menjadi `ditolak`, mahasiswa dapat memilih dosen lain.
8. Mahasiswa dapat memakai aplikasi; TA ter-assign ke dosen tsb.
```

### 5A.2 Skema data untuk status registrasi

```php
// users
$table->string('registration_status')->default('active');
// 'active'   → mahasiswa sudah verifikasi email, belum attach dosen
// 'verified' → mahasiswa sudah punya MahasiswaTa dengan dosen (disetujui)
// 'rejected' → ditolak (tidak bisa login)
// 'pending'  → khusus dosen: menunggu persetujuan admin
```

- `registration_status = 'active'` → mahasiswa bisa login, tetapi belum punya program/dosen.
- Dosen yang approve → set `verified` + `mahasiswa_ta.status_ta = aktif`.
- Mahasiswa yang ditolak → `rejected` (tidak bisa login).

### 5A.3 Form registrasi (individual)

- Hanya membutuhkan **nama**, **email** (wajib, validasi unik), **password**.
- Tidak perlu NIM/NIDN di awal (bisa dilengkapi nanti).
- Setelah submit → email verifikasi dikirim; mahasiswa diarahkan ke halaman verifikasi.
- **Dosen** yang mendaftar tetap berstatus `pending` dan menunggu persetujuan admin.

### 5A.4 Fase/Milestone saat memilih dosen

Saat mahasiswa memilih dosen, form **"Pilih Dosen"** juga menampilkan **dropdown Fase/Milestone** yang disesuaikan dengan jenis program (TA/KP). Mahasiswa memilih fase yang sedang dijalani, dan dosen dapat menyesuaikannya saat menyetujui permintaan attachment.

### 5A.5 Menetapkan peran (approve)

Saat dosen menyetujui permintaan attachment, dosen memilih bagaimana dirinya terhubung ke mahasiswa tsb:

| Pilihan | Efek pada `mahasiswa_ta` |
|---|---|
| Pembimbing 1 | `pembimbing_1_id = dosen` |
| Pembimbing 2 | `pembimbing_2_id = dosen` |
| Penguji 1 | `penguji_1_id = dosen` |
| Penguji 2 | `penguji_2_id = dosen` |

Karena di individual dosen memegang semua peran, dosen bisa menetapkan dirinya **sebagai pembimbing sekaligus penguji** (kolom berbeda di baris TA yang sama, atau dua entri sidang).

### 5A.6 Pembersihan mahasiswa tidak aktif

- Command `students:delete-inactive` (terjadwal harian 03:30) menghapus mahasiswa berstatus `active` yang **tidak memilih dosen dalam 1 bulan** (akun + data terkait dihapus bersih dalam transaksi).

### 5A.7 Kapan berlaku
- **Mode individual**: alur registrasi+attachment ini adalah **jalur utama** menambah mahasiswa.
- **Mode institusi**: registrasi juga bisa ada, tetapi biasanya lewat **admin/import**; approval dilakukan admin/koordinator.

---

## 5B. Sidang & Riwayat Menguji di Mode Individual

- Dosen individual tetap bisa mencatat **sidang** (seminar proposal / sidang akhir) dan menyimpan **riwayat menguji** (untuk pelaporan/portofolio).
- **Penguji bisa menguji mahasiswa ORANG LAIN** (di luar bimbingannya), bukan hanya mahasiswa bimbingan sendiri.
- `sidangs.penguji_id` = dosen tsb (atau mahasiswa yang diizinkan jadi penguji); `sidangs.institution_id` = NULL (milik pribadi).
- Fitur ini **tidak dikunci** — sama seperti institusi, hanya cakupannya terbatas pada data dosen itu sendiri.

### 5B.1 Siapa bisa jadi penguji di mode individual
1. **Dosen** — mencatat sidang mahasiswa mana pun (termasuk mahasiswa orang lain), untuk riwayat menguji/portofolio.

### 5B.2 Konteks sidang (mahasiswa lain)
Saat dosen mencatat sidang mahasiswa yang bukan bimbingannya, dosen perlu konteks siapa pembimbing mahasiswa tsb (mengisi manual, sesuai 5A.4): Pembimbing 1/2/3. Ini dipakai untuk laporan & identifikasi.

---

## 6. Alur "Bawa Data Pribadi ke Institusi"

### 6.1 Konsep
Saat dosen **bergabung/join** ke institusi, mahasiswa pribadinya (`institution_id = NULL` dan `pembimbing = dosen tsb`) dapat **diadopsi** ke institusi tersebut.

### 6.2 Command `ta:adopt-personal-data`

```bash
php artisan ta:adopt-personal-data --dosen=<user_id> --institution=<institution_id>
```

Logika (draft):

1. Temukan semua `mahasiswa_ta` dengan `institution_id = NULL` dan `pembimbing_1_id`/`pembimbing_2_id` = dosen.
2. Untuk setiap TA: set `institution_id = <institution_id>`.
3. Opsional: set `users.institution_id = <institution_id>` pada dosen & mahasiswa terkait.
4. Tulis **audit log** (jumlah data diadopsi, dosen, institusi, waktu).

### 6.3 Parameter adopsi (opsional)
- `--only=<id1,id2>` : adopsi hanya TA tertentu.
- `--dry-run` : tampilkan yang akan diadopsi tanpa mengubah.
- `--include-users` : ikut meng-update `users.institution_id`.

### 6.4 Keamanan
- Hanya dosen pemilik data yang bisa mengadopsi datanya sendiri.
- Data yang sudah `institution_id` terisi tidak akan tersentuh (kecuali `--force`).

---

## 7. Penjagaan (Safeguards)

1. **Global Scope hanya aktif di mode institusi** — mencegah individual tidak sengaja menyaring.
2. **Fitur prodi dikunci via `Feature::has()`** — UI menampilkan "Tidak tersedia di mode individual" alih-alih error.
3. **Adopsi satu arah & tercatat** — audit log + dry-run.
4. **Migration `institution_id` nullable** — tidak memaksa data lama.
5. **Seeder** menciptakan satu record `institutions` bawaan untuk mode individual.

---

## 8. Roadmap Implementasi (urutan)

| Fase | Isi | Verifikasi |
|---|---|---|
| **A** | Migration `institution_id` (users, mahasiswa_ta, sidangs) + `registration_status` + config `APP_MODE` + kelas `Feature` | `php artisan migrate`, `php -l` |
| **B** | Global Scope `InstitutionScope` di mode institusi | Query otomatis tersaring |
| **C** | **Registrasi mahasiswa + approve & assign peran** (mode individual): form sederhana, list pending, approve-set peran | Register → pending; approve → TA + peran terisi |
| **D** | Sidang & riwayat menguji berjalan di kedua mode (dosen bisa jadi penguji) | Catat sidang utk data pribadi |
| **E** | Command `ta:adopt-personal-data` (+ dry-run) | Dry-run tampil, run benar, audit log |
| **F** | Kunci fitur prodi (bulk_import/koordinator/multi_dosen) via `Feature::has()` di controller & view | Mode individual → fitur "Tidak tersedia" |
| **G** | UI: indikator mode, on-boarding join institusi | Tampil jelas mode aktif |
| **H** | Laporan/agregasi koordinator (mode institusi) | Rekap lintas dosen |

---

## 9. Catatan Keputusan

- **NULL = individual** dipertahankan sebagai konvensi sederhana.
- **Satu record `institutions` bawaan** untuk mode individual → branding tetap seragam dan Global Scope tidak perlu cabang khusus NULL.
- **`Feature` + Global Scope** = dua mekanisme komplementer: fitur-gate untuk UI/controller, scope untuk data isolation.
- **Sidang, penguji, & riwayat menguji TIDAK dikunci** — tersedia di kedua mode (individual = satu dosen memegang semua peran).
- **Fitur yang dikunci di individual** hanya yang bersifat *multi-dosen / manajemen institusi*: bulk import, koordinator, laporan institusi.
- Implementasi bertahap dari **A → H**; tidak perlu semua sekaligus.

---

## 10. Pertanyaan Terbuka (untuk dikonfirmasi)

1. Apakah mode bisa dipilih saat **install/setup**, atau harus **fixed per deployment**? (Rekomendasi: fixed per deployment via `APP_MODE`).
2. Apakah **dosen boleh join > 1 institusi**? (Rekomendasi: satu `institution_id` saat ini; multi-institusi = tabel pivot `user_institution`, lebih kompleks).
3. Apakah **mahasiswa pribadi** yang diadopsi tetap bisa dikelola dosen yang sama setelah pindah ke institusi? (Rekomendasi: ya, dosen tetap pembimbing).
4. Di mode individual, apakah **dosen perlu approve mahasiswa** (seperti skema 5A) atau cukup **dosen menambah mahasiswa langsung** (tanpa register mahasiswa)? **→ KONFIRMASI: keduanya.** Mahasiswa bisa register + dosen juga bisa tambah manual (lihat 5A).
5. Apakah **penguji di individual** berarti dosen tsb mencatat sidang mahasiswa ORANG LAIN (di luar bimbingannya), atau hanya mahasiswa bimbingannya sendiri? **→ KONFIRMASI: mahasiswa ORANG LAIN.** Dosen bisa mencatat sidang/riwayat menguji mahasiswa di luar bimbingannya (lihat 5B).

---

## 11. Direktori Organisasi & Grup Dosen (Penambahan)

### 11.1 Direktori Organisasi Hierarkis (4 level)

Untuk mendukung penggunaan oleh dosen dari berbagai perguruan tinggi di Indonesia, aplikasi memiliki **direktori organisasi hierarkis**:

```
universities (perguruan tinggi)
  └── faculties (fakultas)
        └── departments (departemen)
              └── study_programs (program studi)
```

- **Deduplikasi alami** via constraint unik — nama perguruan tinggi tidak muncul dua kali; fakultas/departemen/prodi unik di dalam induknya.
- **`user_university`** (pivot) — mendukung **multi-universitas**: satu dosen bisa terhubung ke banyak perguruan tinggi. `is_primary` menandai universitas utama.
- **`users.nidn`** — identitas dosen (unique).

### 11.2 Alur Registrasi & Afiliasi

- **Dosen** mendaftar dengan **NIDN** + memilih/membuat perguruan tinggi (dedup case-insensitive via `OrganizationalDirectoryService`).
- **Mahasiswa** yang di-invite/disetujui dosen **otomatis mengikuti institusi dosen** (universitas dosen disalin ke mahasiswa, tanpa input ulang).

### 11.3 Grup & Cross-link Dosen

- **`groups`** — grup di level universitas/fakultas/departemen/prodi.
- **`group_members`** — anggota grup dengan status `pending/approved/rejected` dan role `owner/member`.
- **Alur**: dosen membuat grup → mengundang dosen lain dari universitas yang sama → yang diundang **approve** → terhubung (cross-link).
- **Akses "hanya hubungan langsung"** — data hanya bisa diakses jika ada hubungan langsung (TA bersama, grup bersama, atau dosen-mahasiswa bimbingan). Diimplementasikan via `User::hasDirectRelation()` dan diperkuat di `LogbookEntryPolicy` & `MahasiswaTaPolicy`.

### 11.4 Workspace Dosen

- **`workspace_files.user_id`** (nullable) — mendukung workspace pribadi dosen (selain `mahasiswa_ta_id` untuk mahasiswa).
- Halaman `/workspace-saya` untuk mengelola file pribadi dosen.
