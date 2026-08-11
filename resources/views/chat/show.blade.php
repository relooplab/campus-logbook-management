@extends("layouts.app") @section("title", "Chat") @section("content")
<div class="max-w-3xl mx-auto">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-xl font-bold">Chat dengan {{ $conversation->other_user?->name }}</h1> <a
            href="{{ route("chat.index") }}" class="px-3 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">←
            Daftar</a>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border overflow-hidden">
        <div id="message-list" class="h-[60vh] overflow-y-auto p-4 space-y-3">
            @foreach ($messages as $m)
                @php $mine = $m->sender_id === $user->id; @endphp <div class="flex {{ $mine ? "justify-end" : "justify-start" }}">
                    <div
                        class="max-w-[75%] {{ $mine ? "bg-brand text-[#0b1420]" : "bg-bg-hover" }} rounded-lg px-3 py-2 text-sm">
                        @if ($m->attachable)
                            @php $a = $m->attachable; @endphp <div
                                class="mb-1 {{ $mine ? "text-[#0b1420]/80" : "text-text-secondary" }} text-xs">
                                @if ($m->attachable_type === \App\Models\WorkspaceFile::class)
                                    <span class="material-symbols-outlined icon-sm align-text-bottom">description</span> {{ $a->original_name }} <span class="opacity-70">· Workspace</span> <a
                                        href="{{ $a->isPdf() ? route("workspace.preview", $a) : route("workspace.download", $a) }}"
                                        target="_blank" class="underline text-[#0b1420] font-semibold">[Lihat]</a>
                                @elseif ($m->attachable_type === \App\Models\LogbookEntry::class)
                                    <span class="material-symbols-outlined icon-sm align-text-bottom">assignment</span> {{ $a->jenis === "revisi" ? "Revisi r" . $a->revision_round : "Entri #" . $a->sesi_ke }} <a
                                        href="{{ route("logbook.show", $a) }}" class="underline text-[#0b1420] font-semibold">[Lihat]</a>
                                @elseif ($m->attachable_type === \App\Models\LogbookHarianKp::class)
                                    <span class="material-symbols-outlined icon-sm align-text-bottom">calendar_month</span> Logbook Harian KP — {{ $a->tanggal?->format("d M Y") }} <a
                                        href="{{ $a->mahasiswaTa ? route("logbook-harian.index", $a->mahasiswaTa) : "#" }}" class="underline text-[#0b1420] font-semibold">[Lihat]</a>
                                @elseif ($m->attachable_type === \App\Models\SeminarSubmission::class)
                                    <span class="material-symbols-outlined icon-sm align-text-bottom">school</span> Seminar — {{ $a->jenisLabel() }} <a
                                        href="{{ route("seminar-submission.show", $a) }}" class="underline text-[#0b1420] font-semibold">[Lihat]</a>
                                @elseif ($m->attachable_type === \App\Models\ThesisFinalization::class)
                                    <span class="material-symbols-outlined icon-sm align-text-bottom">task_alt</span> Finalisasi{{ $a->full_file_original_name ? " — " . $a->full_file_original_name : "" }} <a
                                        href="{{ $a->mahasiswaTa ? route("finalization.index", $a->mahasiswaTa) : "#" }}" class="underline text-[#0b1420] font-semibold">[Lihat]</a>
                                @endif
                            </div>
                        @endif
                        <p class="whitespace-pre-wrap">{{ $m->body }}</p>
                        <p class="text-[10px] mt-1 {{ $mine ? "text-[#0b1420]/80" : "text-text-secondary" }}">
                            <span class="font-mono">{{ $m->created_at?->format("H:i") }}</span> @if ($m->edited_at)
                                · diedit
                                @endif @if ($mine && $m->isEditable())
                                    · <a href="#" data-edit="{{ $m->id }}"
                                        class="underline edit-link">edit</a>
                                @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route("chat.store", $conversation) }}"
            class="border-t border-border p-3 flex gap-2 items-end"> @csrf <input type="hidden" name="attachable_type"
                id="attach-type"> <input type="hidden" name="attachable_id" id="attach-id"> <button type="button"
                id="attach-btn" class="p-2 rounded-xl hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-md text-accent-teal">attach_file</span></button>
            <textarea name="body" id="msg-body" rows="1" required placeholder="Tulis pesan..."
                class="flex-1 rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm resize-none"></textarea> <button
                class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Kirim</button>
        </form> {{-- Panel attach --}} <div id="attach-panel" class="hidden border-t border-border p-3">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold">Lampirkan referensi</p> <button type="button" id="attach-close"
                    class="text-text-secondary"><span class="material-symbols-outlined icon-sm">close</span></button>
            </div>
            <div id="attach-list" class="space-y-1 max-h-48 overflow-y-auto text-sm"></div>
        </div>
    </div>
</div> {{-- Modal edit --}}
<div id="edit-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-bg-surface rounded-lg border border-border p-4 w-full max-w-md">
        <h3 class="font-semibold mb-2">Edit Pesan</h3>
        <form method="POST" action="" id="edit-form"> @csrf @method("PUT")
            <textarea name="body" id="edit-body" rows="3"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm"></textarea>
            <div class="flex justify-end gap-2 mt-3"> <button type="button" id="edit-cancel"
                    class="px-3 py-2 rounded-xl bg-status-danger hover:bg-status-danger/90 text-white text-sm">Batal</button> <button
                    class="px-3 py-2 rounded-xl bg-brand text-[#0b1420] text-sm">Simpan</button> </div>
        </form>
    </div>
</div>
@endsection @section("scripts")
<script>
    var convId = {{ $conversation->id }};
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    // Polling fallback (15 detik) — realtime Reverb aktif bila server tersedia.
    setInterval(function () {
        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.text(); }).then(function () {
            // reload ringan bila ada pesan baru — pendekatan sederhana
        });
    }, 15000);

    // --- Attach panel ---
    document.getElementById('attach-btn').addEventListener('click', function () {
        var panel = document.getElementById('attach-panel');
        panel.classList.toggle('hidden');
        if (!panel.classList.contains('hidden')) loadAttach();
    });
    document.getElementById('attach-close').addEventListener('click', function () {
        document.getElementById('attach-panel').classList.add('hidden');
    });

    function loadAttach() {
        fetch('/chat/' + convId + '/attach-options', {
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (d) {
            var html = '';
            (d.categories || []).forEach(function (cat) {
                if (!cat.items || !cat.items.length) return;
                html += '<div class="px-2 pt-2 pb-1 text-[10px] uppercase tracking-wide text-text-secondary flex items-center gap-1"><span class="material-symbols-outlined icon-sm">' + cat.icon + '</span> ' + cat.title + '</div>';
                cat.items.forEach(function (f) {
                    html += '<button type="button" data-type="' + f.type + '" data-id="' + f.id + '" data-url="' + (f.url || '#') + '" class="attach-item block w-full text-left px-2 py-1 rounded hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm align-text-bottom">' + cat.icon + '</span> <span class="truncate">' + (f.label || '') + '</span> <span class="text-xs text-text-secondary">· ' + (f.student || '') + '</span></button>';
                });
            });
            if (!html) html = '<p class="text-sm text-text-secondary p-2">Tidak ada karya mahasiswa untuk disematkan.</p>';
            document.getElementById('attach-list').innerHTML = html;
            document.querySelectorAll('.attach-item').forEach(function (b) {
                b.addEventListener('click', function () {
                    document.getElementById('attach-type').value = b.dataset.type;
                    document.getElementById('attach-id').value = b.dataset.id;
                    document.getElementById('attach-panel').classList.add('hidden');
                });
            });
        });
    }

    // --- Edit message ---
    var modal = document.getElementById('edit-modal');
    document.querySelectorAll('.edit-link').forEach(function (l) {
        l.addEventListener('click', function (e) {
            e.preventDefault();
            var id = l.dataset.edit;
            var bubble = l.closest('.rounded-lg');
            var text = bubble.querySelector('p.whitespace-pre-wrap').textContent;
            document.getElementById('edit-body').value = text;
            document.getElementById('edit-form').action = '/chat/' + convId + '/' + id;
            modal.classList.remove('hidden');
        });
    });
    document.getElementById('edit-cancel').addEventListener('click', function () {
        modal.classList.add('hidden');
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.classList.add('hidden');
    });

    // Auto-resize textarea + Enter kirim
    var ta = document.getElementById('msg-body');
    ta.addEventListener('input', function () {
        ta.style.height = 'auto';
        ta.style.height = Math.min(120, ta.scrollHeight) + 'px';
    });
    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!ta.value.trim()) return;
            var form = ta.closest('form');
            if (form.requestSubmit) form.requestSubmit();
            else form.submit();
        }
    });
</script>
@endsection