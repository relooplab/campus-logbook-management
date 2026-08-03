# Panduan Pengguna — Thesis Logbook Management

Selamat datang di **Thesis Logbook Management**, aplikasi pencatatan dan monitoring bimbingan Tugas Akhir (TA) mahasiswa. Panduan ini menjelaskan seluruh fitur aplikasi serta alur kerja (mekanisme) untuk setiap peran pengguna.

---

## Daftar Isi

1. [Tentang Aplikasi](#1-tentang-aplikasi)
2. [Peran Pengguna](#2-peran-pengguna)
3. [Fitur Detail Keseluruhan](#3-fitur-detail-keseluruhan)
4. [Alur Kerja Per Peran](#4-alur-kerja-per-peran)
5. [Tanya Jawab (FAQ)](#5-tanya-jawab-faq)

---

## 1. Tentang Aplikasi

Aplikasi ini membantu dosen pembimbing/penguji dan mahasiswa memantau proses bimbingan Tugas Akhir secara digital. Beberapa hal utama yang didukung:

- **Pencatatan logbook bimbingan** — mahasiswa mencatat setiap sesi bimbingan beserta progres dan kendala.
- **Alur review** — dosen menyetujui (approve) atau meminta revisi atas entri logbook.
- **Revisi berkelanjutan** — perbaikan hasil review dicatat sebagai entri revisi tersendiri.
- **Workspace file** — mahasiswa menyimpan dan membagikan dokumen TA (Bab, revisi, dll) secara terpusat.
- **Chat & komunikasi** — percakapan langsung antara dosen dan mahasiswa.
- **Pengumuman** — pengumuman resmi ke seluruh mahasiswa.
- **Catatan sidang & riwayat menguji** — dosen mencatat hasil sidang/pengujian.
- **Notifikasi & pelacakan kesehatan bimbingan** — indikator keteraturan bimbingan.

### Mode Aplikasi

Aplikasi berjalan dalam salah satu dari dua mode (dikonfigurasi oleh administrator):

| Mode | Deskripsi |
|---|---|
| 🌱 **Individual** (default) | Digunakan oleh **satu dosen** yang mencatat bimbingan dan pengujian mahasiswanya sendiri. Mahasiswa dapat mendaftar lalu disetujui dosen. |
| 🏛️ **Institusi** | Digunakan oleh program studi/institusi yang mengelola **banyak dosen**, mahasiswa, pembimbing, penguji, sidang, dan laporan resmi. |

Indikator mode ditampilkan di bagian atas menu samping (sidebar), misalnya label **"Individual"** atau **"Institusi"**.

---

## 2. Peran Pengguna

| Peran | Deskripsi |
|---|---|
| **Admin** | Mengelola pengguna, data TA, review massal, data sidang, dan pengaturan institusi. |
| **Dosen** | Membimbing dan menguji mahasiswa, me-review logbook, mencatat sidang, menyetujui registrasi mahasiswa. |
| **Mahasiswa** | Mencatat logbook bimbingan, mengunggah revisi, mengelola workspace, dan memantau progres TA. |

---

## 3. Fitur Detail Keseluruhan

Berikut rincian fitur yang tersedia di aplikasi, terlepas dari peran (ketersediaan per peran dijelaskan di bagian 4).

### 3.1 Autentikasi

- **Login** — Masuk dengan email dan kata sandi.
- **Registrasi mandiri mahasiswa** — Mahasiswa dapat mendaftar sendiri (nama, email, kata sandi). Akun berstatus **pending** hingga disetujui dosen.
  - Tersedia opsi **"Saya juga penguji"** — jika dicentang, mahasiswa dapat mencatat sidang mahasiswa lain setelah disetujui.
- **Lupa kata sandi** — Reset kata sandi melalui email.
- **Keluar** — Tombol keluar tersedia di menu profil.

### 3.2 Dashboard

Setiap peran memiliki dashboard ringkas:

- **Admin**: statistik mahasiswa, dosen, data TA, dan entri menunggu review; import mahasiswa via Excel; daftar data TA terbaru.
- **Dosen**: statistik total bimbingan, sedang progres, tamat, diuji, dan menunggu review; antrean review; health indicator; manajemen fase TA.
- **Mahasiswa**: pengumuman belum dibaca, status kesehatan bimbingan, milestone journey (fase), judul & pembimbing TA, progres bimbingan, achievement, statistik & streak, heatmap aktivitas 12 bulan, dan timeline bimbingan.

### 3.3 Logbook Bimbingan

Entri logbook mencatat satu sesi bimbingan.

- **Status entri**: `Draf` → `Dikirim` → `Disetujui` atau `Revisi`.
- **Jenis entri**: `Logbook` (sesi bimbingan) dan `Revisi` (perbaikan hasil review).
- **Batas ronde revisi**: maksimal 3 ronde per entri (peringatan muncul bila tercapai).
- **Auto-save draf**: isian logbook tersimpan otomatis di browser (localStorage) setiap 5 detik.
- **Filter**: status, jenis, rentang tanggal, dan kata kunci.

### 3.4 Review PDF & Anotasi

- Dokumen lampiran (PDF) dapat dibuka dalam **viewer PDF**.
- Dosen dapat **menandai area** pada PDF dan **memberi komentar** (anotasi) per halaman.
- Komentar memiliki status **terbuka/resolved**.
- Dosen dapat **membangun feedback otomatis** dari komentar yang belum di-resolve.

### 3.5 Workspace File

- Mahasiswa dapat **mengunggah file** (PDF, DOC, DOCX, XLS, XLSX) — maks. 25 MB, hingga 5 file sekaligus, dengan *drag & drop*.
- File diberi **label Bab** (opsional) dan **catatan** (deskripsi).
- **Filter** berdasarkan Bab, tipe file, dan kata kunci.
- **Preview** untuk PDF, **download** untuk semua file.
- Mahasiswa dapat **mengedit metadata** (Bab, catatan) dan **menghapus** file.
- Indikator **penggunaan penyimpanan** (MB) ditampilkan.

### 3.6 Chat

- Percakapan langsung antara dosen dan mahasiswa.
- Dosen dapat memulai percakapan dari **halaman detail mahasiswa**.
- Tampilan daftar percakapan menampilkan lawan bicara, jumlah pesan belum dibaca, dan TA terkait.
- Pesan dapat **dilampirkan** ke entitas terkait (opsi lampiran).

### 3.7 Pengumuman

- **Pembuat pengumuman** (dosen/admin) dapat membuat pengumuman yang dikirim ke mahasiswa.
- **Mahasiswa** melihat pengumuman belum dibaca di dashboard dan dapat **menandai dibaca**.
- Pembuat dapat melihat **laporan** (jumlah terkirim, sudah baca) dan **mengingatkan** yang belum membaca.

### 3.8 Notifikasi

- Notifikasi masuk untuk aktivitas penting (entri baru, status berubah, komentar PDF, dll).
- **Lonjong notifikasi** di header menampilkan jumlah belum dibaca; tersedia **dropdown** dan **halaman semua notifikasi**.
- **Notifikasi real-time** (via Reverb/Pusher) menampilkan toast saat ada perubahan status entri atau komentar PDF.

### 3.9 Catatan Sidang & Riwayat Menguji

- Dosen mencatat **sidang** (Seminar Proposal / Sidang Akhir) untuk mahasiswa bimbingan **atau mahasiswa lain** (di luar sistem).
- Data dicatat: mahasiswa, jenis, tanggal, hasil (Lulus / Lulus + Revisi / Mengulang), dan nama pembimbing (maks 3).
- **Export PDF** riwayat menguji untuk keperluan portofolio/BKD.
- Sidang Akhir dengan hasil **Lulus/Lulus + Revisi** otomatis menandai mahasiswa **tamat**.

### 3.10 Jadwal Bimbingan

- Halaman **"Jadwalkan Bimbingan"** menampilkan daftar dosen beserta tautan jadwal bimbingan mereka.
- Klik kartu dosen untuk membuka tautan jadwal (di tab baru).

### 3.11 Pencarian Global (Cmd+K)

- Pencarian cepat di seluruh data (mahasiswa/dosen, entri, file workspace).
- Tekan `Cmd+K` (Mac) atau `Ctrl+K` (Windows/Linux) atau klik kolom pencarian di header.

### 3.12 Profil & Pengaturan

- Setiap pengguna dapat memperbarui **profil** (foto, kontak WhatsApp/Telegram/LinkedIn, dll) dan **kata sandi**.
- Halaman profil pengguna lain dapat dilihat (dihubungkan dari berbagai halaman).

### 3.13 Kesehatan Bimbingan (Health Indicator)

Keteraturan bimbingan dihitung dari tanggal bimbingan terakhir:

| Status | Arti |
|---|---|
| 🟢 **Sehat** | Bimbingan terakhir < 15 hari |
| 🟡 **Perhatian** | 15–40 hari |
| 🔴 **Kritis** | > 40 hari atau belum pernah bimbingan |

- Dosen melihat indikator ini di dashboard (dengan filter).
- Mahasiswa melihat status kesehatan bimbingannya sendiri.
- Mahasiswa yang lama tidak bimbingan dapat menerima **email inaktivitas** (ditandai ikon ⚠).

### 3.14 Milestone Journey & Achievement

- **Milestone Journey** menampilkan fase perjalanan TA mahasiswa: Proposal → Pengumpulan Data → Analisis → Seminar Hasil → Draft Sidang → Sidang → Achievement Unlocked.
- **Achievement** (badge) yang terbuka saat mahasiswa mencapai pencapaian tertentu, ditampilkan di dashboard mahasiswa.

### 3.15 Import & Export

- **Import mahasiswa (Excel)** — admin (institusi) dapat mengimpor data mahasiswa secara massal (nama, NIM, email, pembimbing1_nidn, pembimbing2_nidn) dengan pembimbing default.
- **Export rekap** — rekap bimbingan dalam **PDF** dan **Excel** per mahasiswa.
- **Export riwayat menguji** — PDF untuk dosen.

### 3.16 Manajemen Fase & Status TA

- **Fase** TA dapat diperbarui oleh dosen (dari dashboard atau halaman detail mahasiswa).
- **Status TA**: `Aktif`, `Tamat`, `Nonaktif` — dikelola admin.

---

## 4. Alur Kerja Per Peran

### 4.1 Alur Kerja Mahasiswa

#### 4.1.1 Registrasi & Menunggu Persetujuan

1. Buka halaman **Daftar**.
2. Isi **nama**, **email**, dan **kata sandi**.
3. (Opsional) Centang **"Saya juga penguji"** dan isi nama pembimbing (maks 3) jika ingin mencatat sidang mahasiswa lain.
4. Submit → akun dibuat berstatus **pending** (belum bisa login penuh).
5. Tunggu **persetujuan dosen**. Setelah disetujui, Anda dapat login dan data TA Anda tersedia.

#### 4.1.2 Mencatat Logbook Bimbingan

1. Masuk ke **Dashboard** → klik **+ Logbook** (atau menu **Tambah Logbook**).
2. Nomor **Sesi** terisi otomatis (sesi berikutnya).
3. Isi **Tanggal Bimbingan**, **Topik Bimbingan**, dan **Ringkasan Perbaikan** (progres & kendala).
4. (Opsional) Lampirkan file (PDF, dll).
5. Pilih:
   - **Simpan Draf** — menyimpan sebagai draf, dapat diedit dan dikirim nanti.
   - **Kirim ke dosen** — langsung mengirim ke dosen untuk direview.
6. Isian tersimpan otomatis (auto-save) setiap 5 detik; draf dipulihkan jika halaman tertutup.

#### 4.1.3 Menanggapi Review Dosen

1. Saat dosen meminta revisi, status entri menjadi **Revisi**.
2. Buka detail entri → klik **Buat Revisi dari Feedback Ini**.
3. Isi ringkasan perbaikan dan unggah revisi (bisa mengunggah **Catatan Perbaikan**).
4. Kirim ke dosen → dosen akan mereview kembali.

#### 4.1.4 Mengelola Workspace

1. Buka menu **Workspace**.
2. Klik zona unggah (atau tarik & lepas file) — maks. 5 file, 25 MB per file.
3. Isi label **Bab** (opsional) dan **Catatan**.
4. Klik **Upload** → file muncul di daftar, dikelompokkan per Bab.
5. Gunakan **filter** (Bab, tipe, kata kunci) atau **search** untuk menemukan file.
6. Klik ikon **edit** untuk mengubah metadata, atau **hapus** untuk menghapus file.

#### 4.1.5 Berkomunikasi & Memantau

- **Chat** dengan dosen pembimbing Anda.
- Baca **pengumuman** dan **notifikasi** secara berkala.
- Pantau **milestone journey**, **progres bimbingan**, dan **health indicator** Anda di dashboard.
- Unduh **rekap PDF/Excel** bimbingan Anda.

#### 4.1.6 (Jika Menjadi Penguji) Mencatat Sidang

Jika akun Anda diizinkan menjadi penguji, Anda dapat mencatat riwayat sidang/menguji mahasiswa lain melalui menu **Catat Sidang** (lihat alur dosen di 4.2.5).

---

### 4.2 Alur Kerja Dosen

#### 4.2.1 Menyetujui Registrasi Mahasiswa

1. Buka menu **Persetujuan** (di sidebar).
2. Lihat daftar mahasiswa yang menunggu persetujuan (status **Pending**).
3. Untuk menyetujui, isi:
   - **Judul TA**
   - **Peran Anda** untuk mahasiswa tersebut (Pembimbing 1 / Pembimbing 2 / Penguji 1 / Penguji 2)
   - **Target Sesi**
   - (Jika mahasiswa berstatus "ingin jadi penguji") centang **Izinkan menjadi penguji**
4. Klik **Setujui & Assign** → akun mahasiswa diaktifkan dan data TA dibuat.
5. Alternatif, klik **Tolak** untuk menolak registrasi.

#### 4.2.2 Me-review Logbook

1. Di **Dashboard**, lihat **Antrean Review** (jumlah entri menunggu review).
2. Klik **Review** pada entri, atau gunakan menu **Quick Review** untuk meninjau satu per satu.
3. Pada entri:
   - Buka **PDF & Anotasi** untuk membaca dokumen dan memberi komentar pada area PDF.
   - Pilih **Setujui (Approve)** untuk menyetujui, atau
   - Isi **feedback** (wajib, min. 20 karakter) lalu **Minta Revisi**.
4. Saat menyetujui dengan lampiran PDF yang belum dibuka, aplikasi mengonfirmasi terlebih dahulu.

#### 4.2.3 Quick Review

Mode cepat untuk meninjau antrean satu per satu:

1. Buka menu **Quick Review**.
2. Baca ringkasan entri, feedback sebelumnya, dan anotasi ronde sebelumnya.
3. Gunakan **feedback terakhir** untuk mahasiswa tersebut (klik untuk memakai) atau **template feedback**.
4. Klik **"Jadikan dari Komentar"** untuk membangun feedback dari komentar PDF yang belum di-resolve.
5. Pilih **Setujui & Next** atau **Revisi & Next** untuk lanjut ke entri berikutnya.
6. Simpan feedback sebagai **template** untuk dipakai ulang.

#### 4.2.4 Mengelola Fase & Health Bimbingan

1. Di dashboard, gunakan **Health Indicator Bimbingan** untuk memantau keteraturan bimbingan tiap mahasiswa (filter Sehat/Perhatian/Kritis).
2. Gunakan **Manajemen Fase TA** untuk memperbarui fase tiap mahasiswa (mis. dari Proposal ke Pengumpulan Data).
3. Mahasiswa yang tidak aktif bimbingan dapat ditandai (⚠) dan menerima email inaktivitas.

#### 4.2.5 Mencatat Sidang / Riwayat Menguji

1. Buka menu **Catat Sidang**.
2. Pilih mahasiswa dari daftar bimbingan, atau ketik **nama mahasiswa di luar sistem**.
3. Isi **Jenis** (Seminar Proposal / Sidang Akhir), **Tanggal**, dan **Hasil** (Lulus / Lulus + Revisi / Mengulang).
4. (Opsional) Isi nama pembimbing mahasiswa yang diuji (maks 3).
5. Klik **Simpan Sidang** → tercatat di riwayat menguji.
6. Gunakan **Export PDF** untuk mengunduh riwayat menguji.

#### 4.2.6 Berkomunikasi & Mengumumkan

- **Chat** dengan mahasiswa bimbingan (mulai dari halaman detail mahasiswa).
- Buat **pengumuman** dan pantau laporan pembacaannya; kirim pengingat ke yang belum membaca.
- Pantau **notifikasi** untuk entri baru dan komentar PDF.

#### 4.2.7 Deret Utama Alur Bimbingan (Ringkasan)

```
Mahasiswa mendaftar → Dosen menyetujui & assign
   → Mahasiswa mencatat logbook (draf)
   → Mahasiswa kirim ke dosen (submitted)
   → Dosen review: approve ATAU minta revisi
   → Jika revisi: Mahasiswa buat entri revisi → kirim lagi → dosen review
   → Setiap approve menambah sesi disetujui
   → Dosen monitor health & fase → hingga tamat
```

---

### 4.3 Alur Kerja Admin

#### 4.3.1 Mengelola Pengguna

1. Buka menu **Pengguna**.
2. **Cari/filter** pengguna berdasarkan nama, email, identifier, atau role.
3. **Tambah pengguna**: isi nama, email, identifier (NIM/NIDN), kata sandi, dan pilih role (admin/dosen/mahasiswa).
4. **Reset kata sandi** pengguna (klik **Reset PW**).
5. **Hapus** pengguna bila diperlukan.

#### 4.3.2 Mengelola Data TA

1. Buka menu **Data TA**.
2. **Buat data TA**: pilih mahasiswa (tanpa TA), isi judul, dan tentukan pembimbing 1/2, penguji 1/2, serta target sesi.
3. **Edit & assign** data TA per mahasiswa (judul, pembimbing, penguji, target sesi, status).
4. **Aksi massal**: centang beberapa baris → pilih dosen → **Assign Pembimbing 1** secara massal.
5. **Import mahasiswa (Excel)** dari dashboard: unggah file (nama, NIM, email, pembimbing1_nidn, pembimbing2_nidn) dan pilih pembimbing default.

#### 4.3.3 Review Massal Entri

1. Buka menu **Review Massal**.
2. **Filter** entri berdasarkan status, jenis, dan kata kunci.
3. Centang entri yang dipilih.
4. Pilih aksi massal: **Setujui**, **Tandai Revisi**, atau **Hapus**.

#### 4.3.4 Mengelola Data Sidang

1. Buka menu **Sidang**.
2. **Tambah data sidang**: pilih mahasiswa, dosen penguji, jenis, tanggal, dan hasil.
3. **Hapus** data sidang bila diperlukan.
4. Catatan: Sidang Akhir dengan hasil **Lulus/Lulus + Revisi** otomatis menandai mahasiswa **tamat**.

#### 4.3.5 Mengelola Profil Institusi

1. Buka menu **Institusi**.
2. Isi informasi institusi: nama aplikasi, nama institusi, fakultas, prodi, alamat, kota, telepon, email, website, catatan kaki dokumen, dan logo.
3. Atur **pengaturan upload & template**: link template catatan perbaikan, maks ukuran upload (MB), dan jenis file yang diizinkan.
4. Atur **pengaturan email (SMTP)**: mailer, host, port, enkripsi, username, password, dari-alamat, dan dari-nama.
5. **Kirim email uji** untuk memverifikasi konfigurasi SMTP.

#### 4.3.6 Memantau Dashboard

- Dashboard admin menampilkan statistik **mahasiswa, dosen, data TA**, dan **menunggu review**.
- Daftar **data TA terbaru** ditampilkan untuk pemantauan cepat.

---

## 5. Tanya Jawab (FAQ)

**Q: Apa arti error "419 Page Expired"?**
A: Error ini terjadi karena token keamanan (CSRF) sesi tidak valid atau kedaluwarsa. Solusi: muat ulang halaman login, dan pastikan halaman tidak dibiarkan terbuka terlalu lama. Jika terus terjadi, hubungi administrator untuk memeriksa konfigurasi sesi.

**Q: Saya mahasiswa, tetapi tidak bisa login setelah mendaftar.**
A: Akun Anda berstatus **pending** dan menunggu persetujuan dosen. Silakan hubungi dosen Anda untuk menyetujui akun.

**Q: Bagaimana cara mengubah atau mengirim draf logbook?**
A: Buka entri draf dari halaman **Logbook**, klik **Edit** untuk mengubah, lalu **Kirim ke dosen** untuk mengirim.

**Q: Saya dosen, bagaimana cara memberi komentar pada PDF?**
A: Buka entri (status **Dikirim**) → klik **Review PDF & Beri Anotasi** → buka PDF, tandai area, dan beri komentar. Komentar dapat dijadikan feedback otomatis.

**Q: Berapa batas ukuran file di workspace?**
A: Maksimal 25 MB per file, hingga 5 file sekaligus, dengan format PDF, DOC, DOCX, XLS, XLSX.

**Q: Bagaimana mahasiswa ditandai "tamat"?**
A: Otomatis saat dicatat **Sidang Akhir** dengan hasil **Lulus** atau **Lulus + Revisi**, atau diatur manual oleh admin.

**Q: Apa itu health indicator?**
A: Indikator keteraturan bimbingan: 🟢 Sehat (<15 hari), 🟡 Perhatian (15–40 hari), 🔴 Kritis (>40 hari). Membantu dosen dan mahasiswa memantau konsistensi bimbingan.

---

## Catatan

- Gunakan **Cmd+K / Ctrl+K** untuk pencarian global.
- Gunakan **tombol mode gelap/terang** di header untuk mengubah tampilan.
- Sidebar dapat diciutkan/lebarkan dengan tombol di sisi kiri.
- Untuk laporan masalah atau ide pengembangan, gunakan menu **"Kirim Masukan"**.