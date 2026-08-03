@extends("layouts.app") @section("title", "Entri Revisi") @section("content")
@php
    $inst = \App\Models\Institution::active();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(", ", $inst->allowedFileTypes()));
    $statusOptions = \App\Models\LogbookEntry::PERBAIKAN_STATUSES;
    $oldRiwayat = old("riwayat_perbaikan", []);
@endphp
<div class="max-w-3xl">
    <h1 class="text-xl font-bold mb-4">Entri Revisi</h1>
    @if ($parents->isEmpty())
        <div class="mb-4 px-4 py-3 rounded-md bg-status-pending/10 border border-status-pending/30 text-sm">
            Anda bisa membuat entri revisi langsung tanpa harus ada logbook sebelumnya. Jika ingin menjawab feedback dari entri yang sudah ada, pilih entri asal di bawah.
        </div>
    @endif
    <form method="POST" action="{{ route("logbook.store-revisi") }}" enctype="multipart/form-data"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4" id="revisi-form"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="parent_entry_id">Feedback yang dijawab (opsional)</label>
            <select name="parent_entry_id" id="parent_entry_id"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Tidak ada — revisi mandiri</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old("parent_entry_id", $selectedParentId) == $parent->id)>
                        Entri #{{ $parent->id }} · {{ $parent->revision_round ? "Revisi ke-{$parent->revision_round}" : "Logbook" }} · {{ $parent->reviewed_at?->format("d M Y") }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-text-secondary mt-1">Kosongkan jika ingin membuat revisi tanpa menghubungkan ke entri logbook yang ada.</p>
            @error("parent_entry_id")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        @foreach ($parents as $parent)
            <div data-parent-feedback="{{ $parent->id }}" class="rounded-md bg-bg-panel border border-border p-3 space-y-2 hidden">
                <p class="text-xs font-semibold text-text-secondary">Feedback entri #{{ $parent->id }}</p>
                <p class="text-sm whitespace-pre-wrap">{{ $parent->feedback_dosen ?: "Tidak ada feedback teks." }}</p>
                @foreach ($parent->comments->where("resolution_status", "!=", \App\Models\PdfComment::STATUS_RESOLVED) as $comment)
                    <label class="flex gap-2 items-start text-xs">
                        <input type="checkbox" name="addressed_comment_ids[]" value="{{ $comment->id }}"
                            @checked(in_array($comment->id, old("addressed_comment_ids", []))) class="mt-0.5">
                        <span>Hal. {{ $comment->page_number }}: {{ $comment->comment }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach
        <div> <label
                class="block text-sm font-medium mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label> <input
                type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                value="{{ old("tanggal_pengiriman", now()->format("Y-m-d")) }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("tanggal_pengiriman")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Catatan Perbaikan (tabel terstruktur) ===== --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium">Catatan Perbaikan</label>
                <button type="button" id="tambah-baris" class="px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-xs font-semibold">+ Tambah Baris</button>
            </div>
            <p class="text-xs text-text-secondary mb-2">Isi tabel perbaikan sesuai komentar dosen. PDF catatan perbaikan dibuat otomatis oleh sistem.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-border" id="tabel-perbaikan">
                    <thead>
                        <tr class="bg-bg-panel text-left text-text-secondary">
                            <th class="py-2 px-2 border-b border-border w-[15%]">Halaman/Bagian</th>
                            <th class="py-2 px-2 border-b border-border w-[25%]">Komentar Dosen</th>
                            <th class="py-2 px-2 border-b border-border w-[30%]">Perbaikan yang Dilakukan</th>
                            <th class="py-2 px-2 border-b border-border w-[15%]">Status</th>
                            <th class="py-2 px-2 border-b border-border w-[8%]"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($oldRiwayat as $i => $row)
                            <tr class="border-b border-border">
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][halaman]" value="{{ $row['halaman'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][komentar_dosen]" value="{{ $row['komentar_dosen'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][perbaikan]" value="{{ $row['perbaikan'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1">
                                    <select name="riwayat_perbaikan[{{ $i }}][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm">
                                        <option value="">—</option>
                                        @foreach ($statusOptions as $s)
                                            <option value="{{ $s }}" @selected(($row['status'] ?? '') === $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>
                            </tr>
                        @empty
                            <tr class="border-b border-border">
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][halaman]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][komentar_dosen]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][perbaikan]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1">
                                    <select name="riwayat_perbaikan[0][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm">
                                        <option value="">—</option>
                                        @foreach ($statusOptions as $s)
                                            <option value="{{ $s }}">{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error("riwayat_perbaikan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Pesan untuk Dosen ===== --}}
        <div>
            <label class="block text-sm font-medium mb-1" for="progres_kendala">Pesan untuk Dosen <span class="text-text-secondary">(opsional)</span></label>
            <textarea name="progres_kendala" id="progres_kendala" rows="4" maxlength="500"
                placeholder="Contoh: Mohon maaf atas keterlambatan pengumpulan revisi, saya terkendala ... / Ada satu poin yang masih saya tandai 'Sebagian' karena ... / Mohon arahan untuk bagian ..."
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">{{ old("progres_kendala") }}</textarea>
            <div class="flex flex-wrap items-center justify-between gap-2 mt-1">
                <p class="text-xs text-text-secondary">Gunakan untuk konteks yang tidak tertampung di tabel (kendala, alasan, pertanyaan). Pesan ini tampil di atas PDF yang diterima dosen.</p>
                <span class="text-xs text-text-secondary" id="pesan-counter">0/500</span>
            </div>
            @error("progres_kendala")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Berkas ===== --}}
        <div> <label class="block text-sm font-medium mb-1" for="lampiran">File Perbaikan/Draft ({{ $typesLabel }}, wajib, maks {{ $maxMb }} MB)</label> <input type="file" name="lampiran" id="lampiran" accept="{{ $accept }}" required
                class="w-full text-sm"> @error("lampiran")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2"> <button type="submit"
                class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan
                Revisi</button> <button type="submit" name="submit" value="1"
                class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Kirim
                ke dosen</button> <a href="{{ route("logbook.index") }}"
                class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Batal</a>
        </div>
    </form>
</div>
@endsection @section("scripts")
<script>
    // Counter pesan untuk dosen (max 500).
    var pesanInput = document.getElementById('progres_kendala');
    var pesanCounter = document.getElementById('pesan-counter');
    function updateCounter() {
        var len = pesanInput.value.length;
        pesanCounter.textContent = len + '/500';
    }
    if (pesanInput) pesanInput.addEventListener('input', updateCounter);

    // Tabel perbaikan dinamis: tambah/hapus baris.
    var tabel = document.getElementById('tabel-perbaikan');
    var tbody = tabel ? tabel.querySelector('tbody') : null;
    var tambahBtn = document.getElementById('tambah-baris');
    var statusOptions = @json($statusOptions);

    function reindex() {
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
            tr.querySelectorAll('input, select').forEach(function (el) {
                var name = el.name.replace(/riwayat_perbaikan\[\d+\]/, 'riwayat_perbaikan[' + i + ']');
                el.name = name;
            });
        });
    }

    function addRow(data) {
        data = data || {};
        var tr = document.createElement('tr');
        tr.className = 'border-b border-border';
        tr.innerHTML =
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][halaman]" value="' + (data.halaman || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][komentar_dosen]" value="' + (data.komentar_dosen || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][perbaikan]" value="' + (data.perbaikan || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><select name="riwayat_perbaikan[0][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"><option value="">—</option>' + statusOptions.map(function (s) { return '<option value="' + s + '"' + (data.status === s ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></td>' +
            '<td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>';
        tbody.appendChild(tr);
        reindex();
    }

    if (tambahBtn) tambahBtn.addEventListener('click', function () { addRow(); });

    if (tbody) tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('hapus-baris')) {
            var tr = e.target.closest('tr');
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
                reindex();
            }
        }
    });

    // Feedback parent toggle.
    var parentSelect = document.getElementById('parent_entry_id');
    var parentCards = document.querySelectorAll('[data-parent-feedback]');
    function syncParentFeedback() {
        if (!parentSelect) return;
        parentCards.forEach(function (card) {
            var active = card.dataset.parentFeedback === parentSelect.value;
            card.classList.toggle('hidden', !active);
            card.querySelectorAll('input[name="addressed_comment_ids[]"]').forEach(function (input) {
                input.disabled = !active;
            });
        });
    }
    if (parentSelect) {
        parentSelect.addEventListener('change', syncParentFeedback);
        syncParentFeedback();
    }
</script>
@endsection