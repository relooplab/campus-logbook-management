@extends("layouts.app")
@section("title", "Logbook Harian KP")
@section("content")
<div class="max-w-4xl">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Logbook Harian KP</h1>
            <p class="text-sm text-text-secondary mt-1">
                {{ $mahasiswaTa->mahasiswa?->name }} — {{ $mahasiswaTa->tempat_kp ?: 'Tempat KP' }}
                @if ($mahasiswaTa->periode_mulai)
                    ({{ $mahasiswaTa->periode_mulai->format('d M Y') }} – {{ $mahasiswaTa->periode_selesai?->format('d M Y') ?? 'sekarang' }})
                @endif
            </p>
        </div>
        @if ($mahasiswaTa->isMember(auth()->user()))
            <a href="{{ route('logbook-harian.create', $mahasiswaTa) }}"
                class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">+ Tambah Catatan</a>
        @endif
    </div>

    @if (session("success"))
        <div class="mb-4 rounded-md bg-status-success/10 border border-status-success/30 px-4 py-3 text-sm text-status-success">
            {{ session("success") }}
        </div>
    @endif

    @if ($entries->isEmpty())
        <div class="rounded-xl border border-border bg-bg-surface p-8 text-center text-text-secondary text-sm">
            Belum ada catatan harian. @if ($mahasiswaTa->isMember(auth()->user())) Klik "Tambah Catatan" untuk mengisi kegiatan harian. @endif
        </div>
    @else
        <div class="space-y-3">
            @foreach ($entries as $entry)
                <div class="rounded-xl border border-border bg-bg-surface p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm font-semibold">{{ $entry->tanggal->format("d M Y") }}</div>
                        <div class="flex items-center gap-2">
                            @if ($entry->creator)
                                <span class="text-xs text-text-secondary">oleh {{ $entry->creator->name }}</span>
                            @endif
                            @if ($entry->created_by === auth()->id())
                                <div class="flex gap-2">
                                    <a href="{{ route("logbook-harian.edit", [$mahasiswaTa, $entry]) }}"
                                        class="text-xs text-brand hover:underline">Edit</a>
                                    <form method="POST" action="{{ route("logbook-harian.destroy", [$mahasiswaTa, $entry]) }}"
                                        onsubmit="return confirm('Hapus catatan harian ini?')">
                                        @csrf @method("DELETE")
                                        <button type="submit" class="text-xs text-status-danger hover:underline">Hapus</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                    <p class="text-sm mt-2 whitespace-pre-line">{{ $entry->kegiatan }}</p>
                    @if ($entry->kendala)
                        <p class="text-sm mt-2 text-status-warning"><strong>Kendala:</strong> {{ $entry->kendala }}</p>
                    @endif
                    @if ($entry->foto_1 || $entry->foto_2)
                        <div class="grid grid-cols-2 gap-3 mt-3">
                            @if ($entry->foto_1)
                                <a href="{{ route('logbook-harian.foto', [$mahasiswaTa, $entry, 1]) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('logbook-harian.foto', [$mahasiswaTa, $entry, 1]) }}"
                                        alt="Foto 1" class="w-full h-36 object-cover rounded-lg border border-border">
                                </a>
                            @endif
                            @if ($entry->foto_2)
                                <a href="{{ route('logbook-harian.foto', [$mahasiswaTa, $entry, 2]) }}" target="_blank" rel="noopener">
                                    <img src="{{ route('logbook-harian.foto', [$mahasiswaTa, $entry, 2]) }}"
                                        alt="Foto 2" class="w-full h-36 object-cover rounded-lg border border-border">
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
</div>
@endsection