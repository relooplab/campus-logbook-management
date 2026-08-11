@extends("layouts.app") @section("title", "Edit Logbook") @section("content")
@php
    $inst = \App\Models\Institution::current();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(", ", $inst->allowedFileTypes()));
    $isRevisi = $logbook->jenis === "revisi";
    $statusOptions = \App\Models\LogbookEntry::PERBAIKAN_STATUSES;
    $riwayat = old("riwayat_perbaikan", $logbook->riwayat_perbaikan ?: []);
@endphp
<div class="max-w-3xl">
    <div class="flex items-center justify-between mb-5">
        <h1 class="font-heading font-bold text-2xl text-text-primary">Edit Entri</h1>
        @include('partials.status-badge', ['status' => $logbook->status])
    </div>
    <form method="POST" action="{{ route('logbook.update', $logbook) }}" enctype="multipart/form-data"
        class="card p-6 space-y-4">
        @csrf
        @method('PUT')
        @if ($isRevisi)
            <div>
                <label class="block text-xs text-text-secondary mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label>
                <input type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                    value="{{ old('tanggal_pengiriman', $logbook->tanggal_pengiriman?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('tanggal_pengiriman')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div>
                <label class="block text-xs text-text-secondary mb-1" for="tanggal_bimbingan">Tanggal Bimbingan</label>
                <input type="date" name="tanggal_bimbingan" id="tanggal_bimbingan" required
                    value="{{ old('tanggal_bimbingan', $logbook->tanggal_bimbingan?->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('tanggal_bimbingan')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1" for="topik">Topik Bimbingan</label>
                <input type="text" name="topik" id="topik" required value="{{ old('topik', $logbook->topik) }}"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('topik')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endif

        @if ($isRevisi)
            {{-- ===== Catatan Perbaikan (tabel terstruktur) ===== --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium">Catatan Perbaikan</label>
                    <button type="button" id="tambah-baris" class="px-3 py-1.5 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-xs font-semibold">+ Tambah Baris</button>
                </div>
                <p class="text-xs text-text-secondary mb-2">PDF catatan perbaikan dibuat otomatis oleh sistem dari tabel ini.</p>
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
                            @forelse ($riwayat as $i => $row)
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
        @endif

        {{-- ===== Pesan untuk Dosen (revisi) / Ringkasan (logbook) ===== --}}
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="progres_kendala">{{ $isRevisi ? 'Pesan untuk Dosen (opsional)' : 'Ringkasan Perbaikan' }}</label>
            <textarea name="progres_kendala" id="progres_kendala" rows="6" {{ $isRevisi ? 'maxlength="500"' : 'required' }}
                placeholder="{{ $isRevisi ? 'Contoh: Mohon maaf atas keterlambatan pengumpulan revisi, saya terkendala ... / Ada satu poin yang masih saya tandai "Sebagian" karena ... / Mohon arahan untuk bagian ...' : '' }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('progres_kendala', $logbook->progres_kendala) }}</textarea>
            @if ($isRevisi)
                <div class="flex flex-wrap items-center justify-between gap-2 mt-1">
                    <p class="text-xs text-text-secondary">Gunakan untuk konteks yang tidak tertampung di tabel (kendala, alasan, pertanyaan). Pesan ini tampil di atas PDF yang diterima dosen.</p>
                    <span class="text-xs text-text-secondary" id="pesan-counter">0/500</span>
                </div>
            @endif
            @error('progres_kendala')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Lampiran draft --}} <div> <label class="block text-sm font-medium mb-1">Lampiran Draft
                ({{ $typesLabel }})</label>
            @if ($logbook->lampiran_path)
                    <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-bg-panel"> <span class="material-symbols-outlined icon-lg text-accent-blue">description</span>
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            {{ $logbook->lampiran_original_name ?: basename($logbook->lampiran_path) }}</p>
                        <p class="text-xs text-text-secondary">
                            {{ number_format(filesize(Storage::disk("local")->path($logbook->lampiran_path)) / 1048576, 1) }}
                            MB · {{ $logbook->updated_at->format("d M") }}</p>
                    </div>
                    <div class="flex items-center gap-1"> <a href="{{ route("logbook.pdf", $logbook) }}"
                            target="_blank" class="px-2 py-1 rounded-xl bg-bg-panel hover:bg-bg-hover text-xs">Lihat</a>
                        <label
                            class="px-2 py-1 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-xs cursor-pointer">
                            Ganti <input type="file" name="lampiran" accept="{{ $accept }}" class="hidden">
                        </label>
                        <form method="POST" action="{{ route("logbook.remove-lampiran", $logbook) }}"
                            onsubmit="return confirm('Hapus lampiran ini? File tidak bisa dikembalikan.')"> @csrf
                            @method("DELETE") <button
                                class="px-2 py-1 rounded-xl bg-status-danger hover:bg-status-danger/90 text-white text-xs">Hapus</button>
                        </form>
                    </div>
                </div>
            @else
                <input type="file" name="lampiran" accept="{{ $accept }}" class="w-full text-sm">
                @endif @error("lampiran")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
    </div>
    <div
        class="px-3 py-2 rounded-xl bg-status-pending/10 border border-status-pending/20 text-xs text-status-pending">
        <span class="material-symbols-outlined icon-sm align-text-bottom">warning</span> Mengganti atau menghapus file akan mengarsipkan versi lama dan tidak bisa dikembalikan. Komentar PDF
        pada file yang diganti akan otomatis ditandai selesai (resolve). </div>
    <div class="flex flex-wrap gap-2 pt-2">
        <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan</button>
        <a href="{{ route('logbook.show', $logbook) }}" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Batal</a>
    </div>
