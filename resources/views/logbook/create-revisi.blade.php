@extends("layouts.app") @section("title", "Entri Revisi") @section("content")
@php
    $inst = \App\Models\Institution::current();
    $maxMb = $inst->maxUploadSizeMb();
    $accept = $inst->fileAccept();
    $typesLabel = strtoupper(implode(", ", $inst->allowedFileTypes()));
    $statusOptions = \App\Models\LogbookEntry::PERBAIKAN_STATUSES;
    $oldRiwayat = old("riwayat_perbaikan", []);
    // Komentar PDF terbuka menjadi kartu awal ketika parent dipilih.
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

    {{-- ===== Wizard Steps Indicator ===== --}}
    <div class="flex items-center gap-1 mb-6 overflow-x-auto pb-1">
        @php
            $steps = [
                1 => ['Pilih Umpan Balik', 'forum'],
                2 => ['Isi Perbaikan', 'build'],
                3 => ['Upload File', 'upload_file'],
                4 => ['Review & Kirim', 'send'],
            ];
        @endphp
        @foreach ($steps as $num => [$label, $icon])
            <div class="flex items-center gap-1 shrink-0">
                <button type="button" data-step="{{ $num }}"
                    class="wizard-step flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium transition-colors {{ $num === 1 ? 'bg-brand text-white' : 'bg-bg-panel text-text-secondary hover:bg-bg-hover' }}">
                    <span class="material-symbols-outlined icon-sm">{{ $icon }}</span>
                    <span class="hidden sm:inline">{{ $label }}</span>
                </button>
                @if ($num < 4)
                    <span class="w-4 h-0.5 bg-border"></span>
                @endif
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('logbook.store-revisi') }}" enctype="multipart/form-data"
        class="card p-6 space-y-4" id="revisi-form">
        @csrf

        {{-- ===== STEP 1: Pilih Feedback ===== --}}
        <div class="wizard-panel" data-panel="1">
            <h2 class="font-heading font-semibold text-text-primary mb-3">1. Pilih Umpan Balik yang Dijawab</h2>
            <div>
                <label class="block text-xs text-text-secondary mb-1" for="parent_entry_id">Umpan Balik yang dijawab (opsional)</label>
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

            {{-- Feedback & komentar parent --}}
            @foreach ($parents as $parent)
                <div data-parent-feedback="{{ $parent->id }}" class="rounded-xl bg-bg-panel border border-border p-3 space-y-2 hidden mt-3">
                    <p class="text-xs font-semibold text-text-secondary">Umpan Balik entri #{{ $parent->id }}</p>
                    <p class="text-sm whitespace-pre-wrap">{{ $parent->feedback_dosen ?: "Tidak ada feedback teks." }}</p>
                    @php $openComments = $parent->comments->where('resolution_status', '!=', \App\Models\PdfComment::STATUS_RESOLVED); @endphp
                    @if ($openComments->isNotEmpty())
                        <p class="text-xs font-semibold text-text-secondary pt-1">Komentar PDF yang belum diselesaikan:</p>
                        @foreach ($openComments as $comment)
                            <label class="flex gap-2 items-start text-xs">
                                <input type="checkbox" name="addressed_comment_ids[]" value="{{ $comment->id }}"
                                    @checked(in_array($comment->id, old("addressed_comment_ids", []))) class="mt-0.5">
                                <span>Hal. {{ $comment->page_number ?: '—' }}: {{ $comment->comment }}</span>
                            </label>
                        @endforeach
                    @endif
                </div>
            @endforeach

            <div class="mt-4 flex justify-end">
                <button type="button" class="wizard-next px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Lanjut →</button>
            </div>
        </div>

        {{-- ===== STEP 2: Isi Perbaikan ===== --}}
        <div class="wizard-panel hidden" data-panel="2">
            <h2 class="font-heading font-semibold text-text-primary mb-1">2. Isi Perbaikan</h2>
            <p class="text-xs text-text-secondary mb-3">Isi kartu perbaikan sesuai komentar dosen. PDF catatan perbaikan dibuat otomatis oleh sistem.</p>

            <div id="kartu-perbaikan" class="space-y-3">
                @forelse ($initialRows as $i => $row)
                    <div class="perbaikan-card rounded-xl bg-bg-panel border border-border p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-text-secondary">Perbaikan #{{ $i + 1 }}</span>
                            <button type="button" class="hapus-kartu text-status-danger hover:underline text-xs">Hapus</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Halaman/Bagian</label>
                                <input type="text" name="riwayat_perbaikan[{{ $i }}][halaman]" value="{{ $row['halaman'] ?? '' }}" placeholder="mis. Hal. 5, Bab 3"
                                    class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Status</label>
                                <select name="riwayat_perbaikan[{{ $i }}][status]" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                                    @foreach ($statusOptions as $s)
                                        <option value="{{ $s }}" @selected(($row['status'] ?? '') === $s || (($row['status'] ?? '') === '' && $loop->first))>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Komentar Dosen</label>
                            <input type="text" name="riwayat_perbaikan[{{ $i }}][komentar_dosen]" value="{{ $row['komentar_dosen'] ?? '' }}" placeholder="Komentar yang diperbaiki"
                                class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Perbaikan yang Dilakukan</label>
                            <textarea name="riwayat_perbaikan[{{ $i }}][perbaikan]" rows="2" placeholder="Jelaskan perbaikan yang Anda lakukan"
                                class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">{{ $row['perbaikan'] ?? '' }}</textarea>
                        </div>
                    </div>
                @empty
                    <div class="perbaikan-card rounded-xl bg-bg-panel border border-border p-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-text-secondary">Perbaikan #1</span>
                            <button type="button" class="hapus-kartu text-status-danger hover:underline text-xs">Hapus</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Halaman/Bagian</label>
                                <input type="text" name="riwayat_perbaikan[0][halaman]" placeholder="mis. Hal. 5, Bab 3"
                                    class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Status</label>
                                <select name="riwayat_perbaikan[0][status]" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                                    @foreach ($statusOptions as $s)
                                        <option value="{{ $s }}" @selected($loop->first)>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Komentar Dosen</label>
                            <input type="text" name="riwayat_perbaikan[0][komentar_dosen]" placeholder="Komentar yang diperbaiki"
                                class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs text-text-secondary mb-1">Perbaikan yang Dilakukan</label>
                            <textarea name="riwayat_perbaikan[0][perbaikan]" rows="2" placeholder="Jelaskan perbaikan yang Anda lakukan"
                                class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm"></textarea>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-3">
                <button type="button" id="tambah-kartu" class="px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-xs font-semibold">+ Tambah Kartu</button>
            </div>
            @error("riwayat_perbaikan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror

            <div class="mt-4 flex justify-between">
                <button type="button" class="wizard-prev px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</button>
                <button type="button" class="wizard-next px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Lanjut →</button>
            </div>
        </div>

        {{-- ===== STEP 3: Upload File ===== --}}
        <div class="wizard-panel hidden" data-panel="3">
            <h2 class="font-heading font-semibold text-text-primary mb-3">3. Upload File & Pesan</h2>

            <div>
                <label class="block text-xs text-text-secondary mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label>
                <input type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                    value="{{ old('tanggal_pengiriman', now()->format('Y-m-d')) }}"
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                @error('tanggal_pengiriman')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1" for="lampiran">File Perbaikan/Draft ({{ $typesLabel }}, wajib, maks {{ $maxMb }} MB)</label>
                <input type="file" name="lampiran" id="lampiran" accept="{{ $accept }}" required
                    class="w-full text-sm">
                @error("lampiran")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1" for="progres_kendala">Pesan untuk Dosen <span class="text-text-secondary">(opsional)</span></label>
                <textarea name="progres_kendala" id="progres_kendala" rows="4" maxlength="500"
                    placeholder="Contoh: Mohon maaf atas keterlambatan pengumpulan revisi, saya terkendala ... / Ada satu poin yang masih saya tandai 'Sebagian' karena ... / Mohon arahan untuk bagian ..."
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('progres_kendala') }}</textarea>
                <div class="flex flex-wrap items-center justify-between gap-2 mt-1">
                    <p class="text-xs text-text-secondary">Gunakan untuk konteks yang tidak tertampung di tabel (kendala, alasan, pertanyaan).</p>
                    <span class="text-xs text-text-secondary" id="pesan-counter">0/500</span>
                </div>
                @error('progres_kendala')
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-4 flex justify-between">
                <button type="button" class="wizard-prev px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</button>
                <button type="button" class="wizard-next px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Lanjut →</button>
            </div>
        </div>

        {{-- ===== STEP 4: Review & Kirim ===== --}}
        <div class="wizard-panel hidden" data-panel="4">
            <h2 class="font-heading font-semibold text-text-primary mb-3">4. Review Ringkasan</h2>

            <div class="rounded-xl bg-bg-panel border border-border p-4 space-y-3 text-sm">
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined icon-sm text-text-secondary mt-0.5">forum</span>
                    <div>
                        <p class="text-xs text-text-secondary">Umpan Balik yang dijawab</p>
                        <p class="text-text-primary" id="review-parent">—</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined icon-sm text-text-secondary mt-0.5">build</span>
                    <div>
                        <p class="text-xs text-text-secondary">Jumlah perbaikan</p>
                        <p class="text-text-primary" id="review-jumlah">0 kartu</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined icon-sm text-text-secondary mt-0.5">upload_file</span>
                    <div>
                        <p class="text-xs text-text-secondary">File perbaikan</p>
                        <p class="text-text-primary" id="review-file">Belum dipilih</p>
                    </div>
                </div>
                <div class="flex items-start gap-2">
                    <span class="material-symbols-outlined icon-sm text-text-secondary mt-0.5">event</span>
                    <div>
                        <p class="text-xs text-text-secondary">Tanggal pengiriman</p>
                        <p class="text-text-primary" id="review-tanggal">—</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="wizard-prev px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Simpan Draft</button>
                <button type="submit" name="submit" value="1" id="review-submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90" disabled>Kirim ke Dosen</button>
            </div>
        </div>
    </form>
