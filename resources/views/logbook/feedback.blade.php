@extends("layouts.app") @section("title", "Logbook Feedback") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Logbook Feedback</h1>
        <a href="{{ route("logbook.index") }}"
            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">← Kembali ke Logbook</a>
    </div>

    @if ($feedbacks->isEmpty())
        <div class="px-4 py-6 rounded-lg bg-bg-surface border border-border text-text-secondary">
            Belum ada feedback dari dosen.
        </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
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
                        <tr class="border-b border-border align-top">
                            <td class="py-3 px-4 whitespace-nowrap">
                                {{ $entry->reviewed_at?->format("d M Y") ?? $entry->tanggal_tampil?->format("d M Y") ?? "—" }}
                            </td>
                            <td class="py-3 px-4">
                                <a href="{{ route("logbook.show", $entry) }}" class="text-brand hover:underline">
                                    {{ $entry->topik ?? ($entry->jenis === "revisi" ? "Revisi" : "Logbook") }}
                                </a>
                                @if ($entry->dosen)
                                    <p class="text-xs text-text-secondary mt-0.5">{{ $entry->dosen->name }}</p>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-pre-wrap max-w-md">{{ $entry->feedback_dosen }}</td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route("logbook.feedback-note", $entry) }}" class="space-y-1">
                                    @csrf
                                    @method("PUT")
                                    <textarea name="feedback_note" rows="2" maxlength="2000"
                                        placeholder="Catatan Anda (opsional)..."
                                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">{{ old("feedback_note", $entry->feedback_note) }}</textarea>
                                    <button type="submit"
                                        class="px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-xs">Simpan Note</button>
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