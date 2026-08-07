@extends("layouts.app") @section("title", "Chat") @section("content")
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Chat</h1> <a href="{{ route("dashboard") }}"
            class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">← Dashboard</a>
    </div>
    @if ($user->isDosen() && $supervised->isNotEmpty())
        <div class="bg-bg-surface rounded-xl border border-border p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold">Mahasiswa Bimbingan Anda</h2>
                <span class="text-xs text-text-secondary">{{ $supervised->count() }} mahasiswa</span>
            </div>
            <div class="space-y-2">
                @foreach ($supervised as $ta)
                    <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl border border-border hover:bg-bg-hover/50">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="h-9 w-9 rounded-full bg-brand text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                                @if ($ta->mahasiswa?->photoUrl())
                                    <img src="{{ $ta->mahasiswa->photoUrl() }}" class="h-full w-full object-cover rounded-full" alt="">
                                @else
                                    {{ $ta->mahasiswa?->initials() }}
                                @endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium text-sm truncate">{{ $ta->mahasiswa?->name ?? 'Mahasiswa' }}</p>
                                <p class="text-xs text-text-secondary truncate">{{ $ta->jenisLabel() }} — {{ $ta->judul_ta }}</p>
                            </div>
                        </div>
                        <a href="{{ route('chat.start', ['user' => $ta->mahasiswa?->id, 'ta' => $ta->id]) }}"
                            class="flex-shrink-0 px-3 py-1.5 rounded-lg bg-brand text-white text-xs font-medium hover:opacity-90">Chat</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    @if ($conversations->isEmpty())
        <div class="px-4 py-10 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            percakapan. @if ($user->isDosen())Mulai dari daftar mahasiswa bimbingan di atas.@elseMulai dari halaman detail dosen pembimbing Anda.@endif </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-hidden">
            @foreach ($conversations as $c)
                <a href="{{ route("chat.show", $c) }}"
                    class="flex items-center gap-3 px-4 py-3 border-b border-border hover:bg-bg-panel hover:bg-bg-hover/50">
                    <span
                        class="h-10 w-10 rounded-full bg-brand text-white flex items-center justify-center font-bold flex-shrink-0">
                        @if ($c->other_user?->photoUrl())
                            <img src="{{ $c->other_user->photoUrl() }}" class="h-full w-full object-cover rounded-full"
                                alt="">
                        @else
                            {{ $c->other_user?->initials() }}
                        @endif
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="font-medium">{{ $c->other_user?->name }}</p>
                            @if ($c->unread > 0)
                                <span
                                    class="h-5 min-w-5 px-1 rounded-full bg-status-danger text-white text-xs flex items-center justify-center">{{ $c->unread }}</span>
                            @endif
                        </div>
                        <p class="text-xs text-text-secondary">
                            {{ $c->mahasiswaTa?->mahasiswa?->name ? "TA: " . $c->mahasiswaTa->mahasiswa->name : "" }} ·
                            {{ $c->updated_at?->diffForHumans() }} </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
