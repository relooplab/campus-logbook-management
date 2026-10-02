@if ($paginator->hasPages())
<nav aria-label="Pagination riwayat" class="flex flex-wrap items-center gap-1">
    @if ($paginator->onFirstPage())
        <span class="history-page is-disabled" aria-disabled="true" aria-label="Halaman sebelumnya">‹</span>
    @else
        <a class="history-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">‹</a>
    @endif
    @foreach ($elements as $element)
        @if (is_string($element)) <span class="px-1 text-text-secondary">{{ $element }}</span> @endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span class="history-page is-active" aria-current="page" aria-label="Halaman {{ $page }}">{{ $page }}</span>
                @else
                    <a class="history-page" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())
        <a class="history-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">›</a>
    @else
        <span class="history-page is-disabled" aria-disabled="true" aria-label="Halaman berikutnya">›</span>
    @endif
</nav>
@endif
