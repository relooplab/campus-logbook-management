@extends("layouts.app") @section("title", "Detail " . ($mahasiswaTa->mahasiswa?->name ?? "Mahasiswa")) @section("content")
@php
    $user = auth()->user();
    $isDosen = $user->isDosen();
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Detail Mahasiswa</h1> <a href="{{ url()->previous() }}"
            class="px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">← Kembali</a>
    </div> {{-- Kartu profil mahasiswa --}} <div
        class="bg-bg-surface rounded-xl border border-border p-6 flex flex-wrap items-center gap-4">
        <div
            class="h-16 w-16 rounded-full overflow-hidden bg-brand text-[#0b1420] flex items-center justify-center text-xl font-bold flex-shrink-0">
            @if ($mahasiswaTa->mahasiswa?->photoUrl())
                <img src="{{ $mahasiswaTa->mahasiswa->photoUrl() }}" class="h-full w-full object-cover" alt="Foto">
            @else
                {{ $mahasiswaTa->mahasiswa?->initials() }}
            @endif
        </div>
        <div class="flex-1">
            <h2 class="font-semibold text-lg">{{ $mahasiswaTa->mahasiswa?->name }}</h2>
            <p class="text-sm text-text-secondary">{{ $mahasiswaTa->mahasiswa?->email }} ·
                <span class="font-mono">{{ $mahasiswaTa->mahasiswa?->nim }}</span></p>
            <p class="text-sm text-text-primary mt-1">{{ $mahasiswaTa->isKp() ? ($mahasiswaTa->tempat_kp ?: 'Tempat KP') : $mahasiswaTa->judul_ta }}</p>
            @if ($mahasiswaTa->isKp() && $mahasiswaTa->members->isNotEmpty())
                <div class="mt-2">
                    <span class="text-xs text-text-secondary">Anggota Kelompok:</span>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @foreach ($mahasiswaTa->allMembers() as $member)
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel border border-border">{{ $member->name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-brand">{{ $approved }}/{{ $target }}</div>
            <div class="text-xs text-text-secondary">sesi disetujui</div>
        </div>
        <div class="flex flex-wrap gap-2"> <a
                href="{{ route("chat.start", ["user" => $mahasiswaTa->user_id, "ta" => $mahasiswaTa->id]) }}"
                class="px-3 py-2 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">chat</span> Chat</a> <a
                href="{{ route("workspace.index", $mahasiswaTa) }}"
                class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom text-accent-teal">folder</span>
                Workspace</a>
            @if ($mahasiswaTa->pembimbing1 || $mahasiswaTa->pembimbing2)
                <a href="{{ route("logbook.export.pdf", $mahasiswaTa) }}"
                    class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Rekap
                    PDF</a>
            @endif
            @if ($mahasiswaTa->isKp())
                <a href="{{ route("logbook-harian.index", $mahasiswaTa) }}"
                    class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Logbook Harian</a>
                <a href="{{ route("profil-perusahaan.index", $mahasiswaTa) }}"
                    class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Profil Perusahaan</a>
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
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5 text-accent-teal">chat</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-text-secondary">WhatsApp</span>
                            <span class="block font-medium text-text-primary break-words">{{ $mhs->whatsapp }}</span>
                        </span>
                    </a>
                @endif
                @if ($mhs->telegram)
                    <div class="px-3 py-2.5 rounded-md bg-bg-panel flex items-start gap-2 min-w-0">
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5 text-accent-teal">send</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-text-secondary">Telegram</span>
                            <span class="block font-medium text-text-primary break-words">{{ $mhs->telegram }}</span>
                        </span>
                    </div>
                @endif
                @if ($mhs->linkedin)
                    <a href="{{ $mhs->linkedin }}" target="_blank" rel="noopener"
                        class="px-3 py-2.5 rounded-md bg-bg-panel hover:bg-bg-hover flex items-start gap-2 min-w-0">
                        <span class="material-symbols-outlined icon-sm flex-shrink-0 mt-0.5 text-accent-teal">link</span>
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
        @if ($mahasiswaTa->isKp())
            <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Pembimbing Lapangan:</span>
                <span class="font-medium block">{{ $mahasiswaTa->pembimbing_lapangan ?: 'Belum ditentukan' }}</span> </div>
        @else
            <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Penguji 1:</span> <span
                    class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->penguji1])</span> </div>
            <div class="px-3 py-2 rounded-md bg-bg-panel"> <span class="text-text-secondary">Penguji 2:</span> <span
                    class="font-medium block">@include("partials.user-link", ["user" => $mahasiswaTa->penguji2])</span> </div>
            @php $canManagePenguji = auth()->user()->isAdmin() || (auth()->user()->isDosen() && $mahasiswaTa->isPembimbing(auth()->user())); @endphp
            @if ($canManagePenguji)
                <div class="px-3 py-2 rounded-md bg-bg-panel sm:col-span-2 border border-border/60">
                    <span class="text-text-secondary text-xs font-medium">Ganti Dosen Penguji (oleh pembimbing)</span>
                    <form method="POST" action="{{ route('mahasiswa-ta.penguji', $mahasiswaTa) }}" class="mt-2 grid sm:grid-cols-2 gap-2 items-center" onsubmit="return confirm('Ubah dosen penguji program ini? Perubahan langsung diterapkan.')">
                        @csrf
                        <label class="flex flex-col gap-1 text-xs text-text-secondary">
                            Penguji 1
                            <select name="penguji_1_id" class="rounded-md border border-border bg-bg-surface px-2 py-1 text-xs text-text-primary">
                                <option value="">— Tidak ada —</option>
                                @foreach ($dosenList as $d)
                                    <option value="{{ $d->id }}" @selected($mahasiswaTa->penguji_1_id === $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-1 text-xs text-text-secondary">
                            Penguji 2
                            <select name="penguji_2_id" class="rounded-md border border-border bg-bg-surface px-2 py-1 text-xs text-text-primary">
                                <option value="">— Tidak ada —</option>
                                @foreach ($dosenList as $d)
                                    <option value="{{ $d->id }}" @selected($mahasiswaTa->penguji_2_id === $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="sm:col-span-2">
                            <button class="px-3 py-1.5 rounded-md bg-brand text-[#0b1420] text-xs font-medium hover:opacity-90">Simpan Penguji</button>
                        </div>
                    </form>
                </div>
            @endif
        @endif
        <div class="px-3 py-2 rounded-md bg-bg-panel sm:col-span-2"> <span class="text-text-secondary">Fase:</span>
            <span class="font-medium block">{{ $mahasiswaTa->faseLabel() }}</span>
            @if ($isDosen && $mahasiswaTa->isPembimbing(auth()->user()))
                <form method="POST" action="{{ route($mahasiswaTa->isKp() ? "mahasiswa-kp.fase" : "mahasiswa-ta.fase", $mahasiswaTa) }}" class="mt-2 flex gap-1" onsubmit="return confirm('Ubah fase {{ $mahasiswaTa->jenisLabel() }} mahasiswa ini? Pastikan perubahan sudah benar.')">
                    @csrf <select name="fase"
                        class="rounded-md border border-border bg-bg-surface px-2 py-1 text-xs">
                        @foreach ($faseKeys as $key)
                            <option value="{{ $key }}" @selected($mahasiswaTa->fase === $key)>{{ $faseLabels[$key] ?? $key }}
                            </option>
                        @endforeach
                    </select> <button class="px-2 py-1 rounded-md bg-brand text-[#0b1420] text-xs">Update</button>
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
                <h2 class="font-heading font-semibold text-text-primary">Perjalanan Fase</h2>
                <span class="text-sm text-text-secondary">{{ $mahasiswaTa->faseLabel() }} · {{ $percent }}%</span>
            </div>
            @include('partials.milestone', ['faseKeys' => $faseKeys, 'faseIndex' => $faseIndex, 'faseLabels' => $faseLabels ?? []])
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

        {{-- Logbook Harian (khusus KP) --}}
        @if ($mahasiswaTa->isKp())
            <div class="card p-6 bg-bg-surface rounded-xl border border-border">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-heading font-semibold text-text-primary">Logbook Harian KP</h2>
                    <a href="{{ route('logbook-harian.index', $mahasiswaTa) }}" class="text-sm text-brand hover:underline">Lihat Semua →</a>
                </div>
                @if ($logbookHarian->isEmpty())
                    <p class="text-sm text-text-secondary">Belum ada catatan harian.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($logbookHarian->take(5) as $lh)
                            <div class="px-3 py-2.5 rounded-xl bg-bg-panel">
                                <div class="text-xs font-semibold text-text-secondary">{{ $lh->tanggal->format('d M Y') }}</div>
                                <p class="text-sm mt-0.5">{{ \Illuminate\Support\Str::limit($lh->kegiatan, 120) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Achievement + Statistik & Streak --}}
        <div class="grid lg:grid-cols-2 gap-5">
            @if ($mahasiswaTa->isTa())
                <div class="card p-6 bg-bg-surface rounded-xl border border-border">
                    <h2 class="font-heading font-semibold text-text-primary mb-3">Achievement ({{ $unlockedAchievements->count() }}/{{ $totalAchievements }})</h2>
                    @include('partials.badge-shelf', ['unlockedAchievements' => $unlockedAchievements, 'unlockedCodes' => $unlockedCodes, 'totalAchievements' => $totalAchievements])
                </div>
            @endif

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
                                <td class="py-2 pr-4 font-mono">{{ $entry->jenis === "revisi" ? "—" : $entry->sesi_ke }}</td>
                                <td class="py-2 pr-4 table-col-jenis">{{ ucfirst($entry->jenis) }}</td>
                                <td class="py-2 pr-4">{{ $entry->topik ?? "Revisi" }}</td>
                                <td class="py-2 pr-4 table-col-tanggal font-mono">{{ $entry->tanggal_bimbingan?->format("d M Y") ?? "—" }}</td>
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