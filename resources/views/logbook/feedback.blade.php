@extends('layouts.app')

@section('title', 'Logbook Feedback')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Logbook Feedback</h1>
            <p class="text-sm text-text-secondary mt-0.5">Umpan balik dari dosen pembimbing</p>
        </div>
        <a href="{{ route('logbook.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Kembali ke Logbook</a>
    </div>

    @if ($feedbacks->isEmpty())
        <div class="px-4 py-10 rounded-xl bg-bg-panel border border-border text-center text-text-secondary">
            <span class="material-symbols-outlined icon-lg mb-2 text-text-secondary/50">forum</span>
            <p>Belum ada feedback dari dosen.</p>
        </div>
    @else
        <div class="card p-0 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Topik</th>
                        <th class="py-3 px-4">Feedback</th>
                        <th class="py-3 px-4">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($feedbacks as $entry)
                        <tr class="border-b border-border last:border-0 align-top hover:bg-bg-panel/50">
                            <td class="py-3 px-4 whitespace-nowrap">
                                {{ $entry->reviewed_at?->format('d M Y') ?? $entry->tanggal_tampil?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route('logbook.show', $entry) }}" class="text-brand hover:underline">
                                    {{ $entry->topik ?? ($entry->jenis === 'revisi' ? 'Revisi' : 'Logbook') }}
                                </a>
                                @if ($entry->dosen)
                                    <p class="text-xs text-text-secondary mt-0.5">{{ $entry->dosen->name }}</p>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-pre-wrap max-w-md">{{ $entry->feedback_dosen }}</td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route('logbook.feedback-note', $entry) }}" class="space-y-1">
                                    @csrf
                                    @method('PUT')
                                    <textarea name="feedback_note" rows="2" maxlength="2000"
                                        placeholder="Catatan Anda (opsional)..."
                                        class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">{{ old('feedback_note', $entry->feedback_note) }}</textarea>
                                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-brand text-white text-xs font-medium hover:opacity-90">Simpan Note</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection