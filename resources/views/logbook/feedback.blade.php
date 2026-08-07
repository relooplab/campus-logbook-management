@extends('layouts.app')

@section('title', 'Umpan Balik Logbook')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Umpan Balik Logbook</h1>
            <p class="text-sm text-text-secondary mt-0.5">Umpan balik dosen & alur revisi dalam satu rangkaian</p>
        </div>
        <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali ke Logbook</a>
    </div>

    @if ($feedbacks->isEmpty())
        <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
            <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">forum</span>
            <p>Belum ada feedback dari dosen.</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($feedbacks as $entry)
                @php
                    $openComments = $entry->comments->where('resolution_status', '!=', \App\Models\PdfComment::STATUS_RESOLVED);
                    $doneItems = $entry->actionItems->where('is_done', true)->count();
                    $totalItems = $entry->actionItems->count();
                    $latestRevision = $entry->revisionChildren->first();
                    $canCreateRevision = $entry->status === \App\Models\LogbookEntry::STATUS_REVISI
                        && $entry->revisionChildren->whereIn('status', ['draft', 'submitted', 'revisi', 'revision_in_progress'])->isEmpty();
                @endphp
                <div class="card p-6">
                    {{-- Header kartu --}}
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                        <div class="flex items-start gap-3">
                            <span class="icon-circle w-10 h-10 bg-brand-light text-brand">
                                <span class="material-symbols-outlined icon-md">forum</span>
                            </span>
                            <div>
                                <p class="font-semibold text-text-primary">
                                    <a href="{{ route('logbook.show', $entry) }}" class="hover:text-brand hover:underline">
                                        {{ $entry->topik ?? ($entry->jenis === 'revisi' ? 'Revisi' : 'Logbook') }}
                                    </a>
                                </p>
                                <p class="text-xs text-text-secondary mt-0.5">
                                    {{ $entry->reviewed_at?->format('d M Y') ?? $entry->tanggal_tampil?->format('d M Y') ?? '—' }}
                                    @if ($entry->dosen)
                                        · {{ $entry->dosen->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @include('partials.status-badge', ['status' => $entry->status, 'entry' => $entry])
                    </div>

                    {{-- Alur: Feedback diterima --}}
                    <div class="space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 w-6 h-6 rounded-full bg-status-success/15 text-status-success flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined icon-sm">check</span>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-primary">Umpan Balik diterima</p>
                                <p class="text-sm text-text-secondary whitespace-pre-wrap mt-0.5">{{ $entry->feedback_dosen }}</p>
                            </div>
                        </div>

                        {{-- Komentar PDF belum diselesaikan --}}
                        @if ($openComments->isNotEmpty())
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 w-6 h-6 rounded-full bg-status-pending/15 text-status-pending flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined icon-sm">comment</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-primary">Komentar belum diselesaikan ({{ $openComments->count() }})</p>
                                    <div class="mt-1.5 space-y-1.5">
                                        @foreach ($openComments as $comment)
                                            <div class="flex items-start gap-2 text-sm bg-bg-panel rounded-lg px-3 py-2">
                                                <span class="text-xs text-text-secondary mt-0.5">Hal. {{ $comment->page_number ?: '—' }}</span>
                                                <span class="flex-1 text-text-primary">{{ $comment->comment }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Action items --}}
                        @if ($totalItems > 0)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 w-6 h-6 rounded-full bg-brand-light text-brand flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined icon-sm">checklist</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-primary">Action Items ({{ $doneItems }}/{{ $totalItems }} selesai)</p>
                                    <div class="mt-1.5 space-y-1.5">
                                        @foreach ($entry->actionItems as $item)
                                            <div class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" class="action-item-toggle rounded bg-bg-surface" data-entry-id="{{ $entry->id }}" data-item-id="{{ $item->id }}" @checked($item->is_done)>
                                                <span class="flex-1 {{ $item->is_done ? 'line-through text-text-secondary' : 'text-text-primary' }}">{{ $item->text }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Perbaikan dilakukan (dari revisi anak) --}}
                        @if ($latestRevision && $latestRevision->riwayat_perbaikan)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 w-6 h-6 rounded-full bg-status-success/15 text-status-success flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined icon-sm">build</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-primary">Perbaikan dilakukan</p>
                                    <div class="mt-1.5 space-y-1.5">
                                        @foreach ($latestRevision->riwayat_perbaikan as $row)
                                            <div class="flex items-start gap-2 text-sm bg-bg-panel rounded-lg px-3 py-2">
                                                <span class="text-xs text-text-secondary mt-0.5 w-16 shrink-0">{{ $row['halaman'] ?? '—' }}</span>
                                                <span class="flex-1 text-text-primary">{{ $row['perbaikan'] ?? '' }}</span>
                                                <span class="text-xs {{ ($row['status'] ?? '') === 'Sudah' ? 'text-status-success' : 'text-status-pending' }} shrink-0">{{ $row['status'] ?? '' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Revisi dikirim --}}
                        @if ($latestRevision)
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 w-6 h-6 rounded-full bg-brand-light text-brand flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined icon-sm">send</span>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-text-primary">
                                        Revisi dikirim
                                        <span class="text-xs text-text-secondary">· {{ $latestRevision->tanggal_pengiriman?->format('d M Y') ?? $latestRevision->submitted_at?->format('d M Y') ?? '—' }}</span>
                                    </p>
                                    <div class="mt-1">
                                        @include('partials.status-badge', ['status' => $latestRevision->status, 'entry' => $latestRevision])
                                    </div>
                                    <a href="{{ route('logbook.show', $latestRevision) }}" class="text-xs text-brand hover:underline mt-1 inline-block">Lihat revisi →</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Aksi --}}
                    <div class="mt-4 pt-4 border-t border-border flex flex-wrap gap-2">
                        @if ($canCreateRevision)
                            <a href="{{ route('logbook.create-revisi', ['parent_entry_id' => $entry->id]) }}"
                                class="px-4 py-2 rounded-xl bg-brand text-white text-sm font-medium hover:opacity-90 inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined icon-sm">edit_note</span> Buat Revisi
                            </a>
                        @endif
                        <a href="{{ route('logbook.show', $entry) }}"
                            class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Lihat Detail</a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // ---- Toggle action items di halaman feedback ----
    document.querySelectorAll('.action-item-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var entryId = checkbox.dataset.entryId;
            var itemId = checkbox.dataset.itemId;
            fetch('/logbook/' + entryId + '/action-items/' + itemId + '/toggle', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
            }).then(r => r.json()).then(data => {
                checkbox.checked = data.is_done;
                var textEl = checkbox.nextElementSibling;
                textEl.classList.toggle('line-through', data.is_done);
                textEl.classList.toggle('text-text-secondary', data.is_done);
            });
        });
    });
</script>
@endsection