</form>
</div>
@endsection @section("scripts")
<script>
    var isRevisi = @json($isRevisi);

    // Counter pesan untuk dosen (revisi).
    var pesanInput = document.getElementById('progres_kendala');
    var pesanCounter = document.getElementById('pesan-counter');
    function updateCounter() {
        if (pesanCounter) pesanCounter.textContent = pesanInput.value.length + '/500';
    }
    if (pesanInput && isRevisi) pesanInput.addEventListener('input', updateCounter);

    // Tabel perbaikan dinamis (revisi).
    var tabel = document.getElementById('tabel-perbaikan');
    var tbody = tabel ? tabel.querySelector('tbody') : null;
    var tambahBtn = document.getElementById('tambah-baris');
    var statusOptions = @json($statusOptions);

    function reindex() {
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
            tr.querySelectorAll('input, select').forEach(function (el) {
                el.name = el.name.replace(/riwayat_perbaikan\[\d+\]/, 'riwayat_perbaikan[' + i + ']');
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

    // Auto-save draft ke localStorage (tiap 5 detik) + restore.
    (function () {
        var KEY = 'lbta-edit-draft-' + @json($logbook->id);
        var form = document.querySelector('form[action*="logbook"]');
        var msg = document.createElement('p');
        msg.className = 'text-xs text-text-secondary mt-1';
        msg.id = 'edit-autosave-msg';
        form.appendChild(msg);

        function collect() {
            var data = {
                tanggal_bimbingan: document.getElementById('tanggal_bimbingan')?.value || '',
                tanggal_pengiriman: document.getElementById('tanggal_pengiriman')?.value || '',
                topik: document.getElementById('topik')?.value || '',
                progres_kendala: document.getElementById('progres_kendala')?.value || '',
                riwayat: []
            };
            document.querySelectorAll('#tabel-perbaikan tbody tr').forEach(function (tr) {
                var row = {};
                tr.querySelectorAll('input, select').forEach(function (el) {
                    var name = el.name;
                    var m = name.match(/riwayat_perbaikan\[\d+\]\[(\w+)\]/);
                    if (m) row[m[1]] = el.value;
                });
                data.riwayat.push(row);
            });
            return data;
        }

        function save() {
            localStorage.setItem(KEY, JSON.stringify(collect()));
            msg.textContent = 'Draf tersimpan otomatis ' + new Date().toLocaleTimeString();
        }

        // Restore draft (hanya jika ada dan belum mengisi ulang).
        try {
            var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (saved && saved.progres_kendala && !document.getElementById('progres_kendala').value) {
                if (saved.tanggal_bimbingan) document.getElementById('tanggal_bimbingan')?.value = saved.tanggal_bimbingan;
                if (saved.tanggal_pengiriman) document.getElementById('tanggal_pengiriman')?.value = saved.tanggal_pengiriman;
                if (saved.topik) document.getElementById('topik')?.value = saved.topik;
                document.getElementById('progres_kendala').value = saved.progres_kendala;
                if (saved.riwayat && saved.riwayat.length && tbody) {
                    tbody.innerHTML = '';
                    saved.riwayat.forEach(function (r) { addRow(r); });
                }
                msg.textContent = 'Draf dipulihkan dari penyimpanan otomatis.';
            }
        } catch (e) {}

        setInterval(save, 5000);
        form.addEventListener('submit', function () {
            localStorage.removeItem(KEY);
        });
    })();
</script>
@endsection
