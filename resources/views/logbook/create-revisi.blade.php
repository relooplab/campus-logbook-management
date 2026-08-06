@extends("layouts.app") @section("title", "Entri Revisi") @section("content")
@php
    $inst = \App\Models\Institution::current();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(", ", $inst->allowedFileTypes()));
    $statusOptions = \App\Models\LogbookEntry::PERBAIKAN_STATUSES;
    $oldRiwayat = old("riwayat_perbaikan", []);
    $initialRows = $oldRiwayat;
    if (!$initialRows && $parentComments->isNotEmpty()) {
        $initialRows = $parentComments->map(fn ($c) => [
            'halaman' => 'Hal. '.$c->page_number,
            'komentar_dosen' => $c->comment,
            'perbaikan' => $c->reply,
            'status' => $statusOptions[0] ?? null,
        ])->toArray();
    }
@endphp
<div class="max-w-3xl">
    <div class="flex items-center justify-between mb-5">
        <h1 class="font-heading font-bold text-2xl text-text-primary">Entri Revisi</h1>
        <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</a>
    </div>
    @if ($parents->isEmpty())
        <div class="mb-4 px-4 py-3 rounded-xl bg-status-pending/10 border border-status-pending/30 text-sm">
            Anda bisa membuat entri revisi langsung tanpa harus ada logbook sebelumnya. Jika ingin menjawab feedback dari entri yang sudah ada, pilih entri asal di bawah.
        </div>
    @endif
    <form method="POST" action="{{ route('logbook.store-revisi') }}" enctype="multipart/form-data"
        class="card p-6 space-y-4" id="revisi-form">
        @csrf
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="parent_entry_id">Feedback yang dijawab (opsional)</label>
            <select name="parent_entry_id" id="parent_entry_id"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                <option value="">Tidak ada — revisi mandiri</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old('parent_entry_id', $selectedParentId) == $parent->id)>
                        Entri #{{ $parent->id }} · {{ $parent->revision_round ? "Revisi ke-{$parent->revision_round}" : 'Logbook' }} · {{ $parent->reviewed_at?->format('d M Y') }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-text-secondary mt-1">Kosongkan jika ingin membuat revisi tanpa menghubungkan ke entri logbook yang ada.</p>
            @error('parent_entry_id')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        @foreach ($parents as $parent)
            @php
                $parentCommentData = $parent->comments
                    ->where('resolution_status', '!=', \App\Models\PdfComment::STATUS_RESOLVED)
                    ->values()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'page_number' => $c->page_number,
                        'comment' => $c->comment,
                        'reply' => $c->reply,
                    ]);
            @endphp
            <div data-parent-feedback="{{ $parent->id }}" data-comments="@json($parentCommentData)" class="rounded-xl bg-bg-panel border border-border p-3 space-y-2 hidden">
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
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label>
            <input type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                value="{{ old('tanggal_pengiriman', now()->format('Y-m-d')) }}"
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
            @error('tanggal_pengiriman')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Catatan Perbaikan (tabel terstruktur) ===== --}}
        <div>
            <label class="block text-sm font-medium mb-1">Catatan Perbaikan</label>
            <p class="text-xs text-text-secondary mb-2">Isi tabel perbaikan sesuai komentar dosen. PDF catatan perbaikan dibuat otomatis oleh sistem. Tekan <b>Enter</b> untuk menambah baris.</p>
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
                        @forelse ($initialRows as $i => $row)
                            <tr class="border-b border-border">
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][halaman]" value="{{ $row['halaman'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][komentar_dosen]" value="{{ $row['komentar_dosen'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $i }}][perbaikan]" value="{{ $row['perbaikan'] ?? '' }}" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                <td class="py-1.5 px-1">
                                    <select name="riwayat_perbaikan[{{ $i }}][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm">
                                        @foreach ($statusOptions as $s)
                                            <option value="{{ $s }}" @selected(($row['status'] ?? '') === $s || (($row['status'] ?? '') === '' && $loop->first))>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>
                            </tr>
                        @empty
                            @for ($r = 0; $r < 5; $r++)
                                <tr class="border-b border-border">
                                    <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $r }}][halaman]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                    <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $r }}][komentar_dosen]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                    <td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[{{ $r }}][perbaikan]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>
                                    <td class="py-1.5 px-1">
                                        <select name="riwayat_perbaikan[{{ $r }}][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm">
                                            @foreach ($statusOptions as $s)
                                                <option value="{{ $s }}" @selected($loop->first)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>
                                </tr>
                            @endfor
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                <button type="button" id="tambah-baris" class="px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-xs font-semibold">+ Tambah Baris</button>
            </div>
            @error("riwayat_perbaikan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Pesan untuk Dosen ===== --}}
        <div>
            <label class="block text-xs text-text-secondary mb-1" for="progres_kendala">Pesan untuk Dosen <span class="text-text-secondary">(opsional)</span></label>
            <textarea name="progres_kendala" id="progres_kendala" rows="4" maxlength="500"
                placeholder="Contoh: Mohon maaf atas keterlambatan pengumpulan revisi, saya terkendala ... / Ada satu poin yang masih saya tandai 'Sebagian' karena ... / Mohon arahan untuk bagian ..."
                class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('progres_kendala') }}</textarea>
            <div class="flex flex-wrap items-center justify-between gap-2 mt-1">
                <p class="text-xs text-text-secondary">Gunakan untuk konteks yang tidak tertampung di tabel (kendala, alasan, pertanyaan). Pesan ini tampil di atas PDF yang diterima dosen.</p>
                <span class="text-xs text-text-secondary" id="pesan-counter">0/500</span>
            </div>
            @error('progres_kendala')
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- ===== Berkas ===== --}}
        <div>
            <label class="block text-sm font-medium mb-1" for="lampiran">File Perbaikan/Draft ({{ $typesLabel }}, wajib, maks {{ $maxMb }} MB)</label>
            <input type="file" name="lampiran" id="lampiran" accept="{{ $accept }}" required
                class="w-full text-sm">
            <div id="revisi-file-info" class="hidden mt-2 p-3 rounded-xl bg-bg-panel border border-border text-xs space-y-1">
                <p><span class="text-text-secondary">Nama:</span> <span id="revisi-file-name" class="font-medium text-text-primary"></span></p>
                <p><span class="text-text-secondary">Ukuran:</span> <span id="revisi-file-size" class="font-medium text-text-primary"></span></p>
                <p><span class="text-text-secondary">Tipe:</span> <span id="revisi-file-type" class="font-medium text-text-primary"></span></p>
                <p id="revisi-file-valid" class="text-status-success font-medium"></p>
                <p id="revisi-file-invalid" class="text-status-danger font-medium hidden"></p>
            </div>
            @error("lampiran")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Simpan Revisi</button>
            <button type="submit" name="submit" value="1" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Kirim ke dosen</button>
            <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Batal</a>
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
        // Default status = "Sudah" (statusOptions[0]).
        var defaultStatus = statusOptions.length ? statusOptions[0] : '';
        tr.innerHTML =
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][halaman]" value="' + (data.halaman || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][komentar_dosen]" value="' + (data.komentar_dosen || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><input type="text" name="riwayat_perbaikan[0][perbaikan]" value="' + (data.perbaikan || '') + '" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm"></td>' +
            '<td class="py-1.5 px-1"><select name="riwayat_perbaikan[0][status]" class="w-full rounded border border-border bg-bg-surface px-2 py-1.5 text-sm">' + statusOptions.map(function (s) { return '<option value="' + s + '"' + ((data.status === s) || (!data.status && s === defaultStatus) ? ' selected' : '') + '>' + s + '</option>'; }).join('') + '</select></td>' +
            '<td class="py-1.5 px-1 text-center"><button type="button" class="hapus-baris text-status-danger hover:underline text-xs">Hapus</button></td>';
        tbody.appendChild(tr);
        reindex();
        // Fokus ke input pertama baris baru.
        var firstInput = tr.querySelector('input');
        if (firstInput) firstInput.focus();
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

    // Tekan Enter pada input di tabel untuk menambah baris baru.
    if (tbody) tbody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
            e.preventDefault();
            addRow();
        }
    });

    // Feedback parent toggle + auto-fill tabel dari komentar PDF.
    var parentSelect = document.getElementById('parent_entry_id');
    var parentCards = document.querySelectorAll('[data-parent-feedback]');
    function fillTableFromComments(comments) {
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!comments || !comments.length) {
            for (var k = 0; k < 5; k++) addRow();
            return;
        }
        comments.forEach(function (c) {
            addRow({
                halaman: c.page_number ? 'Hal. ' + c.page_number : '',
                komentar_dosen: c.comment || '',
                perbaikan: c.reply || '',
                status: statusOptions.length ? statusOptions[0] : ''
            });
        });
    }
    var initialParentSyncDone = false;
    function syncParentFeedback() {
        if (!parentSelect) return;
        parentCards.forEach(function (card) {
            var active = card.dataset.parentFeedback === parentSelect.value;
            card.classList.toggle('hidden', !active);
            card.querySelectorAll('input[name="addressed_comment_ids[]"]').forEach(function (input) {
                input.disabled = !active;
            });
            if (active && initialParentSyncDone) {
                try {
                    var comments = JSON.parse(card.dataset.comments || '[]');
                    fillTableFromComments(comments);
                } catch (e) {}
            }
        });
    }
    if (parentSelect) {
        parentSelect.addEventListener('change', syncParentFeedback);
        syncParentFeedback();
        initialParentSyncDone = true;
    }

    // ---- Upload feedback: nama, ukuran, tipe, validasi sebelum submit ----
    (function () {
        var fileInput = document.getElementById('lampiran');
        var infoBox = document.getElementById('revisi-file-info');
        var nameEl = document.getElementById('revisi-file-name');
        var sizeEl = document.getElementById('revisi-file-size');
        var typeEl = document.getElementById('revisi-file-type');
        var validEl = document.getElementById('revisi-file-valid');
        var invalidEl = document.getElementById('revisi-file-invalid');
        var maxMb = {{ $maxMb }};
        var allowedTypes = @json($inst->allowedFileTypes());

        function formatBytes(bytes) {
            if (bytes <= 0) return '0 B';
            var units = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
        }

        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            if (!file) {
                infoBox.classList.add('hidden');
                return;
            }
            infoBox.classList.remove('hidden');
            nameEl.textContent = file.name;
            sizeEl.textContent = formatBytes(file.size);
            typeEl.textContent = file.type || 'Tidak diketahui';

            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var sizeOk = file.size <= maxMb * 1024 * 1024;
            var typeOk = allowedTypes.includes(ext);

            if (sizeOk && typeOk) {
                validEl.textContent = '✓ File valid. Siap diunggah.';
                validEl.classList.remove('hidden');
                invalidEl.classList.add('hidden');
            } else {
                validEl.classList.add('hidden');
                invalidEl.classList.remove('hidden');
                var reasons = [];
                if (!sizeOk) reasons.push('Ukuran melebihi batas ' + maxMb + ' MB');
                if (!typeOk) reasons.push('Format .' + ext + ' tidak diizinkan (hanya: ' + allowedTypes.join(', ') + ')');
                invalidEl.textContent = '✗ ' + reasons.join('. ') + '.';
            }
        });
    })();

    // Auto-save draft ke localStorage (tiap 5 detik) + restore.
    // Key per-user & per-program agar draft TA/KP atau akun berbeda tidak tertukar.
    (function () {
        var KEY = 'lbta-draft-{{ auth()->id() }}-{{ $ta->id ?? 0 }}-revisi';
        var form = document.getElementById('revisi-form');
        var msg = document.createElement('p');
        msg.className = 'text-xs text-text-secondary mt-1';
        msg.id = 'revisi-autosave-msg';
        form.appendChild(msg);

        var restoreBtn = document.createElement('button');
        restoreBtn.type = 'button';
        restoreBtn.id = 'revisi-autosave-restore';
        restoreBtn.className = 'hidden text-xs text-brand hover:underline';
        restoreBtn.textContent = 'Pulihkan';
        form.appendChild(restoreBtn);

        var discardBtn = document.createElement('button');
        discardBtn.type = 'button';
        discardBtn.id = 'revisi-autosave-discard';
        discardBtn.className = 'hidden text-xs text-status-danger hover:underline';
        discardBtn.textContent = 'Buang draft';
        form.appendChild(discardBtn);

        function collect() {
            var data = {
                tanggal_pengiriman: document.getElementById('tanggal_pengiriman')?.value || '',
                progres_kendala: document.getElementById('progres_kendala')?.value || '',
                parent_entry_id: document.getElementById('parent_entry_id')?.value || '',
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
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        function restoreDraft(saved) {
            if (saved.tanggal_pengiriman) document.getElementById('tanggal_pengiriman').value = saved.tanggal_pengiriman;
            if (saved.parent_entry_id) document.getElementById('parent_entry_id').value = saved.parent_entry_id;
            document.getElementById('progres_kendala').value = saved.progres_kendala;
            // Restore riwayat rows.
            if (saved.riwayat && saved.riwayat.length) {
                tbody.innerHTML = '';
                saved.riwayat.forEach(function (r) { addRow(r); });
            }
            msg.textContent = 'Draf dipulihkan dari penyimpanan otomatis.';
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        function discardDraft() {
            localStorage.removeItem(KEY);
            document.getElementById('tanggal_pengiriman').value = '';
            document.getElementById('parent_entry_id').value = '';
            document.getElementById('progres_kendala').value = '';
            tbody.innerHTML = '';
            addRow();
            msg.textContent = 'Draft dibuang.';
            restoreBtn.classList.add('hidden');
            discardBtn.classList.add('hidden');
        }

        // Cek draft tersimpan.
        try {
            var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (saved && saved.progres_kendala && !document.getElementById('progres_kendala').value) {
                msg.textContent = 'Draft tersimpan ditemukan (' + new Date(saved.ts || Date.now()).toLocaleTimeString() + ').';
                restoreBtn.classList.remove('hidden');
                discardBtn.classList.remove('hidden');
            }
        } catch (e) {}

        restoreBtn.addEventListener('click', function () {
            try {
                var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
                if (saved) restoreDraft(saved);
            } catch (e) {}
        });
        discardBtn.addEventListener('click', discardDraft);

        setInterval(save, 5000);
        // Hapus draft saat berhasil submit.
        form.addEventListener('submit', function () {
            localStorage.removeItem(KEY);
        });
    })();
</script>
@endsection
