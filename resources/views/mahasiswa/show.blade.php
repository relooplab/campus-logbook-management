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
            class="h-16 w-16 rounded-full overflow-hidden bg-accent-teal text-white flex items-center justify-center text-xl font-bold flex-shrink-0">
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
            <div class="text-2xl font-bold text-accent-teal">{{ $approved }}/{{ $target }}</div>
            <div class="text-xs text-text-secondary">sesi disetujui</div>
        </div>
        <div class="flex flex-wrap gap-2"> <a
                href="{{ route("chat.start", ["user" => $mahasiswaTa->user_id, "ta" => $mahasiswaTa->id]) }}"
                class="px-3 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">chat</span> Chat</a> <a
                href="{{ route("workspace.index", $mahasiswaTa) }}"
                class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">folder</span>
                Workspace</a>
            @if ($mahasiswaTa->pembimbing1 || $mahasiswaTa->pembimbing2)
                <a href="{{ route("logbook.export.pdf", $mahasiswaTa) }}"
                    class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Rekap
                    PDF</a>
            @endif
        </div>
    </div> {{-- Info pembimbing + penguji + fase --}} <div
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
                    </select> <button class="px-2 py-1 rounded-md bg-accent-blue text-white text-xs">Update</button>
                </form>
            @endif
        </div>
    </div> {{-- Riwayat logbook --}} <div class="bg-bg-surface rounded-xl border border-border p-5">
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
                                        class="text-accent-teal hover:underline">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
