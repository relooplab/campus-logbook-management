@extends('layouts.app')

@section('title', 'Detail Entri')

@section('content')
@php
    $user = auth()->user();
    $owner = $user->isMahasiswa() && $logbook->mahasiswaTa?->isMember($user);
    $canReview = $user->can('review', $logbook);
    $canReopen = $user->can('reopen', $logbook);
    $canManageActionItems = $user->can('manageActionItems', $logbook);

    // Peran dosen reviewer entri ini (pembimbing ATAU penguji bila mahasiswa
    // mengirim revisi ke dosen pengujinya).
    $reviewerRole = ($logbook->dosen && $logbook->mahasiswaTa)
        ? $logbook->mahasiswaTa->dosenRoleLabel($logbook->dosen)
        : null;
    $reviewerLabel = $logbook->dosen
        ? (($reviewerRole ? $reviewerRole.' — ' : '').$logbook->dosen->name)
        : ($logbook->mahasiswaTa?->pembimbing1?->name ?? null);

    // Navigasi "Kembali" konteks-sensitif pada halaman detail entri.
    if ($logbook->parentEntry) {
        // Entri ini adalah revisi yang menjawab entri induk → kembali ke sesi sebelumnya.
        $backUrl = route('logbook.show', $logbook->parentEntry);
        $backLabel = '← Lihat entri induk (sesi sebelumnya)';
    } elseif ($logbook->revisionChildren->count() === 1) {
        // Entri ini adalah induk dari tepat satu revisi berikutnya (umumnya dosen
        // datang dari sini) → kembali ke revisi tersebut.
        $child = $logbook->revisionChildren->first();
        $backUrl = route('logbook.show', $child);
        $backLabel = '← Kembali ke ' . ($child->revision_round ? 'Revisi ke-' . $child->revision_round : 'Revisi');
    } else {
        // Entri berdiri sendiri → kembali ke daftar logbook.
        $backUrl = route('logbook.index');
        $backLabel = '← Kembali ke Logbook';
    }
    $revisionRows = collect($logbook->riwayat_perbaikan ?? []);
    $completedRevisions = $revisionRows->filter(fn ($row) => ($row['status'] ?? null) === 'Sudah')->count();
@endphp

