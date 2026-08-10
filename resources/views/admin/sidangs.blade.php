@extends("layouts.app") @section("title", "Data Sidang") @section("content")
<div class="space-y-4">
    <h1 class="text-xl font-bold">Data Sidang</h1>
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4">Penguji</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Hasil</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sidangs as $s)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">{{ $s->mahasiswaTa?->mahasiswa?->name }}</td>
                            <td class="py-3 px-4">{{ $s->penguji?->name }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ $s->jenisLabel() }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $s->tanggal?->format("d M Y") }}</td>
                            <td class="py-3 px-4"> <span
                                    class="badge {{ $s->hasil === "lulus" ? "badge-success" : "" }} {{ $s->hasil === "lulus_revisi" ? "badge-pending" : "" }} {{ $s->hasil === "mengulang" ? "badge-danger" : "" }}">
                                    {{ $s->hasilLabel() }} </span> </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route("admin.sidangs.destroy", $s) }}"
                                    onsubmit="return confirm('Hapus data sidang?')"> @csrf @method("DELETE") <button
                                        class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                            </td>
                    </tr> @empty <tr>
                            <td colspan="6" class="py-4 px-4 text-text-secondary">Belum ada data sidang.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $sidangs->links() }}</div>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Tambah Data Sidang</h2>
            <form method="POST" action="{{ route("admin.sidangs.store") }}" class="space-y-3"> @csrf <select
                    name="mahasiswa_ta_id" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pilih mahasiswa...</option>
                    @foreach ($mahasiswaList as $m)
                        <option value="{{ $m->id }}">{{ $m->mahasiswa?->name }}</option>
                    @endforeach
                </select> <select name="penguji_id" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pilih dosen penguji...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select> <select name="jenis" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="seminar_proposal">Seminar Proposal</option>
                    <option value="sidang_akhir">Sidang Akhir</option>
                </select> <input type="date" name="tanggal" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <select
                    name="hasil" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Hasil (opsional)</option>
                    <option value="lulus">Lulus</option>
                    <option value="lulus_revisi">Lulus + Revisi</option>
                    <option value="mengulang">Mengulang</option>
                </select> <button
                    class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Simpan</button>
                <p class="text-xs text-text-secondary">Sidang Akhir dengan hasil Lulus/Lulus+Revisi otomatis menandai
                    mahasiswa <b>tamat</b>.</p>
            </form>
        </div>
    </div>
</div>
@endsection
