@extends("layouts.app") @section("title", "Quick Review") @section("content")
@if (!$entry)
    <div class="max-w-2xl mx-auto text-center py-16">
        <h1 class="text-2xl font-bold">Quick Review</h1>
        <p class="text-text-secondary mt-2">Tidak ada entri menunggu review. <span class="material-symbols-outlined icon-sm align-text-bottom">celebration</span></p> <a href="{{ route("dashboard") }}"
            class="inline-block mt-4 px-4 py-2 rounded-md bg-accent-teal text-white text-sm">← Dashboard</a>
    </div>
@else
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold">Quick Review —
                {{ $entry->jenis === "revisi" ? "Revisi" : "Sesi " . $entry->sesi_ke }}</h1> <a
                href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">←
                Dashboard</a>
        </div> {{-- Ringkasan entry --}} <div class="bg-bg-surface rounded-xl border border-border p-5 space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-lg">{{ $entry->mahasiswaTa?->mahasiswa?->name }}</p>
                    <p class="text-sm text-text-secondary">{{ $entry->mahasiswaTa?->judul_ta }}</p>
                </div> @include("partials.status-badge", ["status" => $entry->status])
            </div>
            <dl class="grid sm:grid-cols-2 gap-2 text-sm">
                <div class="px-3 py-2 rounded-md bg-bg-panel">
                    <dt class="text-text-secondary">Tanggal</dt>
                    <dd>{{ $entry->tanggal_bimbingan?->format("d M Y") ?? "—" }}</dd>
                </div>
                <div class="px-3 py-2 rounded-md bg-bg-panel">
                    <dt class="text-text-secondary">Topik</dt>
                    <dd>{{ $entry->topik ?? "Revisi" }}</dd>
                </div>
            </dl>
            <div>
                <h3 class="text-sm font-semibold text-text-secondary mb-1">Ringkasan Perbaikan</h3>
                <div class="text-sm whitespace-pre-wrap">{{ $entry->progres_kendala }}</div>
            </div>
            @if ($entry->lampiran_path || $entry->catatan_perbaikan_path)
                <a href="{{ route("logbook.pdf-viewer", $entry) }}" target="_blank"
                    class="inline-block px-3 py-2 rounded-md bg-accent-blue text-white text-sm">Lihat PDF &
                    Anotasi</a>
            @endif
        </div> {{-- Feedback terakhir untuk mahasiswa ini (reuse) --}} @if ($lastFeedback)
            <div class="bg-bg-surface rounded-xl border border-border p-4">
                <p class="text-xs font-semibold text-text-secondary uppercase mb-1">Feedback terakhir untuk mahasiswa
                    ini</p> <button type="button" id="use-last"
                    class="text-left text-sm hover:text-accent-teal whitespace-pre-wrap">{{ $lastFeedback }}</button>
            </div>
        @endif {{-- Template feedback --}} <div
            class="bg-bg-surface rounded-xl border border-border p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold text-text-secondary uppercase">Template Feedback</p> <button
                    type="button" id="new-tpl" class="text-xs text-accent-teal hover:underline">+ Simpan feedback
                    sebagai template</button>
            </div>
            <div class="flex flex-wrap gap-2" id="tpl-list">
                @foreach ($templates as $t)
                    <button type="button" data-body="{{ $t->body }}"
                        class="tpl-chip px-3 py-1.5 rounded-full bg-bg-hover text-xs hover:bg-bg-hover hover:bg-bg-hover">
                        {{ $t->title ?: \Illuminate\Support\Str::limit($t->body, 40) }} </button>
                    @endforeach @if ($templates->isEmpty())
                        <span class="text-xs text-text-secondary">Belum ada template.</span>
                    @endif
            </div>
        </div> {{-- Form review --}} <div class="bg-bg-surface rounded-xl border border-border p-5 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Feedback</h2> <button type="button" id="build-feedback"
                    class="text-xs px-3 py-1.5 rounded-md bg-accent-blue/10 text-accent-blue">
                    <span class="material-symbols-outlined icon-sm align-text-bottom">bolt</span> Jadikan dari Komentar </button>
            </div>
            <textarea name="feedback_dosen" id="feedback_dosen" rows="4" required
                placeholder="Tulis feedback / alasan revisi..."
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ $feedbackDraft ?? "" }}</textarea>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route("quick-review.approve-next", $entry) }}"> @csrf <button type="submit"
                        class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm font-semibold"><span class="material-symbols-outlined icon-sm align-text-bottom">check</span>
                        Setujui & Next</button> </form>
                <form method="POST" action="{{ route("quick-review.revisi-next", $entry) }}" id="revisi-form">
                    @csrf <input type="hidden" name="feedback_dosen" id="revisi-feedback">
                    <button type="submit" id="revisi-btn"
                        class="px-4 py-2 rounded-md bg-status-pending hover:bg-status-pending/90 text-white text-sm font-semibold"><span class="material-symbols-outlined icon-sm align-text-bottom">autorenew</span>
                        Revisi & Next</button>
                </form> <a href="{{ route("logbook.show", $entry) }}"
                    class="px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Detail
                    penuh</a>
            </div>
        </div>
    </div>
