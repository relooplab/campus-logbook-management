@extends("layouts.app") @section("title", "Review Massal Entri") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Review Massal Entri</h1> <a href="{{ route("logbook.index") }}"
            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Lihat sebagai
            Dosen</a>
    </div> {{-- Filter --}} <form method="GET" action="{{ route("admin.entries") }}"
        class="bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap gap-3 items-end">
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Status</label> <select name="status"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach (["draft" => "Draf", "submitted" => "Dikirim", "approved" => "Disetujui", "revisi" => "Revisi"] as $v => $l)
                    <option value="{{ $v }}" @selected(request("status") === $v)>{{ $l }}</option>
                @endforeach
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Jenis</label> <select name="jenis"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="logbook" @selected(request("jenis") === "logbook")>Logbook</option>
                <option value="revisi" @selected(request("jenis") === "revisi")>Revisi</option>
            </select> </div>
        <div class="w-full sm:w-auto"> <label class="block text-xs text-text-secondary mb-1">Kata kunci</label> <input type="text"
                name="keyword" value="{{ request("keyword") }}" placeholder="Topik / nama / isi"
                class="w-full sm:w-auto rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> </div>
        <div class="flex gap-2 w-full sm:w-auto"> <button
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Cari</button> <a
                href="{{ route("admin.entries") }}"
                class="flex-1 sm:flex-none px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm text-center">Reset</a>
        </div>
    </form> {{-- Daftar entri dengan checkbox + bulk action --}} <form method="POST" action="{{ route("admin.bulk") }}" id="bulk-form"> @csrf <input
            type="hidden" name="action" value="" id="bulk-action">
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4"><input type="checkbox" id="select-all" class="bg-bg-surface"></th>
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4">Sesi</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4">Topik</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr class="border-b border-border hover:bg-bg-panel hover:bg-bg-hover/50">
                            <td class="py-3 px-4"><input type="checkbox" name="ids[]" value="{{ $entry->id }}"
                                    class="row-check bg-bg-surface"></td>
                            <td class="py-3 px-4">{{ $entry->mahasiswaTa?->mahasiswa?->name }}</td>
                            <td class="py-3 px-4">{{ $entry->jenis === "revisi" ? "—" : $entry->sesi_ke }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ ucfirst($entry->jenis) }}</td>
                            <td class="py-3 px-4">{{ $entry->topik ?? "Revisi" }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $entry->tanggal_bimbingan?->format("d M Y") ?? "—" }}</td>
                            <td class="py-3 px-4">@include("partials.status-badge", ["status" => $entry->status])</td>
                            <td class="py-3 px-4"><a href="{{ route("logbook.show", $entry) }}"
                                    class="text-accent-teal hover:underline">Detail</a></td>
                    </tr> @empty <tr>
                            <td colspan="8" class="py-4 px-4 text-text-secondary">Tidak ada entri yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-2">{{ $entries->links() }}</div>
        <div class="mt-4 bg-bg-surface rounded-xl border border-border p-4 flex flex-wrap items-center gap-3"> <span
                class="text-sm text-text-secondary">Aksi massal untuk entri terpilih:</span> <button type="button"
                data-action="approve"
                class="bulk-btn px-3 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Setujui</button>
            <button type="button" data-action="revisi"
                class="bulk-btn px-3 py-2 rounded-md bg-status-pending hover:bg-status-pending/90 text-white text-sm">Tandai
                Revisi</button> <button type="button" data-action="delete"
                class="bulk-btn px-3 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Hapus</button>
        </div>
    </form>
</div>
@endsection @section("scripts")
<script>
    document.getElementById('select-all').addEventListener('change', function(e) {
        document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked);
    });
    document.querySelectorAll('.bulk-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var checked = document.querySelectorAll('.row-check:checked').length;
            if (!checked) {
                alert('Pilih minimal satu entri.');
                return;
            }
            var action = btn.dataset.action;
            if (action === 'delete' && !confirm('Hapus ' + checked + ' entri?')) return;
            document.getElementById('bulk-action').value = action;
            document.getElementById('bulk-form').submit();
        });
    });
</script>
@endsection
