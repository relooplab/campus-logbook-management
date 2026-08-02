@extends("layouts.app") @section("title", "Detail Entri") @section("content")
@php
    $user = auth()->user();
    $owner = $user->isMahasiswa() && $logbook->mahasiswaTa?->user_id === $user->id;
    $canReview = $user->isDosen() && $user->hasRole("dosen");
@endphp

<div class="max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">
            {{ $logbook->jenis === "revisi" ? "Entri Revisi" : "Logbook Sesi " . $logbook->sesi_ke }} </h1>
        @include("partials.status-badge", ["status" => $logbook->status])
    </div>
    <div class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
            <div class="px-3 py-2 rounded-md bg-bg-panel">
                <dt class="text-text-secondary">Mahasiswa</dt>
                <dd class="font-medium">{{ $logbook->mahasiswaTa?->mahasiswa?->name }}</dd>
            </div>
            <div class="px-3 py-2 rounded-md bg-bg-panel">
                <dt class="text-text-secondary">
                    {{ $logbook->jenis === "revisi" ? "Tanggal Pengiriman Revisi" : "Tanggal Bimbingan" }}</dt>
                <dd class="font-medium">{{ $logbook->tanggal_tampil?->format("d M Y") ?? "—" }}</dd>
            </div>
            <div class="px-3 py-2 rounded-md bg-bg-panel">
                <dt class="text-text-secondary">Topik</dt>
                <dd class="font-medium">{{ $logbook->topik ?? "Revisi" }}</dd>
            </div>
            <div class="px-3 py-2 rounded-md bg-bg-panel">
                <dt class="text-text-secondary">Dosen</dt>
                <dd class="font-medium">
                    {{ $logbook->dosen?->name ?? ($logbook->mahasiswaTa?->pembimbing1?->name ?? "—") }}</dd>
            </div>
        </dl>
        <div>
            <h3 class="text-sm font-semibold text-text-secondary mb-1">Ringkasan Perbaikan</h3>
            <div class="text-sm whitespace-pre-wrap">{{ $logbook->progres_kendala }}</div>
        </div>
        @if ($logbook->feedback_dosen)
            <div class="px-4 py-3 rounded-md bg-status-pending/10 border border-status-pending/20 text-sm">
                <strong>Feedback Dosen:</strong><br>{{ $logbook->feedback_dosen }}
            </div>
        @endif
        <div class="flex flex-wrap gap-2 text-sm">
            @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path) <a
                    href="{{ route("logbook.pdf-viewer", $logbook) }}"
                    class="px-3 py-2 rounded-md bg-accent-blue hover:bg-accent-blue/90 text-white">
                    @if ($canReview)
                        Review PDF &amp; Beri Anotasi
                    @else
                        Lihat PDF &amp; Komentar
                    @endif
                </a> @endif @if ($logbook->lampiran_path)
                    <a href="{{ route("logbook.pdf", $logbook) }}" target="_blank"
                        class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover">Buka PDF
                        di Browser</a>
                    @endif @if ($logbook->catatan_perbaikan_path)
                        <a href="{{ route("logbook.catatan-pdf", $logbook) }}" target="_blank"
                            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover">Buka
                            Catatan di Browser</a>
                    @endif
        </div>
    </div> {{-- Action items / todo dari feedback (mahasiswa) --}} @if ($owner && $logbook->feedback_dosen)
        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3"><span class="material-symbols-outlined icon-sm align-text-bottom">assignment</span> Action Items (dari feedback)</h2>
            <form class="mb-2 flex gap-2" onsubmit="addActionItem(event)"> @csrf <input type="text" id="ai-input"
                    placeholder="Tulis tugas kecil... (mis. 'Perbaiki sitasi BAB 2')"
                    class="flex-1 rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"> <button
                    class="px-3 py-2 rounded-md bg-accent-blue text-white text-sm">+ Tambah</button> </form>
            <div id="ai-list" class="space-y-1">
                @foreach ($logbook->actionItems as $item)
                    <div class="flex items-center gap-2 px-2 py-1 rounded hover:bg-bg-panel hover:bg-bg-hover">
                        <input type="checkbox" data-id="{{ $item->id }}" {{ $item->is_done ? "checked" : "" }}
                            onchange="toggleActionItem(this)" class="rounded bg-bg-surface"> <span
                            class="text-sm {{ $item->is_done ? "line-through text-text-secondary" : "" }}">{{ $item->text }}</span>
                        <button type="button" data-id="{{ $item->id }}" onclick="delActionItem(this)"
                            class="ml-auto text-status-danger"><span class="material-symbols-outlined icon-sm">close</span></button>
                    </div>
                @endforeach
            </div>
            <p id="ai-done-msg" class="text-xs text-accent-teal mt-2"></p>
        </div> @endif {{-- Actions berdasarkan role & status --}} @if ($owner && $logbook->isEditable())
            <div class="flex flex-wrap gap-2"> <a href="{{ route("logbook.edit", $logbook) }}"
                    class="px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Edit</a>
                <form method="POST" action="{{ route("logbook.submit", $logbook) }}"> @csrf <button
                        class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Kirim ke
                        Pembimbing</button> </form>
            </div>
            @endif @if ($canReview && $logbook->status === "submitted")
                <div class="bg-bg-surface rounded-xl border border-border p-5 space-y-4">
                    <h2 class="font-semibold">Review</h2>
                    @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path)
                        <div class="px-4 py-3 rounded-md bg-accent-blue/10 border border-accent-blue/20">
                            <p class="text-sm mb-2">Buka PDF, seret untuk menandai area, dan beri komentar sebelum
                                memutuskan.</p> <a href="{{ route("logbook.pdf-viewer", $logbook) }}"
                                class="inline-block px-4 py-2 rounded-md bg-accent-blue hover:bg-accent-blue/90 text-white text-sm">Buka
                                PDF &amp; Anotasi</a>
                        </div>
                    @else
                        <p class="text-xs text-text-secondary">Tidak ada file PDF pada entri ini untuk dianotasi.</p>
                    @endif
                    <form method="POST" action="{{ route("logbook.approve", $logbook) }}" class="mb-3"> @csrf
                        <button
                            class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">Setujui
                            (Approve)</button>
                    </form>
                    <form method="POST" action="{{ route("logbook.request-revisi", $logbook) }}" class="space-y-2">
                        @csrf
                        <textarea name="feedback_dosen" rows="3" required placeholder="Alasan revisi / feedback wajib diisi..."
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm"></textarea> <button
                            class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Minta
                            Revisi</button>
                    </form>
                </div>
            @endif
</div>
@endsection @section("scripts")
<script>
    var entryId = {{ $logbook->id }};
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    function addActionItem(e) {
        e.preventDefault();
        var input = document.getElementById('ai-input');
        var text = input.value.trim();
        if (!text) return;
        fetch('/logbook/' + entryId + '/action-items', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                text: text
            })
        }).then(function(r) {
            return r.json();
        }).then(function(item) {
            input.value = '';
            location.reload();
        });
    }

    function toggleActionItem(cb) {
        var id = cb.dataset.id;
        fetch('/logbook/' + entryId + '/action-items/' + id + '/toggle', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function(r) {
            return r.json();
        }).then(function(d) {
            cb.nextElementSibling.classList.toggle('line-through', d.is_done);
            cb.nextElementSibling.classList.toggle('text-text-secondary', d.is_done);
            if (d.all_done) {
                document.getElementById('ai-done-msg').innerHTML =
                    '<span class="material-symbols-outlined icon-sm align-text-bottom">check_circle</span> Semua tugas selesai — siap kirim revisi ke pembimbing!';
            } else {
                document.getElementById('ai-done-msg').innerHTML = '';
            }
        });
    }

    function delActionItem(btn) {
        var id = btn.dataset.id;
        fetch('/logbook/' + entryId + '/action-items/' + id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function() {
            location.reload();
        });
    }
</script>
@endsection
