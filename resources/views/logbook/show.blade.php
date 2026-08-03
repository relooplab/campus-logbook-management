@extends("layouts.app") @section("title", "Detail Entri") @section("content")
@php
    $user = auth()->user();
    $owner = $user->isMahasiswa() && $logbook->mahasiswaTa?->user_id === $user->id;
    $canReview = $user->can("review", $logbook);
@endphp

<div class="max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">
            {{ $logbook->jenis === "revisi" ? "Entri Revisi" . ($logbook->revision_round ? " ke-{$logbook->revision_round}" : "") : "Logbook Sesi " . $logbook->sesi_ke }} </h1>
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
        @if ($logbook->parentEntry)
            <div class="px-4 py-3 rounded-md bg-brand/10 border border-brand/20 text-sm">
                <p class="font-semibold">Menjawab entri induk #{{ $logbook->parentEntry->id }}</p>
                <a href="{{ route("logbook.show", $logbook->parentEntry) }}" class="text-brand hover:underline">Lihat feedback dan anotasi ronde sebelumnya</a>
            </div>
        @endif
        @if ($logbook->revisionChildren->isNotEmpty())
            <div class="px-4 py-3 rounded-md bg-bg-panel border border-border text-sm">
                <p class="font-semibold mb-1">Riwayat revisi</p>
                @foreach ($logbook->revisionChildren as $child)
                    <a href="{{ route("logbook.show", $child) }}" class="block text-brand hover:underline">Revisi {{ $child->revision_round ?: "—" }} · {{ ucfirst($child->status) }}</a>
                @endforeach
            </div>
        @endif
        @if ($logbook->jenis === "revisi")
            <div>
                <h3 class="text-sm font-semibold text-text-secondary mb-1">Pesan untuk Dosen</h3>
                <div class="text-sm whitespace-pre-wrap">{{ $logbook->progres_kendala ?: "—" }}</div>
            </div>
            @if (!empty($logbook->riwayat_perbaikan))
                <div>
                    <h3 class="text-sm font-semibold text-text-secondary mb-2">Catatan Perbaikan</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border border-border">
                            <thead>
                                <tr class="bg-bg-panel text-left text-text-secondary">
                                    <th class="py-2 px-2 border-b border-border w-[6%]">No</th>
                                    <th class="py-2 px-2 border-b border-border w-[16%]">Halaman/Bagian</th>
                                    <th class="py-2 px-2 border-b border-border w-[28%]">Komentar Dosen</th>
                                    <th class="py-2 px-2 border-b border-border w-[34%]">Perbaikan yang Dilakukan</th>
                                    <th class="py-2 px-2 border-b border-border w-[16%]">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logbook->riwayat_perbaikan as $i => $r)
                                    <tr class="border-b border-border">
                                        <td class="py-2 px-2">{{ $i + 1 }}</td>
                                        <td class="py-2 px-2">{{ $r['halaman'] ?? '—' }}</td>
                                        <td class="py-2 px-2">{{ $r['komentar_dosen'] ?? '—' }}</td>
                                        <td class="py-2 px-2">{{ $r['perbaikan'] ?? '—' }}</td>
                                        <td class="py-2 px-2">
                                            @php $status = $r['status'] ?? '—'; @endphp
                                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                                                {{ $status === 'Sudah' ? 'bg-status-success/10 text-status-success' : '' }}
                                                {{ $status === 'Sebagian' ? 'bg-status-pending/10 text-status-pending' : '' }}
                                                {{ $status === 'Belum' ? 'bg-status-danger/10 text-status-danger' : '' }}
                                            ">{{ $status }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <div>
                <h3 class="text-sm font-semibold text-text-secondary mb-1">Ringkasan Perbaikan</h3>
                <div class="text-sm whitespace-pre-wrap">{{ $logbook->progres_kendala }}</div>
            </div>
        @endif
        @if ($logbook->feedback_dosen)
            <div class="px-4 py-3 rounded-md bg-status-pending/10 border-l-4 border-status-pending text-sm">
                <div class="flex items-center gap-1.5 mb-1 text-xs font-semibold text-status-pending uppercase tracking-wide"><span class="material-symbols-outlined icon-sm">forum</span> Feedback Dosen</div><div class="text-sm">{{ $logbook->feedback_dosen }}</div>
            </div>
        @endif
        <div class="flex flex-wrap gap-2 text-sm">
            @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path) <a
                    href="{{ route("logbook.pdf-viewer", $logbook) }}"
                    class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white">
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
    </div> {{-- Actions berdasarkan role & status --}} @if ($owner && $logbook->isEditable())
            <div class="flex flex-wrap gap-2"> <a href="{{ route("logbook.edit", $logbook) }}"
                    class="px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Edit</a>
                <form method="POST" action="{{ route("logbook.submit", $logbook) }}"> @csrf <button
                        class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Kirim ke
                        dosen</button> </form>
            </div>
            @endif
            @if ($owner && $logbook->status === "revisi" && !$logbook->isLockedByActiveRevision())
                <a href="{{ route("logbook.create-revisi", ["parent_entry_id" => $logbook->id]) }}"
                    class="inline-block px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Buat Revisi dari Feedback Ini</a>
            @endif
            @if ($canReview && $logbook->status === "submitted")
                <div class="bg-bg-surface rounded-xl border border-border p-5 space-y-4">
                    <h2 class="font-semibold">Review</h2>
                    @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path)
                        <div class="px-4 py-3 rounded-md bg-brand/10 border border-brand/20">
                            <p class="text-sm mb-2">Buka PDF, seret untuk menandai area, dan beri komentar sebelum
                                memutuskan.</p> <a href="{{ route("logbook.pdf-viewer", $logbook) }}"
                                class="inline-block px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Buka
                                PDF &amp; Anotasi</a>
                        </div>
                    @else
                        <p class="text-xs text-text-secondary">Tidak ada file PDF pada entri ini untuk dianotasi.</p>
                    @endif
                    <form method="POST" action="{{ route("logbook.approve", $logbook) }}" id="review-approve-form" class="mb-3"
                        data-pdf-opened="{{ $logbook->review_opened_at ? "1" : "0" }}"
                        data-has-pdf="{{ $logbook->lampiran_path || $logbook->catatan_perbaikan_path ? "1" : "0" }}"> @csrf
                        <button
                            class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Setujui
                            (Approve)</button>
                    </form>
                    <form method="POST" action="{{ route("logbook.request-revisi", $logbook) }}" class="space-y-2">
                        @csrf
                        <textarea name="feedback_dosen" rows="3" required minlength="20" placeholder="Alasan revisi / feedback wajib diisi (minimal 20 karakter)..."
                            class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("feedback_dosen") }}</textarea>
                        @error("feedback_dosen")
                            <p class="text-status-danger text-xs">{{ $message }}</p>
                        @enderror
                        <button
                            class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Minta
                            Revisi</button>
                    </form>
                </div>
            @endif
</div>
@endsection @section("scripts")
<script>
    var approveForm = document.getElementById('review-approve-form');
    if (approveForm) {
        approveForm.addEventListener('submit', function (event) {
            if (approveForm.dataset.hasPdf === '1' && approveForm.dataset.pdfOpened !== '1' &&
                !window.confirm('Lampiran PDF belum dibuka. Tetap setujui entri ini?')) {
                event.preventDefault();
                return;
            }
            var button = approveForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Memproses...';
            }
        });
    }
</script>
@endsection
