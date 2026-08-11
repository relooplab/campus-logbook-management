<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Dosen</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
        h2 { font-size: 12px; text-align: center; font-weight: normal; margin: 0 0 16px; color: #333; }
        h3 { font-size: 13px; margin: 20px 0 6px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #555; padding: 5px 6px; font-size: 11px; }
        table.data th { background: #eee; text-align: left; }
        .footer { margin-top: 24px; text-align: right; font-size: 11px; }
    </style>
</head>
<body>
    <h1>REKAP BIMBINGAN &amp; PENGUJIAN DOSEN</h1>
    <h2>{{ $dosen->name }} ({{ $dosen->nim }})</h2>

    <h3>A. Bimbingan Mahasiswa ({{ $bimbingan->count() }})</h3>
    <table class="data">
        <thead><tr><th>No</th><th>Mahasiswa</th><th>Judul TA</th><th>Status</th><th>Fase</th></tr></thead>
        <tbody>
            @forelse ($bimbingan as $i => $ta)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $ta->mahasiswa?->name }}</td>
                <td>{{ $ta->judul_ta }}</td>
                <td>{{ ucfirst($ta->status_ta) }}</td>
                <td>{{ $ta->faseLabel() }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center">Belum ada bimbingan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>B. Riwayat Pengujian ({{ $sidangs->count() }})</h3>
    <table class="data">
        <thead><tr><th>No</th><th>Mahasiswa</th><th>Jenis</th><th>Tanggal</th><th>Hasil</th></tr></thead>
        <tbody>
            @forelse ($sidangs as $i => $s)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $s->mahasiswaTa?->mahasiswa?->name }}</td>
                <td>{{ $s->jenisLabel() }}</td>
                <td>{{ $s->tanggal?->format('d/m/Y') }}</td>
                <td>{{ $s->hasilLabel() }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center">Belum ada riwayat menguji.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ now()->translatedFormat('d F Y') }}
    </div>
</body>
</html>
