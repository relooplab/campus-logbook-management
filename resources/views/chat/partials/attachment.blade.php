@if ($m->attachable)
    @php $a = $m->attachable; $link = null; $label = null; @endphp
    @if ($a instanceof \App\Models\WorkspaceFile)
        @php $link = $a->isPdf() ? route('workspace.preview', $a) : route('workspace.download', $a); $label = $a->original_name; @endphp
    @elseif ($a instanceof \App\Models\LogbookEntry)
        @php $link = route('logbook.show', $a); $label = $a->jenis === 'revisi' ? 'Revisi r'.$a->revision_round : 'Entri #'.$a->sesi_ke; @endphp
    @elseif ($a instanceof \App\Models\LogbookHarianKp && $a->mahasiswaTa)
        @php $link = route('logbook-harian.index', $a->mahasiswaTa); $label = 'Logbook Harian KP · '.$a->tanggal?->format('d M Y'); @endphp
    @elseif ($a instanceof \App\Models\SeminarSubmission)
        @php $link = route('seminar-submission.show', $a); $label = 'Seminar · '.$a->jenisLabel(); @endphp
    @elseif ($a instanceof \App\Models\ThesisFinalization && $a->mahasiswaTa)
        @php $link = route('finalization.index', $a->mahasiswaTa); $label = 'Finalisasi'.($a->full_file_original_name ? ' · '.$a->full_file_original_name : ''); @endphp
    @endif
    @if ($link)<a href="{{ $link }}" class="mb-2 flex min-w-0 items-center gap-2 rounded-control border border-border bg-bg-surface/70 p-2 text-xs font-semibold text-text-primary underline decoration-brand hover:bg-bg-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><span class="material-symbols-outlined icon-sm text-brand" aria-hidden="true">description</span><span class="min-w-0 break-all">{{ $label }}</span><span class="sr-only">Lihat referensi</span></a>@endif
@endif