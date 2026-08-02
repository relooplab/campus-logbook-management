<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Bimbingan TA</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
        h2 { font-size: 12px; text-align: center; font-weight: normal; margin: 0 0 16px; color: #333; }
        .header { border: 1px solid #111; padding: 10px; margin-bottom: 16px; }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { padding: 2px 4px; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.data th, table.data td { border: 1px solid #555; padding: 5px 6px; font-size: 11px; }
        table.data th { background: #eee; text-align: left; }
        .footer { margin-top: 24px; text-align: right; font-size: 11px; }
    </style>
</head>
<body>
    @php $inst = \App\Models\Institution::active(); @endphp
    <h1>{{ strtoupper($inst->institution_name) }}</h1>
    @if ($inst->faculty)<h2>{{ $inst->faculty }}{{ $inst->study_program ? ' — '.$inst->study_program : '' }}</h2>@endif
    <h2 style="margin-top:4px">REKAPITULASI BIMBINGAN TUGAS AKHIR</h2>

    <div class="header">
        <table>
            <tr><td style="width:180px"><strong>Nama Mahasiswa</strong></td><td>: {{ $mahasiswaTa->mahasiswa->name }} ({{ $mahasiswaTa->mahasiswa->identifier }})</td></tr>
            <tr><td><strong>Judul TA</strong></td><td>: {{ $mahasiswaTa->judul_ta }}</td></tr>
            <tr><td><strong>Pembimbing 1</strong></td><td>: {{ $mahasiswaTa->pembimbing1?->name ?? '—' }}</td></tr>
            <tr><td><strong>Pembimbing 2</strong></td><td>: {{ $mahasiswaTa->pembimbing2?->name ?? '—' }}</td></tr>
            <tr><td><strong>Target Sesi</strong></td><td>: {{ $target }}</td></tr>
            <tr><td><strong>Sesi Disetujui</strong></td><td>: {{ $approved }}</td></tr>
        </table>
    </div>

    <h3 style="margin:0 0 4px">Daftar Entri Bimbingan</h3>
    <table class="data">
        <thead>
            <tr>
                <th style="width:8%">No</th><th style="width:20%">Tanggal</th><th>Feedback</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $i => $e)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $e->tanggal_tampil?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $e->feedback_dosen ?: '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align:center">Belum ada entri.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $inst->city ? $inst->city.', ' : '' }}{{ now()->translatedFormat('d F Y') }}<br><br><br>
        <span>{{ $mahasiswaTa->pembimbing1?->name ?? '____________________' }}</span>
    </div>
    @if ($inst->footer_note)
        <p style="text-align:center; font-size:9px; color:#888; margin-top:20px">{{ $inst->footer_note }}</p>
    @endif
</body>
</html>
