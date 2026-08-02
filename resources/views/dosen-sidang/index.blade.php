@extends("layouts.app") @section("title", "Catat Sidang") @section("content")
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Riwayat Menguji ({{ $sidangs->count() }})</h1>
        <div class="flex flex-wrap gap-2"> <a href="{{ route("dashboard.dosen.sidang-list.export") }}"
                class="px-3 py-2 rounded-md bg-accent-blue hover:bg-accent-blue/90 text-white text-sm">⬇ Export PDF</a>
            <a href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">←
                Dashboard</a> </div>
    </div> {{-- Form catat sidang (dosen, bisa mahasiswa orang lain) --}} <div class="bg-bg-surface rounded-xl border border-border p-5">
        <h2 class="font-semibold mb-3">Catat Sidang / Pengujian</h2>
        <form method="POST" action="{{ route("dosen-sidang.store") }}" class="grid sm:grid-cols-2 gap-3"> @csrf <div>
                <label class="block text-xs text-text-secondary mb-1">Mahasiswa (dari bimbingan)</label> <select
                    name="mahasiswa_ta_id"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">— Pilih mahasiswa bimbingan / atau isi manual di bawah —</option>
                    @foreach ($bimbingan as $ta)
                        <option value="{{ $ta->id }}">{{ $ta->mahasiswa?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div> <label class="block text-xs text-text-secondary mb-1">Atau nama mahasiswa (di luar sistem)</label>
                <input type="text" name="mahasiswa_name" placeholder="Nama mahasiswa yang diuji"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            @error("mahasiswa")
                <p class="text-status-danger text-xs sm:col-span-2">{{ $message }}</p>
            @enderror <div> <label class="block text-xs text-text-secondary mb-1">Jenis</label> <select name="jenis"
                    required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="seminar_proposal">Seminar Proposal</option>
                    <option value="sidang_akhir">Sidang Akhir</option>
                </select> </div>
            <div> <label class="block text-xs text-text-secondary mb-1">Tanggal</label> <input type="date"
                    name="tanggal" required value="{{ now()->format("Y-m-d") }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
            <div> <label class="block text-xs text-text-secondary mb-1">Hasil</label> <select name="hasil"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">—</option>
                    <option value="lulus">Lulus</option>
                    <option value="lulus_revisi">Lulus + Revisi</option>
                    <option value="mengulang">Mengulang</option>
                </select> </div>
            <div class="sm:col-span-2">
                <p class="text-xs text-text-secondary mb-1">Pembimbing mahasiswa yang diuji (maks 3, opsional untuk
                    konteks)</p>
                <div class="grid sm:grid-cols-3 gap-2"> <input type="text" name="supervisor_1"
                        placeholder="Pembimbing 1"
                        class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                        name="supervisor_2" placeholder="Pembimbing 2"
                        class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <input type="text"
                        name="supervisor_3" placeholder="Pembimbing 3"
                        class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
            </div>
            <div class="sm:col-span-2"> <button
                    class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Simpan
                    Sidang</button> </div>
        </form>
    </div> {{-- Daftar riwayat --}} @if ($sidangs->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            riwayat menguji. </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4">Pembimbing (diuji)</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Hasil</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sidangs as $s)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">{{ $s->mahasiswa_name ?? $s->mahasiswaTa?->mahasiswa?->name }}</td>
                            <td class="py-3 px-4 text-xs">
                                {{ $s->supervisor_names ? implode(", ", $s->supervisor_names) : "—" }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ $s->jenisLabel() }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $s->tanggal?->format("d M Y") }}</td>
                            <td class="py-3 px-4"> <span
                                    class="badge {{ $s->hasil === "lulus" ? "badge-success" : "" }} {{ $s->hasil === "lulus_revisi" ? "badge-pending" : "" }} {{ $s->hasil === "mengulang" ? "badge-danger" : "" }}">
                                    {{ $s->hasilLabel() }} </span> </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route("dosen-sidang.destroy", $s) }}"
                                    onsubmit="return confirm('Hapus?')"> @csrf @method("DELETE") <button
                                        class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