@endif {{-- Modal simpan template --}}
<div id="tpl-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-md">
        <h3 class="font-semibold mb-3">Simpan sebagai Template Feedback</h3>
        <div class="space-y-3"> <input type="text" id="tpl-title" placeholder="Judul (opsional)"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            <textarea id="tpl-body" rows="3" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"
                placeholder="Isi feedback..."></textarea>
        </div>
        <div class="flex justify-end gap-2 mt-4"> <button type="button" id="tpl-cancel"
                class="px-3 py-2 rounded-md bg-bg-panel text-sm">Batal</button> <button type="button" id="tpl-save"
                class="px-3 py-2 rounded-md bg-accent-teal text-white text-sm">Simpan</button> </div>
    </div>
</div>
@endsection @section("scripts")
@if ($entry)
<script>
    (function() {
        var feedback = document.getElementById('feedback_dosen');
        var csrf = document.querySelector('meta[name="csrf-token"]').content;
        var revisiForm = document.getElementById('revisi-form');
        var revisiBtn = document.getElementById('revisi-btn');
        var revisiFeedback = document.getElementById('revisi-feedback');

        // Pakai feedback terakhir.
        var useLast = document.getElementById('use-last');
        if (useLast) {
            useLast.addEventListener('click', function () {
                feedback.value = useLast.textContent.trim();
            });
        }

        // Template chips.
        document.querySelectorAll('.tpl-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                feedback.value = chip.dataset.body;
            });
        });

        // Revisi & next: salin isi textarea ke input hidden lalu submit form.
        if (revisiBtn && revisiForm && revisiFeedback) {
            revisiBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!feedback.value.trim()) {
                    alert('Feedback wajib diisi.');
                    return;
                }
                revisiFeedback.value = feedback.value;
                revisiForm.submit();
            });
        }

        // Build feedback dari komentar unresolved.
        var buildBtn = document.getElementById('build-feedback');
        if (buildBtn) {
            buildBtn.addEventListener('click', function () {
                fetch('/quick-review/{{ $entry->id }}/build-feedback', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.json(); })
                  .then(function (d) { feedback.value = d.feedback || ''; })
                  .catch(function () { alert('Gagal memuat komentar.'); });
            });
        }

        // Modal simpan template.
        var modal = document.getElementById('tpl-modal');
        var newTpl = document.getElementById('new-tpl');
        var tplTitle = document.getElementById('tpl-title');
        var tplBody = document.getElementById('tpl-body');
        var tplCancel = document.getElementById('tpl-cancel');
        var tplSave = document.getElementById('tpl-save');

        if (newTpl) {
            newTpl.addEventListener('click', function () {
                tplBody.value = feedback.value;
                modal.classList.remove('hidden');
            });
        }
        if (tplCancel) {
            tplCancel.addEventListener('click', function () {
                modal.classList.add('hidden');
            });
        }
        if (tplSave) {
            tplSave.addEventListener('click', function () {
                var body = tplBody.value.trim();
                if (!body) return;
                var title = tplTitle.value.trim();
                fetch('/feedback-templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ title: title, body: body })
                }).then(function () {
                    modal.classList.add('hidden');
                    window.location.reload();
                });
            });
        }
    })();
</script>
@endif
@endsection
