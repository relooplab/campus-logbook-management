@extends('layouts.app')

@section('title', 'Detail '.$submission->jenisLabel())

@section('content')
@php
    // Lokasi dan tautan masih disimpan dalam satu field. Pisahkan hanya untuk tampilan.
    $rawLocation = (string) ($submission->lokasi ?? '');
    preg_match_all('~https?://[^\s<>()]+~iu', $rawLocation, $locationMatches);
    $meetingLinks = [];
    foreach ($locationMatches[0] as $match) {
        $url = rtrim($match, '.,;!?');
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $meetingLinks[$url] = $url;
        }
    }
    $locationText = trim(str_replace($locationMatches[0], '', $rawLocation), " \t\n\r\0\x0B,;()");
@endphp

<div class="detail-workspace space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-heading font-bold text-2xl text-text-primary">Detail {{ $submission->jenisLabel() }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-text-secondary">
                <span class="font-medium text-text-primary">{{ $submission->mahasiswaTa->mahasiswa?->name }}</span>
                <span>{{ $submission->tanggal?->format('d M Y') ?? '—' }} · {{ $submission->waktu?->format('H:i') ?? '—' }}</span>
                <span class="badge badge-info">{{ $submission->statusLabel() }}</span>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($isMember && $submission->isUpdatableByStudent())
                <a href="{{ route('seminar-submission.edit', $submission) }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Edit</a>
            @endif
            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Dashboard</a>
        </div>
    </div>

    <div class="detail-workspace-grid">
        <div class="space-y-5 min-w-0">
            <section class="card p-5 sm:p-6" aria-labelledby="seminar-schedule-title">
                <h2 id="seminar-schedule-title" class="font-heading font-semibold text-text-primary mb-4">Jadwal {{ $submission->jenisLabel() }}</h2>
                <div class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-bg-panel p-4">
                        <p class="text-xs text-text-secondary mb-1">Tanggal</p>
                        <p class="font-semibold text-text-primary">{{ $submission->tanggal?->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-bg-panel p-4">
                        <p class="text-xs text-text-secondary mb-1">Waktu</p>
                        <p class="font-semibold text-text-primary">{{ $submission->waktu?->format('H:i') ?? '—' }}</p>
                    </div>
                    <div class="rounded-xl bg-bg-panel p-4 sm:col-span-2 detail-workspace-card">
                        <p class="text-xs text-text-secondary mb-1">Lokasi</p>
                        <p class="font-medium text-text-primary">{{ $locationText !== '' ? $locationText : ($meetingLinks ? 'Pertemuan daring' : ($rawLocation ?: '—')) }}</p>
                    </div>
                </div>
                @if ($meetingLinks)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($meetingLinks as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90 detail-workspace-card">
                                <span class="material-symbols-outlined icon-sm">open_in_new</span> Buka Pertemuan{{ count($meetingLinks) > 1 ? ' '. $loop->iteration : '' }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card p-5 sm:p-6 space-y-4" aria-labelledby="seminar-documents-title">
                <h2 id="seminar-documents-title" class="font-heading font-semibold text-text-primary">Dokumen</h2>
                <article class="rounded-xl bg-bg-panel border border-border p-4 detail-workspace-card">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-accent-blue">description</span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-text-primary">Surat Undangan</h3>
                            <p class="text-sm text-text-primary mt-1">{{ $submission->undangan_original_name ?: 'Dokumen undangan' }}</p>
                            <p class="text-xs text-text-secondary mt-1">Diundang: {{ $submission->undanganKepadaLabel() }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($submission->isUndanganPdf())
                            <a href="{{ route('seminar-submission.undangan-preview', $submission) }}" target="_blank" rel="noopener noreferrer" class="px-3 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Buka PDF</a>
                        @endif
                        <a href="{{ route('seminar-submission.undangan-download', $submission) }}" class="px-3 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Download</a>
                    </div>
                </article>
                @if ($submission->materi_path)
                    <article class="rounded-xl bg-bg-panel border border-border p-4 detail-workspace-card">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-accent-orange">picture_as_pdf</span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-text-primary">Dokumen Materi</h3>
                                <p class="text-sm text-text-primary mt-1">{{ $submission->materi_original_name ?: 'Dokumen materi' }}</p>
                                <p class="text-xs text-text-secondary mt-1">{{ $submission->materiFromWorkspace() ? 'Dari workspace' : 'Upload baru' }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($submission->isMateriPdf())
                                <a href="{{ route('seminar-submission.materi-preview', $submission) }}" target="_blank" rel="noopener noreferrer" class="px-3 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Buka PDF</a>
                            @endif
                            <a href="{{ route('seminar-submission.materi-download', $submission) }}" class="px-3 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Download</a>
                        </div>
                    </article>
                @else
                    <p class="text-sm text-text-secondary">Belum ada dokumen materi.</p>
                @endif
            </section>

            @if ($submission->catatan_keterangan)
                <section class="card p-5 sm:p-6 detail-workspace-card">
                    <h2 class="font-heading font-semibold text-text-primary mb-2">Catatan Keterangan</h2>
                    <p class="text-sm text-text-secondary whitespace-pre-line">{{ $submission->catatan_keterangan }}</p>
                </section>
            @endif

            @if ($submission->sidang_id && $submission->sidang)
                @php $sidangR = $submission->sidang; @endphp
                <section class="card p-5 sm:p-6">
                    <h2 class="font-heading font-semibold text-text-primary mb-3">Hasil & Nilai {{ $sidangR->jenisLabel() }}</h2>
                    <p class="text-sm mb-2">Hasil: <span class="font-medium">{{ $sidangR->hasilLabel() }}</span></p>
                    @php $grades = $sidangR->loadMissing('grades.user')->grades; @endphp
                    @if ($grades->isNotEmpty())
                        <ul class="space-y-1 text-sm">
                            @foreach ($grades as $g)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $g->user?->name }} <span class="text-xs text-text-secondary">({{ ucfirst($g->role) }})</span></span>
                                    <span class="font-medium">{{ $g->filled_at ? $g->nilai : 'Belum dinilai' }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if ($sidangR->nilaiFinal() !== null)
                            <p class="mt-2 font-semibold text-text-primary">Rerata: {{ $sidangR->nilaiFinal() }}</p>
                        @else
                            <p class="mt-2 text-xs text-text-secondary">Nilai belum lengkap — menunggu dosen terkait melengkapi.</p>
                        @endif
                    @else
                        <p class="text-xs text-text-secondary">Belum ada penilaian dicatat untuk sidang ini.</p>
                    @endif
                </section>
            @endif
        </div>

        <aside class="detail-workspace-panel space-y-4" aria-label="Ringkasan dan tindakan seminar">
            <section class="card p-5 space-y-2 detail-workspace-card">
                <h2 class="font-heading font-semibold text-text-primary">Ringkasan</h2>
                <p class="text-sm font-medium">{{ $submission->jenisLabel() }}</p>
                <p class="text-sm text-text-secondary">{{ $submission->tanggal?->format('d M Y') ?? '—' }} · {{ $submission->waktu?->format('H:i') ?? '—' }}</p>
                <p class="text-sm text-text-secondary">{{ $locationText !== '' ? $locationText : ($meetingLinks ? 'Pertemuan daring' : ($rawLocation ?: '—')) }}</p>
            </section>

            <section class="card p-5 detail-workspace-card">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Catatan Hardcopy</h2>
                @if ($isDosen)
                    <form method="POST" action="{{ route('seminar-submission.hardcopy-note', $submission) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <label for="catatan-hardcopy" class="sr-only">Catatan Hardcopy</label>
                        <textarea id="catatan-hardcopy" name="catatan_hardcopy" rows="4" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('catatan_hardcopy', $submission->catatan_hardcopy) }}</textarea>
                        @error('catatan_hardcopy') <p class="text-status-danger text-xs">{{ $message }}</p> @enderror
                        <button type="submit" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Simpan Catatan</button>
                    </form>
                @else
                    <p class="text-sm text-text-secondary whitespace-pre-line">{{ $submission->catatan_hardcopy ?: 'Belum ada catatan.' }}</p>
                @endif
            </section>

            @if ($isDosen && !$submission->sidang_id)
                <section class="card p-5 detail-workspace-card">
                    <h2 class="font-heading font-semibold text-text-primary mb-2">Setelah Seminar</h2>
                    <p class="text-sm text-text-secondary mb-3">Setelah sidang/seminar berlangsung, catat hasilnya ke Riwayat Sidang. Pembimbing & penguji akan mengisi nilai.</p>
                    <a href="{{ route('dosen-sidang.index', ['submission' => $submission->id]) }}" class="inline-block px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Catat Hasil Sidang / Seminar</a>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection
