@extends("layouts.app") @section("title", "Logbook") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Logbook Bimbingan</h1> @auth @if (auth()->user()->isMahasiswa())
            <div class="flex flex-wrap gap-2"> <a href="{{ route("logbook.create") }}"
                    class="px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm">+ Logbook</a>
                <a href="{{ route("logbook.create-revisi") }}"
                    class="px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm">+ Entri
                    Revisi</a>
            </div>
        @endif @endauth
    </div> {{-- Filter kombinasi --}} <form method="GET" action="{{ route("logbook.index") }}"
        class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3 items-end">
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Status</label> <select name="status"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach (["draft" => "Draf", "submitted" => "Dikirim", "approved" => "Disetujui", "revisi" => "Revisi"] as $v => $l)
                    <option value="{{ $v }}" @selected(($filters["status"] ?? "") === $v)>{{ $l }}</option>
                @endforeach
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Jenis</label> <select name="jenis"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="logbook" @selected(($filters["jenis"] ?? "") === "logbook")>Logbook</option>
                <option value="revisi" @selected(($filters["jenis"] ?? "") === "revisi")>Revisi</option>
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Dari tanggal</label> <input type="date"
                name="date_from" value="{{ $filters["date_from"] ?? "" }}"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Sampai tanggal</label> <input type="date"
                name="date_to" value="{{ $filters["date_to"] ?? "" }}"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Kata kunci</label> <input type="text"
                name="keyword" value="{{ $filters["keyword"] ?? "" }}" placeholder="Topik / nama / isi"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div class="flex gap-2 w-full sm:w-auto"> <button
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-brand hover:bg-brand-hover text-white text-sm">Cari</button> <a
                href="{{ route("logbook.index") }}"
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm text-center">Reset</a>
        </div>
    </form>
    @if ($entries->isEmpty())
        <div class="px-4 py-6 rounded-lg bg-bg-surface border border-border text-text-secondary"> Belum ada entri yang
            cocok. </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        @if (!auth()->user()->isMahasiswa())
                            <th class="py-3 px-4">Mahasiswa</th>
                        @endif
                        <th class="py-3 px-4">Sesi</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4">Topik</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr class="border-b border-border hover:bg-bg-panel hover:bg-bg-hover/50">
                            @if (!auth()->user()->isMahasiswa())
                                <td class="py-3 px-4">{{ $entry->mahasiswaTa?->mahasiswa?->name }}</td>
                            @endif
                            <td class="py-3 px-4">{{ $entry->jenis === "revisi" ? "—" : $entry->sesi_ke }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ ucfirst($entry->jenis) }}</td>
                            <td class="py-3 px-4">{{ $entry->topik ?? "Revisi" }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $entry->tanggal_tampil?->format("d M Y") ?? "—" }}</td>
                            <td class="py-3 px-4">@include("partials.status-badge", ["status" => $entry->status])</td>
                            <td class="py-3 px-4"> <a href="{{ route("logbook.show", $entry) }}"
                                    class="text-brand hover:underline">Detail</a> </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-2">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
