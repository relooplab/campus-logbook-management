@extends('layouts.app')

@section('title', 'Langganan Direktori')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Langganan Direktori</h1>
            <p class="text-sm text-text-secondary">Kelola langganan plan untuk node direktori (universitas/fakultas/departemen/prodi). Langganan di satu node otomatis meng-cover semua node di bawahnya.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.system.directory') }}"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm font-medium transition-colors">
                <span class="material-symbols-outlined icon-md">account_tree</span>
                Kelola Struktur Direktori
            </a>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Scope</th>
                        <th class="py-3 px-4">Pool Kuota</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Mulai &amp; Berakhir</th>
                        <th class="py-3 px-4">Dibuat oleh</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $sub)
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-bg-panel mr-1">{{ $sub->scopeLabel() }}</span>
                                <div class="mt-1">
                                    <span class="text-text-primary font-medium">{{ $sub->scopeName() }}</span>
                                    <span class="text-xs text-text-secondary">#{{ $sub->scope_id }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-text-primary font-medium">{{ number_format($sub->poolLimitMb()) }} MB</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs {{ $sub->isActive() ? 'bg-status-success/10 text-status-success' : 'bg-status-danger/10 text-status-danger' }}">
                                    {{ ucfirst($sub->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-text-primary">{{ $sub->starts_at?->format('d M Y') ?? '—' }}</div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-text-secondary">s/d {{ $sub->ends_at?->format('d M Y') ?? 'selamanya' }}</span>
                                    @if ($sub->isActive() && $sub->ends_at)
                                        @php
                                            $sisaHari = (int) now()->startOfDay()->diffInDays($sub->ends_at->startOfDay(), false);
                                        @endphp
                                        @if ($sisaHari >= 0)
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-status-pending/10 text-status-pending">sisa {{ $sisaHari }} hari</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] bg-status-danger/10 text-status-danger">lewat {{ abs($sisaHari) }} hari</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4">{{ $sub->assignedBy?->name ?? '—' }}</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('admin.system.directory-subscriptions.edit', $sub) }}" class="text-brand hover:underline text-xs">Edit</a>
                                    @if ($sub->isActive())
                                        <form method="POST" action="{{ route('admin.system.directory-subscriptions.cancel', $sub) }}"
                                            onsubmit="return confirm('Batalkan langganan ini?')" class="inline">
                                            @csrf
                                            <button class="text-status-danger hover:underline text-xs">Batalkan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-text-secondary">Belum ada langganan direktori.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        <div class="bg-bg-surface rounded-xl border border-border p-5 h-fit">
            <h2 class="font-semibold mb-3">Assign Langganan</h2>
            <form method="POST" action="{{ route('admin.system.directory-subscriptions.store') }}" class="space-y-3">
                @csrf

                <input type="hidden" name="scope_type" id="scope-type" value="university">

                <div>
                    <label class="block text-sm mb-1">Node</label>
                    <select name="scope_id" id="scope-id" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                        @foreach ($universities as $univ)
                            <optgroup label="{{ $univ->name }}">
                                <option value="{{ $univ->id }}" data-type="university">{{ $univ->name }}</option>
                                @foreach ($univ->faculties as $faculty)
                                    <option value="{{ $faculty->id }}" data-type="faculty">↳ {{ $faculty->name }}</option>
                                    @foreach ($faculty->departments as $dept)
                                        <option value="{{ $dept->id }}" data-type="department">&nbsp;&nbsp;↳ {{ $dept->name }}</option>
                                        @foreach ($dept->studyPrograms as $prodi)
                                            <option value="{{ $prodi->id }}" data-type="study_program">&nbsp;&nbsp;&nbsp;&nbsp;↳ {{ $prodi->name }}</option>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm mb-1">Pool Kuota (MB) — input bebas</label>
                    <input type="number" name="storage_limit_mb" min="1" max="1048576" required
                        placeholder="mis. 5120"
                        class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <p class="text-xs text-text-secondary mt-1">Nilai pool langsung, tidak terikat plan.</p>
                </div>

                <div>
                    <label class="block text-sm mb-1">Berakhir (opsional)</label>
                    <input type="date" name="ends_at" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>

                <button class="w-full px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">Assign</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function() {
        var typeInput = document.getElementById('scope-type');
        var idSelect = document.getElementById('scope-id');

        function sync() {
            var type = typeInput.value;
            Array.from(idSelect.options).forEach(function(opt) {
                opt.hidden = opt.dataset.type !== type;
            });
            // Pilih option pertama yang terlihat.
            var firstVisible = Array.from(idSelect.options).find(function(opt) { return !opt.hidden; });
            if (firstVisible) idSelect.value = firstVisible.value;
        }

        idSelect.addEventListener('change', function() {
            var selected = idSelect.options[idSelect.selectedIndex];
            if (selected) typeInput.value = selected.dataset.type;
        });

        sync();
    })();
</script>
@endsection
