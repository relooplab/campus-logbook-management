<x-mail::message>
# Halo {{ $namaDosen }}

Mahasiswa **{{ $namaMahasiswa }}** telah mengirim bahan **{{ $jenisLabel }}** beserta dokumen pendukungnya. Mohon untuk direview sebelum jadwal dimulai.

<x-mail::panel>
**Detail Jadwal**

- **Jenis:** {{ $jenisLabel }}
- **Mahasiswa:** {{ $namaMahasiswa }}
- **Tanggal:** {{ $tanggal }}
- **Waktu:** {{ $waktuMulai }} – {{ $waktuSelesai }} ({{ $durasiMenit }} menit)
- **Lokasi:** {{ $lokasi }}
- **Diundang:** {{ $diundang }}
@if ($catatan)
- **Catatan:** {{ $catatan }}
@endif
</x-mail::panel>

<x-mail::button :url="$urlUndangan">
📄 Surat Undangan — {{ $namaFileUndangan }}
</x-mail::button>

@if ($urlMateri)
<x-mail::button :url="$urlMateri">
📄 Materi — {{ $namaFileMateri }}
</x-mail::button>
@endif

<x-mail::button :url="$urlDetail">
Lihat Detail Bahan
</x-mail::button>

Lampiran **.ics** memuat jadwal di atas — klik untuk menambahkannya ke kalender Anda.

<x-slot:subcopy>
Jika tombol di atas tidak berfungsi, salin tautan berikut ke browser Anda:

**Surat Undangan:** <span class="break-all">{{ $urlUndangan }}</span>

@if ($urlMateri)
**Materi:** <span class="break-all">{{ $urlMateri }}</span>
@endif

**Detail Bahan:** <span class="break-all">{{ $urlDetail }}</span>
</x-slot:subcopy>
</x-mail::message>