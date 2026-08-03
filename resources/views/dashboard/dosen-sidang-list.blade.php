@extends("layouts.app") @section("title", "Riwayat Menguji") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Riwayat Menguji ({{ $sidangs->count() }})</h1>
        <div class="flex flex-wrap gap-2"> <a href="{{ route("dashboard.dosen.sidang-list.export") }}"
                class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">download</span> Export PDF
                (BKD)</a>
            <a href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">←
                Dashboard</a>
        </div>
    </div>
    @if ($sidangs->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            riwayat menguji. </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sidangs as $s)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">{{ $s->mahasiswa_name ?? $s->mahasiswaTa?->mahasiswa?->name }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ $s->jenisLabel() }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $s->tanggal?->format("d M") }}</td>
                            <td class="py-3 px-4"> <span
                                    class="badge {{ $s->hasil === "lulus" ? "badge-success" : "" }} {{ $s->hasil === "lulus_revisi" ? "badge-pending" : "" }} {{ $s->hasil === "mengulang" ? "badge-danger" : "" }}">
                                    {{ $s->hasilLabel() }} </span> </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
