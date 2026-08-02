@extends("layouts.app") @section("title", "Detail " . ($mahasiswaTa->mahasiswa?->name ?? "Mahasiswa")) @section("content")
@php
    $user = auth()->user();
    $isDosen = $user->isDosen();
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Detail Mahasiswa</h1> <a href="{{ url()->previous() }}"
            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">← Kembali</a>
    </div> {{-- Kartu profil mahasiswa --}} <div
        class="bg-bg-surface rounded-xl border border-border p-6 flex flex-wrap items-center gap-4">
        <div
            class="h-16 w-16 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-xl font-bold flex-shrink-0">
            @if ($mahasiswaTa->mahasiswa?->photoUrl())
                <img src="{{ $mahasiswaTa->mahasiswa->photoUrl() }}" class="h-full w-full object-cover" alt="Foto">
            @else
                {{ $mahasiswaTa->mahasiswa?->initials() }}
            @endif
        </div>
        <div class="flex-1">
            <h2 class="font-semibold text-lg">{{ $mahasiswaTa->mahasiswa?->name }}</h2>
            <p class="text-sm text-text-secondary">{{ $mahasiswaTa->mahasiswa?->email }} ·
                {{ $mahasiswaTa->mahasiswa?->identifier }}</p>
            <p class="text-sm text-text-primary mt-1">{{ $mahasiswaTa->judul_ta }}</p>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-brand">{{ $approved }}/{{ $target }}</div>
            <div class="text-xs text-text-secondary">sesi disetujui</div>
        </div>
        <div class="flex flex-wrap gap-2"> <a
                href="{{ route("chat.start", ["user" => $mahasiswaTa->user_id, "ta" => $mahasiswaTa->id]) }}"
                class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">chat</span> Chat</a> <a
                href="{{ route("workspace.index", $mahasiswaTa) }}"
                class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">folder</span>
                Workspace</a>
            @if ($mahasiswaTa->pembimbing1 || $mahasiswaTa->pembimbing2)
                <a href="{{ route("logbook.export.pdf", $mahasiswaTa) }}"
                    class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Rekap
                    PDF</a>
            @endif
        </div>
    </div> {{-- Kontak mahasiswa (hanya untuk dosen) --}} @php $mhs = $mahasiswaTa->mahasiswa; @endphp
    @if ($isDosen && $mhs && ($mhs->whatsapp || $mhs->telegram || $mhs->linkedin))
        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3"><span class="material-symbols-outlined icon-sm align-text-bottom">contact_phone</span> Kontak Mahasiswa</h2>
            <div class="grid sm:grid-cols-3 gap-3 text-sm">
                @if ($mhs->whatsapp)
                    <a href="{{ $mhs->whatsappUrl() }}" target="_blank" rel="noopener"
                        class="px-3 py-2.5 rounded-md bg-bg-panel hover:bg-bg-hover flex items-start gap-2 min-w-0">
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5">chat</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-text-secondary">WhatsApp</span>
                            <span class="block font-medium text-text-primary break-words">{{ $mhs->whatsapp }}</span>
                        </span>
                    </a>
                @endif
                @if ($mhs->telegram)
                    <div class="px-3 py-2.5 rounded-md bg-bg-panel flex items-start gap-2 min-w-0">
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5">send</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-text-secondary">Telegram</span>
                            <span class="block font-medium text-text-primary break-words">{{ $mhs->telegram }}</span>
                        </span>
                    </div>
                @endif
                @if ($mhs->linkedin)
                    <a href="{{ $mhs->linkedin }}" target="_blank" rel="noopener"
                        class="px-3 py-2.5 rounded-md bg-bg-panel hover:bg-bg-hover flex items-start gap-2 min-w-0">
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5">link</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-text-secondary">LinkedIn</span>
                            <span class="block font-medium text-text-primary break-words">{{ \Illuminate\Support\Str::limit($mhs->linkedin, 40) }}</span>
                        </span>
                    </a>
                @endif
            </div>
        </div>
    @endif
    {{-- Info pembimbing + penguji + fase --}} <div
        class="bg-bg-surface rounded-xl border border-border p-5 grid sm:grid-cols-2 gap-4 text-sm">
        <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Pembimbing 1:</span> <span
                class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->pembimbing1])</span> </div>
        <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Pembimbing 2:</span> <span
                class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->pembimbing2])</span> </div>
        <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Penguji 1:</span> <span
                class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->penguji1])</span> </div>
        <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Penguji 2:</span> <span
                class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->penguji2])</span> </div>
        <div class="px-3 py-2 rounded-md bg-bg-panel sm:col-span-2"> <span class="text-text-secondary">Fase:</span>
            <span class="font-medium block">{{ $mahasiswaTa->faseLabel() }}</span>
            @if ($isDosen && $mahasiswaTa->isPembimbing(auth()->user()))
                <form method="POST" action="{{ route("mahasiswa-ta.fase", $mahasiswaTa) }}" class="mt-2 flex gap-1">
                    @csrf <select name="fase"
                        class="rounded-md border border-border bg-bg-surface px-2 py-1 text-xs">
                        @foreach (\App\Models\MahasiswaTa::FASES as $key => $label)
                            <option value="{{ $key }}" @selected($mahasiswaTa->fase === $key)>{{ $label }}
                            </option>
                        @endforeach
                    </select> <button class="px-2 py-1 rounded-md bg-brand text-white text-xs">Update</button>
                </form>
            @endif
        </div>
    </div>

    {{-- ===== Ringkasan dashboard mahasiswa (view dosen) ===== --}}
    <div class="space-y-6">
        {{-- Health indicator (self-awareness) --}}
        <div class="px-4 py-3 rounded-card border flex items-center gap-3
            {{ $regularity === 'green' ? 'bg-status-success/10 border-status-success/20 text-status-success' : '' }}
            {{ $regularity === 'yellow' ? 'bg-status-pending/10 border-status-pending/20 text-status-pending' : '' }}
            {{ $regularity === 'red' ? 'bg-status-danger/10 border-status-danger/20 text-status-danger' : '' }}">
            <span class="inline-block w-4 h-4 rounded-full flex-shrink-0
                {{ $regularity === 'green' ? 'bg-status-success' : '' }}
                {{ $regularity === 'yellow' ? 'bg-status-pending' : '' }}
                {{ $regularity === 'red' ? 'bg-status-danger' : '' }}"></span>
            <div class="text-sm">
                <strong>Status bimbingan mahasiswa: {{ ucfirst($regularity) }}</strong>
                <span class="block text-xs opacity-80">{{ $regularityTooltip }}</span>
            </div>
        </div>

        {{-- Milestone Journey (fase) --}}
        <div class="card p-6 bg-bg-surface rounded-xl border border-border">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-text-primary">Milestone Journey</h2>
                <span class="text-sm text-text-secondary">{{ $mahasiswaTa->faseLabel() }} · {{ $percent }}%</span>
            </div>
            @include('partials.milestone', ['faseKeys' => $faseKeys, 'faseIndex' => $faseIndex])
            <p class="mt-3 text-xs text-text-secondary">Fase ditetapkan oleh dosen pembimbing.</p>
        </div>

        {{-- Progres Bimbingan --}}
        <div class="card p-6 bg-bg-surface rounded-xl border border-border">
            <h2 class="font-heading font-semibold text-text-primary mb-3">Progres Bimbingan</h2>
            <div class="flex items-end justify-between mb-1 text-sm">
                <span class="text-text-secondary">{{ $approved }} / {{ $target }} sesi disetujui</span>
                <span class="font-bold text-text-primary">{{ $percent }}%</span>
            </div>
            <div class="h-3 rounded-full bg-bg-panel overflow-hidden">
                <div class="progress-bar h-full rounded-full bg-brand" style="width:{{ $percent }}%"></div>
            </div>
            <p class="mt-3 text-xs text-text-secondary">Minimal {{ $target }} sesi bimbingan perlu disetujui.</p>
        </div>

        {{-- Achievement + Statistik & Streak --}}
        <div class="grid lg:grid-cols-2 gap-5">
            <div class="card p-6 bg-bg-surface rounded-xl border border-border">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Achievement ({{ $unlockedAchievements->count() }}/{{ $totalAchievements }})</h2>
                @include('partials.badge-shelf', ['unlockedAchievements' => $unlockedAchievements, 'unlockedCodes' => $unlockedCodes, 'totalAchievements' => $totalAchievements])
            </div>

            <div class="card p-6 bg-bg-surface rounded-xl border border-border">
                <h2 class="font-heading font-semibold text-text-primary mb-3">Statistik & Streak</h2>
                @include('partials.stat-cards', ['stats' => $stats])
            </div>
        </div>

        {{-- Heatmap --}}
        <div class="card p-6 bg-bg-surface rounded-xl border border-border">
            <h2 class="font-heading font-semibold text-text-primary mb-3">Aktivitas 12 Bulan</h2>
            @include('partials.heatmap', ['heatmap' => $heatmap])
        </div>

        {{-- Timeline --}}
        <div class="card p-6 bg-bg-surface rounded-xl border border-border">
            <h2 class="font-heading font-semibold text-text-primary mb-4">Timeline Bimbingan</h2>
            @include('partials.timeline', ['timeline' => $timeline, 'regularity' => $regularity, 'regularityTooltip' => $regularityTooltip])
        </div>
    </div>

    {{-- Riwayat logbook --}} <div class="bg-bg-surface rounded-xl border border-border p-5">
        <h2 class="font-semibold mb-4">Riwayat Logbook ({{ $entries->count() }})</h2>
        @if ($entries->isEmpty())
            <p class="text-sm text-text-secondary">Belum ada bimbingan. Mulai catat sesi pertamamu.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-text-secondary border-b border-border">
                            <th class="py-2 pr-4">Sesi</th>
                            <th class="py-2 pr-4 table-col-jenis">Jenis</th>
                            <th class="py-2 pr-4">Topik</th>
                            <th class="py-2 pr-4 table-col-tanggal">Tanggal</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-b border-border">
                                <td class="py-2 pr-4">{{ $entry->jenis === "revisi" ? "—" : $entry->sesi_ke }}</td>
                                <td class="py-2 pr-4 table-col-jenis">{{ ucfirst($entry->jenis) }}</td>
                                <td class="py-2 pr-4">{{ $entry->topik ?? "Revisi" }}</td>
                                <td class="py-2 pr-4 table-col-tanggal">{{ $entry->tanggal_bimbingan?->format("d M Y") ?? "—" }}</td>
                                <td class="py-2 pr-4">@include("partials.status-badge", ["status" => $entry->status])</td>
                                <td class="py-2"><a href="{{ route("logbook.show", $entry) }}"
                                        class="text-brand hover:underline">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection