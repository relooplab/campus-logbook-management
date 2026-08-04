@extends('layouts.app')

@section('title', 'Detail Entri')

@section('content')
@php
    $user = auth()->user();
    $owner = $user->isMahasiswa() && $logbook->mahasiswaTa?->user_id === $user->id;
    $canReview = $user->can('review', $logbook);
@endphp

<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">
                {{ $logbook->jenis === 'revisi' ? 'Entri Revisi' . ($logbook->revision_round ? ' ke-' . $logbook->revision_round : '') : 'Logbook Sesi ' . $logbook->sesi_ke }}
            </h1>
            <p class="text-sm text-text-secondary mt-0.5">{{ $logbook->mahasiswaTa?->mahasiswa?->name }}</p>
        </div>
        @include('partials.status-badge', ['status' => $logbook->status])
    </div>

    <div class="card p-6 space-y-4">
        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
            <div class="px-3 py-2 rounded-xl bg-bg-panel">
                <dt class="text-text-secondary">Mahasiswa</dt>
                <dd class="font-medium">{{ $logbook->mahasiswaTa?->mahasiswa?->name }}</dd>
            </div>
            <div class="px-3 py-2 rounded-xl bg-bg-panel">
                <dt class="text-text-secondary">
                    {{ $logbook->jenis === 'revisi' ? 'Tanggal Pengiriman Revisi' : 'Tanggal Bimbingan' }}</dt>
                <dd class="font-medium">{{ $logbook->tanggal_tampil?->format('d M Y') ?? '—' }}</dd>
            </div>
            <div class="px-3 py-2 rounded-xl bg-bg-panel">
                <dt class="text-text-secondary">Topik</dt>
                <dd class="font-medium">{{ $logbook->topik ?? 'Revisi' }}</dd>
            </div>
            <div class="px-3 py-2 rounded-xl bg-bg-panel">
                <dt class="text-text-secondary">Dosen</dt>
                <dd class="font-medium">
                    {{ $logbook->dosen?->name ?? ($logbook->mahasiswaTa?->pembimbing1?->name ?? '—') }}</dd>
            </div>
        </dl>

        @if ($logbook->parentEntry)
            <div class="px-4 py-3 rounded-xl bg-brand/10 border border-brand/20 text-sm">
                <p class="font-semibold">Menjawab entri induk #{{ $logbook->parentEntry->id }}</p>
                <a href="{{ route('logbook.show', $logbook->parentEntry) }}" class="text-brand hover:underline">Lihat feedback dan anotasi ronde sebelumnya</a>
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
            <div class="px-4 py-3 rounded-xl bg-status-pending/10 border-l-4 border-status-pending text-sm">
                <div class="flex items-center gap-1.5 mb-1 text-xs font-semibold text-status-pending uppercase tracking-wide"><span class="material-symbols-outlined icon-sm">forum</span> Feedback Dosen</div>
                <div class="text-sm">{{ $logbook->feedback_dosen }}</div>
            </div>
        @endif

        <div class="flex flex-wrap gap-2 text-sm">
            @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path)
                <a href="{{ route('logbook.pdf-viewer', $logbook) }}" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">
                    @if ($canReview)
                        Review PDF & Beri Anotasi
                    @else
                        Lihat PDF & Komentar
                    @endif
                </a>
            @endif
            @if ($logbook->lampiran_path)
                <a href="{{ route('logbook.pdf', $logbook) }}" target="_blank" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Buka PDF di Browser</a>
            @endif
            @if ($logbook->catatan_perbaikan_path)
                <a href="{{ route('logbook.catatan-pdf', $logbook) }}" target="_blank" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Buka Catatan di Browser</a>
            @endif
        </div>
    </div>

    {{-- Actions berdasarkan role & status --}}
    @if ($owner && $logbook->isEditable())
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('logbook.edit', $logbook) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Edit</a>
            <form method="POST" action="{{ route('logbook.submit', $logbook) }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Kirim ke dosen</button>
            </form>
        </div>
    @endif

    @if ($owner && $logbook->status === 'revisi' && !$logbook->isLockedByActiveRevision())
        <a href="{{ route('logbook.create-revisi', ['parent_entry_id' => $logbook->id]) }}" class="inline-block px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Buat Revisi dari Feedback Ini</a>
    @endif

    @if ($canReview && $logbook->status === 'submitted')
        <div class="card p-6 space-y-4">
            <h2 class="font-heading font-semibold text-text-primary">Review</h2>
            @if ($logbook->lampiran_path || $logbook->catatan_perbaikan_path)
                <div class="px-4 py-3 rounded-xl bg-brand/10 border border-brand/20">
                    <p class="text-sm mb-2">Buka PDF, seret untuk menandai area, dan beri komentar sebelum memutuskan.</p>
                    <a href="{{ route('logbook.pdf-viewer', $logbook) }}" class="inline-block px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Buka PDF & Anotasi</a>
                </div>
            @else
                <p class="text-xs text-text-secondary">Tidak ada file PDF pada entri ini untuk dianotasi.</p>
            @endif
            <form method="POST" action="{{ route('logbook.approve', $logbook) }}" id="review-approve-form" class="mb-3"
                data-pdf-opened="{{ $logbook->review_opened_at ? '1' : '0' }}"
                data-has-pdf="{{ $logbook->lampiran_path || $logbook->catatan_perbaikan_path ? '1' : '0' }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90">Setujui (Approve)</button>
            </form>
            <form method="POST" action="{{ route('logbook.request-revisi', $logbook) }}" class="space-y-2">
                @csrf
                <textarea name="feedback_dosen" rows="3" required minlength="20" placeholder="Alasan revisi / feedback wajib diisi (minimal 20 karakter)..."
                    class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('feedback_dosen') }}</textarea>
                @error('feedback_dosen')
                    <p class="text-status-danger text-xs">{{ $message }}</p>
                @enderror
                <button type="submit" class="px-4 py-2 rounded-xl bg-status-danger/10 text-status-danger text-sm font-medium hover:bg-status-danger/20">Minta Revisi</button>
            </form>
        </div>
    @endif
</div>
@endsection

@section('scripts')
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