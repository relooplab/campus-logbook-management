@extends("layouts.app") @section("title", "Pengumuman") @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Pengumuman</h1> <a href="{{ route("announcements.create") }}"
            class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm">+ Buat Pengumuman</a>
    </div>
    @if ($announcements->isEmpty())
        <div class="px-4 py-10 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            pengumuman yang Anda kirim. </div>
    @else
        @foreach ($announcements as $a)
            <div class="bg-bg-surface rounded-xl border border-border p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold"><span class="material-symbols-outlined icon-sm">campaign</span> {{ $a->title }}</p>
                        <p class="text-sm text-text-secondary mt-1">{{ $a->body }}</p>
                        <p class="text-xs text-text-secondary mt-2">{{ $a->created_at?->diffForHumans() }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm"> <span>Terkirim: <b>{{ $a->recipients_count }}</b>
                        mahasiswa</span> <span>· Sudah baca: <b
                            class="text-accent-teal">{{ $a->recipients_count - $a->unreadRecipientsCount() }}</b></span>
                    <span class="ml-auto flex flex-wrap gap-2"> <a href="{{ route("announcements.report", $a) }}"
                            class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-bg-hover text-xs">Lihat Detail</a>
                        <form method="POST" action="{{ route("announcements.remind", $a) }}"> @csrf <button
                                class="px-3 py-1.5 rounded-md bg-status-pending hover:bg-status-pending/90 text-white text-xs">Ingatkan
                                yang belum baca</button> </form>
                    </span>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
