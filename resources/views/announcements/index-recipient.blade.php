@extends("layouts.app") @section("title", "Pengumuman") @section("content")
<div class="space-y-4 max-w-3xl">
    <h1 class="text-xl font-bold">Pengumuman</h1>
    @if ($announcements->isEmpty())
        <div class="px-4 py-10 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            pengumuman. </div>
    @else
        @foreach ($announcements as $a)
            @php $unread = !$a->pivot->read_at; @endphp <div
                class="bg-bg-surface rounded-xl border {{ $unread ? "border-status-pending/30" : "border-border" }} p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold"><span class="material-symbols-outlined icon-sm text-accent-orange">campaign</span> {{ $a->title }}</p>
                        <p class="text-sm text-text-primary mt-1">{{ $a->body }}</p>
                        <p class="text-xs text-text-secondary mt-2">Dari: {{ $a->sender?->name }} ·
                            {{ $a->created_at?->diffForHumans() }}</p>
                    </div>
                    @if ($unread)
                        <span class="badge badge-pending flex-shrink-0">Belum dibaca</span>
                    @else
                        <span class="badge badge-success flex-shrink-0">Dibaca <span class="material-symbols-outlined icon-sm align-text-bottom">check</span></span>
                    @endif
                </div>
                @if ($unread)
                    <form method="POST" action="{{ route("announcements.read", $a) }}" class="mt-3"> @csrf <button
                            class="px-3 py-1.5 rounded-md bg-brand hover:bg-brand-hover text-[#0b1420] text-xs">Tandai
                            Dibaca</button> </form>
                @endif
            </div>
        @endforeach
    @endif
</div>
@endsection
