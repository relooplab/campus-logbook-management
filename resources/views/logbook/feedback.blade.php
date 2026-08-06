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
                        <th class="py-3 px-4">Action Items</th>
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
                            <td class="py-3 px-4 min-w-[200px]">
                                <div class="action-items-list space-y-1.5" data-entry-id="{{ $entry->id }}">
                                    @forelse ($entry->actionItems as $item)
                                        <div class="flex items-center gap-2 action-item-row" data-item-id="{{ $item->id }}">
                                            <input type="checkbox" class="action-item-toggle rounded bg-bg-surface" @checked($item->is_done)>
                                            <span class="flex-1 text-xs {{ $item->is_done ? 'line-through text-text-secondary' : '' }}">{{ $item->text }}</span>
                                            <button type="button" class="action-item-delete text-status-danger hover:underline text-[10px]">Hapus</button>
                                        </div>
                                    @empty
                                        <p class="text-xs text-text-secondary">Belum ada action item.</p>
                                    @endforelse
                                </div>
                                <form class="action-item-add-form flex gap-1.5 mt-2">
                                    @csrf
                                    <input type="text" name="text" placeholder="Tambah..." maxlength="500"
                                        class="flex-1 rounded-lg border border-border bg-bg-surface px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand/40">
                                    <button type="submit" class="px-2 py-1 rounded-lg bg-brand text-white text-xs font-medium hover:opacity-90">+</button>
                                </form>
                            </td>
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

@section('scripts')
<script>
    // ---- Action items di halaman feedback ----
    function actionItemRow(item) {
        var row = document.createElement('div');
        row.className = 'flex items-center gap-2 action-item-row';
        row.dataset.itemId = item.id;
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'action-item-toggle rounded bg-bg-surface';
        checkbox.checked = !!item.is_done;
        var text = document.createElement('span');
        text.className = 'flex-1 text-xs' + (item.is_done ? ' line-through text-text-secondary' : '');
        text.textContent = item.text;
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'action-item-delete text-status-danger hover:underline text-[10px]';
        del.textContent = 'Hapus';
        row.appendChild(checkbox);
        row.appendChild(text);
        row.appendChild(del);
        return row;
    }

    document.querySelectorAll('.action-items-list').forEach(function (list) {
        var entryId = list.dataset.entryId;

        // Toggle
        list.addEventListener('change', function (e) {
            if (e.target.classList.contains('action-item-toggle')) {
                var row = e.target.closest('.action-item-row');
                var id = row.dataset.itemId;
                fetch('/logbook/' + entryId + '/action-items/' + id + '/toggle', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                }).then(r => r.json()).then(data => {
                    e.target.checked = data.is_done;
                    var textEl = row.querySelector('span');
                    textEl.classList.toggle('line-through', data.is_done);
                    textEl.classList.toggle('text-text-secondary', data.is_done);
                });
            }
        });

        // Delete
        list.addEventListener('click', function (e) {
            if (e.target.classList.contains('action-item-delete')) {
                var row = e.target.closest('.action-item-row');
                var id = row.dataset.itemId;
                if (!confirm('Hapus action item ini?')) return;
                fetch('/logbook/' + entryId + '/action-items/' + id, {
                    method: 'DELETE',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                }).then(r => r.json()).then(() => {
                    row.remove();
                    if (!list.querySelector('.action-item-row')) {
                        list.innerHTML = '<p class="text-xs text-text-secondary">Belum ada action item.</p>';
                    }
                });
            }
        });
    });

    // Add form
    document.querySelectorAll('.action-item-add-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var list = form.closest('td').querySelector('.action-items-list');
            var entryId = list.dataset.entryId;
            var input = form.querySelector('input[name="text"]');
            var val = input.value.trim();
            if (!val) return;
            fetch('/logbook/' + entryId + '/action-items', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify({text: val}),
            }).then(r => r.json()).then(item => {
                input.value = '';
                var empty = list.querySelector('p');
                if (empty) empty.remove();
                list.appendChild(actionItemRow(item));
            });
        });
    });
</script>
@endsection