<div class="{{ $logbook->jenis === 'revisi' ? 'detail-workspace' : 'max-w-5xl' }} space-y-6">
    <x-page-header
        :subtitle="$logbook->jenis === 'revisi' ? null : 'Logbook Bimbingan'"
        :title="$logbook->jenis === 'revisi' ? 'Revisi' . ($logbook->revision_round ? ' ke-' . $logbook->revision_round : '') : 'Sesi ' . $logbook->sesi_ke">
        <x-slot:actions>
            <a href="{{ $backUrl }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">{{ $backLabel }}</a>
        </x-slot:actions>
    </x-page-header>

    @if ($logbook->jenis === 'revisi')
        <section class="card p-5 sm:p-6" aria-label="Ringkasan revisi">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-heading font-semibold text-text-primary">{{ $logbook->mahasiswaTa?->mahasiswa?->name }}</p>
                @include('partials.status-badge', ['status' => $logbook->status])
            </div>
            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm detail-workspace-card">
                <div><dt class="inline text-text-secondary">Dikirim </dt><dd class="inline font-medium">{{ $logbook->tanggal_tampil?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="inline text-text-secondary">Topik </dt><dd class="inline font-medium">{{ $logbook->topik ?? 'Revisi' }}</dd></div>
                <div><dt class="inline text-text-secondary">Ditujukan kepada </dt><dd class="inline font-medium">{{ $reviewerLabel ?? '—' }}</dd></div>
            </dl>
        </section>
    @endif

    <div class="{{ $logbook->jenis === 'revisi' ? 'detail-workspace-grid' : 'grid lg:grid-cols-[1fr_320px] gap-6 items-start' }}">
    <div class="space-y-4 min-w-0">
    <div class="{{ $logbook->jenis === 'revisi' ? 'space-y-5' : 'card p-6 space-y-4' }}">
        @if ($logbook->jenis !== 'revisi')
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-text-secondary">{{ $logbook->mahasiswaTa?->mahasiswa?->name }}</p>
            @include('partials.status-badge', ['status' => $logbook->status])
        </div>

        @include('partials.meta-grid', [
            'items' => [
                ['label' => 'Mahasiswa', 'value' => $logbook->mahasiswaTa?->mahasiswa?->name],
                ['label' => $logbook->jenis === 'revisi' ? 'Tanggal Pengiriman Revisi' : 'Tanggal Bimbingan', 'value' => $logbook->tanggal_tampil?->format('d M Y') ?? '—'],
                ['label' => 'Topik', 'value' => $logbook->topik ?? 'Revisi'],
                ['label' => $logbook->jenis === 'revisi' ? 'Ditujukan kepada' : 'Dosen', 'value' => $reviewerLabel ?? '—'],
            ],
        ])
        @endif

        @if ($owner && $logbook->status === 'submitted')
            <div class="px-4 py-3 rounded-xl bg-bg-panel border border-border text-sm flex flex-wrap items-center gap-2">
                @if ($logbook->review_opened_at)
                    <span class="inline-flex items-center gap-1.5 text-status-success font-medium">
                        <span class="material-symbols-outlined icon-sm text-status-info">visibility</span> Sudah dilihat dosen
                    </span>
                    <span class="text-xs text-text-secondary">dibuka {{ $logbook->review_opened_at->diffForHumans() }}</span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-text-secondary font-medium">
                        <span class="material-symbols-outlined icon-sm text-status-info">visibility_off</span> Belum dilihat dosen
                    </span>
                @endif
            </div>
        @endif

        @if ($logbook->parentEntry)
            <div class="px-4 py-3 rounded-xl bg-brand/10 border border-brand/20 text-sm">
                <p class="font-semibold">Menjawab entri induk #{{ $logbook->parentEntry->id }}</p>
                <a href="{{ route('logbook.show', $logbook->parentEntry) }}" class="text-brand hover:underline">Lihat feedback dan anotasi sesi sebelumnya</a>
            </div>
        @endif

        @if ($logbook->revisionChildren->isNotEmpty())
            <div class="px-4 py-3 rounded-xl bg-bg-panel border border-border text-sm">
                <p class="font-semibold mb-1">Riwayat revisi</p>
                @foreach ($logbook->revisionChildren as $child)
                    <a href="{{ route('logbook.show', $child) }}" class="block text-brand hover:underline">Revisi {{ $child->revision_round ?: '—' }} · {{ ucfirst($child->status) }}</a>
                @endforeach
            </div>
        @endif

        @if ($logbook->jenis === 'revisi')
            <div>
                <h3 class="text-sm font-semibold text-text-secondary mb-1">Pesan untuk Dosen</h3>
                <div class="text-sm whitespace-pre-wrap">{{ $logbook->progres_kendala ?: '—' }}</div>
            </div>
            <section aria-labelledby="revision-list-title">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 id="revision-list-title" class="font-heading font-semibold text-text-primary">Catatan Perbaikan</h3>
                    <p class="text-sm text-text-secondary">{{ $revisionRows->count() }} catatan · {{ $completedRevisions }} ditandai sudah oleh mahasiswa</p>
                </div>
                @forelse ($revisionRows as $r)
                    @php $revisionStatus = $r['status'] ?? '—'; @endphp
                    <article class="rounded-xl bg-bg-panel border border-border p-4 sm:p-5 mb-3 detail-workspace-card">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-text-primary">{{ $r['halaman'] ?? 'Bagian tidak disebutkan' }}</h4>
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $revisionStatus === 'Sudah' ? 'bg-status-success/10 text-status-success' : '' }}
                                {{ $revisionStatus === 'Sebagian' ? 'bg-status-pending/10 text-status-pending' : '' }}
                                {{ $revisionStatus === 'Belum' ? 'bg-status-danger/10 text-status-danger' : '' }}
                            ">{{ $revisionStatus }}</span>
                        </div>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div><dt class="text-xs font-semibold text-text-secondary mb-1">Komentar Dosen</dt><dd class="whitespace-pre-wrap text-text-primary">{{ $r['komentar_dosen'] ?? '—' }}</dd></div>
                            <div><dt class="text-xs font-semibold text-text-secondary mb-1">Respons / Perbaikan Mahasiswa</dt><dd class="whitespace-pre-wrap text-text-primary">{{ $r['perbaikan'] ?? '—' }}</dd></div>
                        </dl>
                    </article>
                @empty
                    <p class="text-sm text-text-secondary">Belum ada catatan perbaikan.</p>
                @endforelse
            </section>
        @else
            <div>
                <h3 class="text-sm font-semibold text-text-secondary mb-1">Ringkasan Perbaikan</h3>
                <div class="text-sm whitespace-pre-wrap">{{ $logbook->progres_kendala }}</div>
            </div>
        @endif

        @if ($logbook->feedback_dosen)
            <div class="px-4 py-3 rounded-xl bg-status-pending/10 border-l-4 border-status-pending text-sm">
                <div class="flex items-center gap-1.5 mb-1 text-xs font-semibold text-status-pending uppercase tracking-wide"><span class="material-symbols-outlined icon-sm text-accent-teal">forum</span> Umpan Balik Dosen</div>
                <div class="text-sm">{{ $logbook->feedback_dosen }}</div>
            </div>
        @endif

    </div>
    </div>

    {{-- ===== Kolom kanan: aksi (sticky) ===== --}}
    <aside class="{{ $logbook->jenis === 'revisi' ? 'detail-workspace-panel' : 'lg:sticky lg:top-20' }} space-y-4" aria-label="Dokumen dan tindakan">
        @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path)
            <section class="card p-5 space-y-3 detail-workspace-card">
                <h2 class="font-heading font-semibold text-text-primary">Dokumen</h2>
                <a href="{{ route('logbook.pdf-viewer', $logbook) }}" class="block text-center px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">{{ $canReview ? 'Buka PDF & Anotasi' : 'Lihat PDF & Komentar' }}</a>
                <div class="flex flex-wrap gap-x-3 gap-y-2 text-xs">
                    @if ($logbook->lampiran_path)
                        <a href="{{ route('logbook.pdf', $logbook) }}" target="_blank" rel="noopener noreferrer" class="text-brand hover:underline">Buka PDF di browser</a>
                    @endif
                    @if ($logbook->catatan_perbaikan_path)
                        <a href="{{ route('logbook.catatan-pdf', $logbook) }}" target="_blank" rel="noopener noreferrer" class="text-brand hover:underline">Buka catatan di browser</a>
                    @endif
                </div>
            </section>
        @endif

        @if ($canManageActionItems)
            <section class="card p-5 space-y-3 detail-workspace-card">
                <h2 class="font-heading font-semibold text-text-primary">Action Items</h2>
                <div id="action-items-list" class="space-y-3">
                    @forelse ($logbook->actionItems as $item)
                        <div class="flex items-start gap-2 action-item-row" data-item-id="{{ $item->id }}">
                            @if ($user->can('update', $logbook))
                                <input type="checkbox" class="action-item-toggle rounded bg-bg-surface mt-1" aria-label="Tandai selesai: {{ $item->text }}" @checked($item->is_done)>
                            @else
                                <span class="material-symbols-outlined icon-sm {{ $item->is_done ? '' : 'text-text-secondary' }}">{{ $item->is_done ? 'check_circle' : 'radio_button_unchecked' }}</span>
                            @endif
                            <span class="flex-1 min-w-0 text-sm {{ $item->is_done ? 'line-through text-text-secondary' : '' }}">{{ $item->text }}</span>
                            <button type="button" class="action-item-delete text-status-danger hover:underline text-xs shrink-0">Hapus</button>
                        </div>
                    @empty
                        <p class="text-sm text-text-secondary">Belum ada action item.</p>
                    @endforelse
                </div>
                <form id="action-item-add-form" class="space-y-2">
                    @csrf
                    <label for="action-item-text" class="sr-only">Tambah action item</label>
                    <input id="action-item-text" type="text" name="text" placeholder="Tambah action item..." maxlength="500" required
                        class="w-full min-w-0 rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                    <p id="action-item-error" class="hidden text-status-danger text-xs" role="alert"></p>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Tambah</button>
                </form>
            </section>
        @endif
        @if ($owner && $logbook->isEditable())
            <div class="card p-5 space-y-2">
                <h2 class="font-heading font-semibold text-text-primary mb-1">Tindakan</h2>
                <a href="{{ route('logbook.edit', $logbook) }}" class="block text-center px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Edit</a>
                <form method="POST" action="{{ route('logbook.submit', $logbook) }}">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Kirim ke dosen</button>
                </form>
                <form method="POST" action="{{ route('logbook.destroy', $logbook) }}"
                    onsubmit="return confirm('Hapus entri ini? Tindakan tidak dapat dibatalkan.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Hapus</button>
                </form>
            </div>
        @endif

        @if ($owner && $logbook->status === 'revisi' && !$logbook->isLockedByActiveRevision())
            <a href="{{ route('logbook.create-revisi', ['parent_entry_id' => $logbook->id]) }}" class="block text-center px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Buat Revisi dari Umpan Balik Ini</a>
        @endif

        @if ($canReview && $logbook->status === 'submitted')
            <section class="card p-5 space-y-4 detail-workspace-card">
                <div>
                    <h2 class="font-heading font-semibold text-text-primary">Keputusan Review</h2>
                    @if ($logbook->jenis === 'revisi')
                        <p class="text-sm text-text-secondary mt-1">{{ $completedRevisions }} dari {{ $revisionRows->count() }} catatan ditandai sudah oleh mahasiswa.</p>
                    @endif
                </div>
                @php $reviewDecision = old('review_decision', old('feedback_dosen') ? 'revisi' : ''); @endphp
                <form method="POST" action="{{ route('logbook.request-revisi', $logbook) }}" id="review-decision-form" class="space-y-4"
                    data-approve-url="{{ route('logbook.approve', $logbook) }}"
                    data-revision-url="{{ route('logbook.request-revisi', $logbook) }}"
                    data-pdf-opened="{{ $logbook->review_opened_at ? '1' : '0' }}"
                    data-has-pdf="{{ $logbook->lampiran_path || $logbook->catatan_perbaikan_path ? '1' : '0' }}">
                    @csrf
                    <fieldset class="space-y-2">
                        <legend class="sr-only">Pilih keputusan review</legend>
                        <label class="flex items-center gap-2 rounded-xl border border-border bg-bg-panel p-3 text-sm cursor-pointer">
                            <input type="radio" name="review_decision" value="approve" required @checked($reviewDecision === 'approve')> Setujui
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-border bg-bg-panel p-3 text-sm cursor-pointer">
                            <input type="radio" name="review_decision" value="revisi" required @checked($reviewDecision === 'revisi')> Minta Revisi
                        </label>
                    </fieldset>
                    <div id="revision-feedback-wrap" class="space-y-2 {{ $reviewDecision === 'revisi' ? '' : 'hidden' }}">
                        <label for="revision-feedback" class="block text-xs font-semibold text-text-secondary">Alasan revisi / feedback</label>
                        <textarea id="revision-feedback" name="feedback_dosen" rows="4" minlength="20" placeholder="Jelaskan perbaikan yang diperlukan (minimal 20 karakter)..."
                            class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40" @disabled($reviewDecision !== 'revisi') @if($reviewDecision === 'revisi') required @endif>{{ old('feedback_dosen') }}</textarea>
                        @error('feedback_dosen') <p class="text-status-danger text-xs">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Simpan Keputusan</button>
                </form>
            </section>
        @endif

        @if ($canReopen && $logbook->status === 'approved')
            <div class="card p-5 space-y-4">
                <h2 class="font-heading font-semibold text-text-primary">Buka Kembali Persetujuan</h2>
                <div class="px-4 py-3 rounded-xl bg-status-pending/10 border border-status-pending/20 text-sm text-text-secondary">
                    Entri ini sudah disetujui. Jika ternyata masih perlu perbaikan, batalkan persetujuan atau minta revisi kembali ke mahasiswa.
                </div>
                <form method="POST" action="{{ route('logbook.reopen', $logbook) }}"
                    onsubmit="return confirm('Batalkan persetujuan? Entri akan kembali menunggu review.');">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Batalkan Persetujuan → Menunggu Review</button>
                </form>
                <form method="POST" action="{{ route('logbook.reopen-revisi', $logbook) }}" class="space-y-2"
                    onsubmit="return confirm('Minta revisi kembali ke mahasiswa dengan feedback ini?');">
                    @csrf
                    <textarea name="feedback_dosen" rows="3" required minlength="20" placeholder="Feedback revisi lanjutan wajib diisi (minimal 20 karakter)..."
                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('feedback_dosen') }}</textarea>
                    @error('feedback_dosen')
                        <p class="text-status-danger text-xs">{{ $message }}</p>
                    @enderror
                    <button type="submit" class="w-full px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Minta Revisi Kembali</button>
                </form>
            </div>
        @endif
    </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // ---- Action items (pemilik boleh toggle + tambah; reviewer boleh tambah/hapus) ----
    var actionList = document.getElementById('action-items-list');
    var actionAddForm = document.getElementById('action-item-add-form');
    var logbookId = {{ $logbook->id }};
    var ownerCanToggle = {{ $user->can('update', $logbook) ? 'true' : 'false' }};

    function actionItemRow(item, interactive) {
        interactive = interactive !== undefined ? interactive : true;
        var row = document.createElement('div');
        row.className = 'flex items-start gap-2 action-item-row';
        row.dataset.itemId = item.id;
        if (interactive) {
            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'action-item-toggle rounded bg-bg-surface mt-1';
            checkbox.setAttribute('aria-label', 'Tandai selesai: ' + item.text);
            checkbox.checked = !!item.is_done;
            row.appendChild(checkbox);
        } else {
            var icon = document.createElement('span');
            icon.className = 'material-symbols-outlined icon-sm ' + (item.is_done ? '' : 'text-text-secondary');
            icon.textContent = item.is_done ? 'check_circle' : 'radio_button_unchecked';
            row.appendChild(icon);
        }
        var text = document.createElement('span');
        text.className = 'flex-1 min-w-0 text-sm' + (item.is_done ? ' line-through text-text-secondary' : '');
        text.textContent = item.text;
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'action-item-delete text-status-danger hover:underline text-xs shrink-0';
        del.textContent = 'Hapus';
        row.appendChild(text);
        row.appendChild(del);
        return row;
    }

    if (actionList) {
        // Toggle
        actionList.addEventListener('change', function (e) {
            if (e.target.classList.contains('action-item-toggle')) {
                var row = e.target.closest('.action-item-row');
                var id = row.dataset.itemId;
                var previous = !e.target.checked;
                fetch('/logbook/' + logbookId + '/action-items/' + id + '/toggle', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                }).then(r => { if (!r.ok) throw new Error('Gagal memperbarui action item.'); return r.json(); }).then(data => {
                    e.target.checked = data.is_done;
                    var textEl = row.querySelector('span');
                    textEl.classList.toggle('line-through', data.is_done);
                    textEl.classList.toggle('text-text-secondary', data.is_done);
                }).catch(() => { e.target.checked = previous; window.alert('Action item belum tersimpan. Coba lagi.'); });
            }
        });

        // Delete
        actionList.addEventListener('click', function (e) {
            if (e.target.classList.contains('action-item-delete')) {
                var row = e.target.closest('.action-item-row');
                var id = row.dataset.itemId;
                if (!confirm('Hapus action item ini?')) return;
                fetch('/logbook/' + logbookId + '/action-items/' + id, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                }).then(r => { if (!r.ok) throw new Error('Gagal menghapus action item.'); return r.json(); }).then(() => {
                    row.remove();
                    if (!actionList.querySelector('.action-item-row')) {
                        actionList.innerHTML = '<p class="text-sm text-text-secondary">Belum ada action item.</p>';
                    }
                }).catch(() => window.alert('Action item belum terhapus. Coba lagi.'));
            }
        });
    }

    if (actionAddForm) {
        actionAddForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = actionAddForm.querySelector('input[name="text"]');
            var val = input.value.trim();
            if (!val) return;
            var submitButton = actionAddForm.querySelector('button[type="submit"]');
            var error = document.getElementById('action-item-error');
            submitButton.disabled = true;
            error.classList.add('hidden');
            fetch('/logbook/' + logbookId + '/action-items', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({text: val}),
            }).then(r => { if (!r.ok) throw new Error('Gagal menambah action item.'); return r.json(); }).then(item => {
                input.value = '';
                var empty = actionList.querySelector('p');
                if (empty) empty.remove();
                actionList.appendChild(actionItemRow(item, ownerCanToggle));
            }).catch(() => {
                error.textContent = 'Action item belum tersimpan. Coba lagi.';
                error.classList.remove('hidden');
            }).finally(() => { submitButton.disabled = false; });
        });
    }

    var decisionForm = document.getElementById('review-decision-form');
    if (decisionForm) {
        var decisionChoices = decisionForm.querySelectorAll('input[name="review_decision"]');
        var feedbackWrap = document.getElementById('revision-feedback-wrap');
        var feedback = document.getElementById('revision-feedback');
        function syncDecision() {
            var selected = decisionForm.querySelector('input[name="review_decision"]:checked');
            var needsRevision = selected && selected.value === 'revisi';
            decisionForm.action = needsRevision ? decisionForm.dataset.revisionUrl : decisionForm.dataset.approveUrl;
            feedbackWrap.classList.toggle('hidden', !needsRevision);
            feedback.disabled = !needsRevision;
            feedback.required = !!needsRevision;
        }
        decisionChoices.forEach(choice => choice.addEventListener('change', syncDecision));
        syncDecision();
        decisionForm.addEventListener('submit', function (event) {
            var selected = decisionForm.querySelector('input[name="review_decision"]:checked');
            if (!selected) {
                event.preventDefault();
                decisionChoices[0].focus();
                return;
            }
            if (selected.value === 'approve' && decisionForm.dataset.hasPdf === '1' && decisionForm.dataset.pdfOpened !== '1' &&
                !window.confirm('Lampiran PDF belum dibuka. Tetap setujui entri ini?')) {
                event.preventDefault();
                return;
            }
            var button = decisionForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Memproses...';
            }
        });
    }
</script>
@endsection