</div>
@endsection @section("scripts")
<script>
    // ===== Wizard navigation =====
    var currentStep = 1;
    var totalSteps = 4;
    var uploadReady = false;
    var stepButtons = document.querySelectorAll('.wizard-step');
    var panels = document.querySelectorAll('.wizard-panel');

    function showStep(n) {
        currentStep = Math.max(1, Math.min(totalSteps, n));
        panels.forEach(function (p) { p.classList.toggle('hidden', parseInt(p.dataset.panel) !== currentStep); });
        stepButtons.forEach(function (b) {
            var active = parseInt(b.dataset.step) === currentStep;
            b.classList.toggle('bg-brand', active);
            b.classList.toggle('text-white', active);
            b.classList.toggle('bg-bg-panel', !active);
            b.classList.toggle('text-text-secondary', !active);
        });
        if (currentStep === 4) updateReview();
        var submitBtn = document.getElementById('review-submit');
        if (submitBtn) submitBtn.disabled = currentStep !== 4 || !uploadReady;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    document.querySelectorAll('.wizard-next').forEach(function (btn) {
        btn.addEventListener('click', function () { if (currentStep === 3 && !uploadReady) return; showStep(currentStep + 1); });
    });
    document.querySelectorAll('.wizard-prev').forEach(function (btn) {
        btn.addEventListener('click', function () { showStep(currentStep - 1); });
    });
    stepButtons.forEach(function (btn) {
        btn.addEventListener('click', function () { showStep(parseInt(btn.dataset.step)); });
    });

    // ===== Counter pesan =====
    var pesanInput = document.getElementById('progres_kendala');
    var pesanCounter = document.getElementById('pesan-counter');
    function updateCounter() {
        var len = pesanInput.value.length;
        pesanCounter.textContent = len + '/500';
    }
    if (pesanInput) pesanInput.addEventListener('input', updateCounter);
    var fileInput = document.getElementById('lampiran');
    var uploadNext = document.querySelector('[data-panel="3"] .wizard-next');
    if (fileInput) fileInput.addEventListener('change', async function () {
        uploadReady = false; if (uploadNext) uploadNext.disabled = true;
        if (!fileInput.files.length || fileInput.files[0].type !== 'application/pdf') return;
        var body = new FormData(); body.append('lampiran', fileInput.files[0]);
        try {
            var response = await fetch('{{ route('logbook.upload-revisi') }}', { method: 'POST', body: body, headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'} });
            if (!response.ok) throw new Error('Upload gagal');
            var result = await response.json(); document.getElementById('revision-upload-token').value = result.token;
            uploadReady = true; if (uploadNext) uploadNext.disabled = false; showStep(3);
        } catch (error) { alert('Upload file revisi gagal. Silakan coba lagi.'); }
    });

    // ===== Kartu perbaikan dinamis =====
    var kartuContainer = document.getElementById('kartu-perbaikan');
    var tambahBtn = document.getElementById('tambah-kartu');
    var statusOptions = @json($statusOptions);

    function reindex() {
        Array.prototype.forEach.call(kartuContainer.querySelectorAll('.perbaikan-card'), function (card, i) {
            card.querySelectorAll('input, select, textarea').forEach(function (el) {
                var name = el.name.replace(/riwayat_perbaikan\[\d+\]/, 'riwayat_perbaikan[' + i + ']');
                el.name = name;
            });
            var label = card.querySelector('.text-xs.font-semibold');
            if (label) label.textContent = 'Perbaikan #' + (i + 1);
        });
    }

    function addKartu(data) {
        data = data || {};
        var defaultStatus = statusOptions.length ? statusOptions[0] : '';
        var div = document.createElement('div');
        div.className = 'perbaikan-card rounded-xl bg-bg-panel border border-border p-4 space-y-2';
        div.innerHTML =
            '<div class="flex items-center justify-between">' +
                '<span class="text-xs font-semibold text-text-secondary">Perbaikan #' + (kartuContainer.querySelectorAll('.perbaikan-card').length + 1) + '</span>' +
                '<button type="button" class="hapus-kartu text-status-danger hover:underline text-xs">Hapus</button>' +
            '</div>' +
            '<div class="grid sm:grid-cols-2 gap-2">' +
                '<div><label class="block text-xs text-text-secondary mb-1">Halaman/Bagian</label>' +
                '<input type="text" name="riwayat_perbaikan[0][halaman]" value="' + (data.halaman || '') + '" placeholder="mis. Hal. 5, Bab 3" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm"></div>' +
                '<div><label class="block text-xs text-text-secondary mb-1">Status</label>' +
                '<select name="riwayat_perbaikan[0][status]" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">' +
                statusOptions.map(function (s) { return '<option value="' + s + '"' + ((data.status === s) || (!data.status && s === defaultStatus) ? ' selected' : '') + '>' + s + '</option>'; }).join('') +
                '</select></div>' +
            '</div>' +
            '<div><label class="block text-xs text-text-secondary mb-1">Komentar Dosen</label>' +
            '<input type="text" name="riwayat_perbaikan[0][komentar_dosen]" value="' + (data.komentar_dosen || '') + '" placeholder="Komentar yang diperbaiki" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm"></div>' +
            '<div><label class="block text-xs text-text-secondary mb-1">Perbaikan yang Dilakukan</label>' +
            '<textarea name="riwayat_perbaikan[0][perbaikan]" rows="2" placeholder="Jelaskan perbaikan yang Anda lakukan" class="w-full rounded-lg border border-border bg-bg-surface px-3 py-2 text-sm">' + (data.perbaikan || '') + '</textarea></div>';
        kartuContainer.appendChild(div);
        reindex();
        var firstInput = div.querySelector('input');
        if (firstInput) firstInput.focus();
    }

    if (tambahBtn) tambahBtn.addEventListener('click', function () { addKartu(); });

    if (kartuContainer) kartuContainer.addEventListener('click', function (e) {
        if (e.target.classList.contains('hapus-kartu')) {
            var card = e.target.closest('.perbaikan-card');
            if (kartuContainer.querySelectorAll('.perbaikan-card').length > 1) {
                card.remove();
                reindex();
            }
        }
    });

    // ===== Feedback parent toggle =====
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

    // ===== Review ringkasan =====
    function updateReview() {
        var parentText = '—';
        if (parentSelect && parentSelect.selectedOptions[0] && parentSelect.value) {
            parentText = parentSelect.selectedOptions[0].textContent.trim();
        }
        document.getElementById('review-parent').textContent = parentText;

        var count = kartuContainer.querySelectorAll('.perbaikan-card').length;
        document.getElementById('review-jumlah').textContent = count + ' kartu';

        var fileInput = document.getElementById('lampiran');
        document.getElementById('review-file').textContent = fileInput && fileInput.files.length
            ? fileInput.files[0].name
            : 'Belum dipilih';

        var tanggal = document.getElementById('tanggal_pengiriman');
        document.getElementById('review-tanggal').textContent = tanggal && tanggal.value ? tanggal.value : '—';
    }

    // ===== Auto-save draft ke localStorage =====
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
            document.querySelectorAll('#kartu-perbaikan .perbaikan-card').forEach(function (card) {
                var row = {};
                card.querySelectorAll('input, select, textarea').forEach(function (el) {
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
                if (saved.tanggal_pengiriman) document.getElementById('tanggal_pengiriman').value = saved.tanggal_pengiriman;
                if (saved.parent_entry_id) document.getElementById('parent_entry_id').value = saved.parent_entry_id;
                document.getElementById('progres_kendala').value = saved.progres_kendala;
                if (saved.riwayat && saved.riwayat.length) {
                    kartuContainer.innerHTML = '';
                    saved.riwayat.forEach(function (r) { addKartu(r); });
                }
                msg.textContent = 'Draf dipulihkan dari penyimpanan otomatis.';
                syncParentFeedback();
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
        form.addEventListener('submit', function () {
            localStorage.removeItem(KEY);
        });
    })();
</script>
@endsection