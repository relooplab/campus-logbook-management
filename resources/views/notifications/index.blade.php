@extends("layouts.app") @section("title", "Notifikasi") @section("content")
<div class="max-w-3xl space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Notifikasi</h1>
        <form method="POST" action="{{ route("notifications.mark-all-read") }}"> @csrf <button
                class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Tandai semua
                sudah dibaca</button> </form>
    </div>
    @if ($notifications->isEmpty())
        <div class="px-4 py-6 rounded-lg bg-bg-surface border border-border text-text-secondary"> Belum ada notifikasi.
        </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border divide-y divide-border divide-border">
            @foreach ($notifications as $n)
                @php
                    $unread = $n->unread();
                    $url = data_get($n->data, "url");
                @endphp <a href="{{ route("notifications.show", $n) }}"
                    class="flex items-start gap-3 px-4 py-3 hover:bg-bg-panel hover:bg-bg-hover/50 {{ $unread ? "bg-accent-blue/10/50 bg-accent-blue/5" : "" }}">
                    <span
                        class="mt-1.5 h-2 w-2 rounded-full flex-shrink-0 {{ $unread ? "bg-accent-blue" : "bg-bg-hover bg-bg-hover" }}"></span>
                    <div class="min-w-0">
                        <p class="text-sm {{ $unread ? "font-semibold" : "text-text-primary" }}">
                            {{ data_get($n->data, "message", "Pemberitahuan") }}</p>
                        <p class="text-xs text-text-secondary mt-0.5">{{ $n->created_at?->diffForHumans() }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="px-2">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
