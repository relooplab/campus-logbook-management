@extends("layouts.app") @section("title", "Data TA") @section("content")
<div class="space-y-4">
    <h1 class="text-xl font-bold">Data Tugas Akhir</h1> {{-- Search / filter --}} <form method="GET"
        action="{{ route("admin.tas") }}" class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3">
        <input type="text" name="keyword" value="{{ request("keyword") }}" placeholder="Judul TA"
            class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <select name="pembimbing"
            class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <option value="">Semua pembimbing</option>
            @foreach ($dosenList as $d)
                <option value="{{ $d->id }}" @selected((string) request("pembimbing") === (string) $d->id)>{{ $d->name }}</option>
            @endforeach
        </select> <button class="w-full sm:w-auto px-3 py-2 rounded-md bg-brand text-white text-sm">Cari</button>
    </form>
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <form method="POST" action="{{ route("admin.bulk") }}" class="p-0"> @csrf <input type="hidden"
                    name="action" value="assign_dosen">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-3 px-4"><input type="checkbox" id="select-all" class="bg-bg-surface"></th>
                            <th class="py-3 px-4">Mahasiswa</th>
                            <th class="py-3 px-4">Judul</th>
                            <th class="py-3 px-4">Pembimbing 1</th>
                            <th class="py-3 px-4 table-col-pembimbing2">Pembimbing 2</th>
                            <th class="py-3 px-4 table-col-penguji">Penguji 1</th>
                            <th class="py-3 px-4 table-col-penguji">Penguji 2</th>
                            <th class="py-3 px-4 table-col-target">Target</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tas as $ta)
                            <tr class="border-b border-border">
                                <td class="py-3 px-4"><input type="checkbox" name="ids[]" value="{{ $ta->id }}"
                                        class="row-check bg-bg-surface"></td>
                                <td class="py-3 px-4">{{ $ta->mahasiswa?->name }}</td>
                                <td class="py-3 px-4 max-w-[220px] truncate">{{ $ta->judul_ta }}</td>
                                <td class="py-3 px-4">{{ $ta->pembimbing1?->name ?? "—" }}</td>
                                <td class="py-3 px-4 table-col-pembimbing2">{{ $ta->pembimbing2?->name ?? "—" }}</td>
                                <td class="py-3 px-4 table-col-penguji">{{ $ta->penguji1?->name ?? "—" }}</td>
                                <td class="py-3 px-4 table-col-penguji">{{ $ta->penguji2?->name ?? "—" }}</td>
                                <td class="py-3 px-4 table-col-target">{{ $ta->target_sesi }}</td>
                                <td class="py-3 px-4"> <span
                                        class="badge {{ $ta->status_ta === "aktif" ? "badge-info" : "" }} {{ $ta->status_ta === "tamat" ? "badge-success" : "" }} {{ $ta->status_ta === "nonaktif" ? "badge-neutral" : "" }}">
                                        {{ ucfirst($ta->status_ta) }} </span> </td>
                                <td class="py-3 px-4"><a href="{{ route("admin.tas") }}#edit-{{ $ta->id }}"
                                        class="text-brand hover:underline text-xs">Edit</a></td>
                        </tr> @empty <tr>
                                <td colspan="10" class="py-4 px-4 text-text-secondary">Tidak ada data TA.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-3 flex flex-wrap items-center gap-2 border-t border-border"> <span
                        class="text-sm text-text-secondary">Aksi massal:</span> <select name="dosen_id"
                        class="rounded-md border border-border bg-bg-surface px-3 py-1.5 text-sm">
                        <option value="">Pilih dosen...</option>
                        @foreach ($dosenList as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select> <button class="px-3 py-2 rounded-md bg-brand text-white text-sm">Assign Pembimbing
                        1</button> </div>
            </form>
            <div class="p-3">{{ $tas->links() }}</div>
        </div>
        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Buat Data TA</h2>
            <form method="POST" action="{{ route("admin.tas.store") }}" class="space-y-3"> @csrf <select
                    name="user_id" required
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pilih mahasiswa (tanpa TA)...</option>
                    @foreach ($mahasiswaList as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->identifier }})</option>
                    @endforeach
                </select>
                <textarea name="judul_ta" required placeholder="Judul TA"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"></textarea> <select name="pembimbing_1_id"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pembimbing 1...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select> <select name="pembimbing_2_id"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pembimbing 2...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select> <select name="penguji_1_id"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Penguji 1...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select> <select name="penguji_2_id"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Penguji 2...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                    @endforeach
                </select> <input type="number" name="target_sesi" value="7" min="1"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <button
                    class="w-full px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Simpan</button>
            </form>
        </div>
    </div> {{-- Inline edit forms --}} @foreach ($tas as $ta)
        <div id="edit-{{ $ta->id }}" class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Edit &amp; Assign — {{ $ta->mahasiswa?->name }}</h2>
            <form method="POST" action="{{ route("admin.tas.update", $ta) }}" class="grid sm:grid-cols-2 gap-3">
                @csrf @method("PUT") <div class="sm:col-span-2">
                    <textarea name="judul_ta" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ $ta->judul_ta }}</textarea>
                </div> <select name="pembimbing_1_id"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pembimbing 1...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}" @selected($ta->pembimbing_1_id === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select> <select name="pembimbing_2_id"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Pembimbing 2...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}" @selected($ta->pembimbing_2_id === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select> <select name="penguji_1_id"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Penguji 1...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}" @selected($ta->penguji_1_id === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select> <select name="penguji_2_id"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">Penguji 2...</option>
                    @foreach ($dosenList as $d)
                        <option value="{{ $d->id }}" @selected($ta->penguji_2_id === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select> <input type="number" name="target_sesi" value="{{ $ta->target_sesi }}" min="1"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <select name="status_ta"
                    class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="aktif" @selected($ta->status_ta === "aktif")>Aktif</option>
                    <option value="tamat" @selected($ta->status_ta === "tamat")>Tamat</option>
                    <option value="nonaktif" @selected($ta->status_ta === "nonaktif")>Nonaktif</option>
                </select> <button class="px-4 py-2 rounded-md bg-brand text-white text-sm">Simpan
                    Perubahan</button> </form>
        </div>
    @endforeach
</div>
@endsection @section("scripts")
<script>
    document.getElementById('select-all').addEventListener('change', function(e) {
        document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked);
    });
</script>
@endsection
