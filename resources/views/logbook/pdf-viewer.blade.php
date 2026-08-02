@extends("layouts.app") @section("title", "Viewer PDF & Anotasi") @section("head") @vite(["resources/js/pdf-viewer.jsx"])
@endsection @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold"> Viewer PDF & Anotasi —
            {{ $logbook->jenis === "revisi" ? "Revisi" : "Sesi " . $logbook->sesi_ke }} </h1>
        <a href="{{ route("logbook.show", $logbook) }}"
            class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">← Kembali</a>
    </div>
    <div id="pdf-viewer-root"></div>
</div>
@endsection @section("scripts")
<script>
    window.PDF_VIEWER_DATA = {
        draftUrl: @if ($logbook->lampiran_path)
            @json(route("logbook.pdf", $logbook))
        @else
            null
        @endif ,
        catatanUrl: @if ($logbook->catatan_perbaikan_path)
            @json(route("logbook.catatan-pdf", $logbook))
        @else
            null
        @endif ,
        hasCatatan: @json($logbook->catatan_perbaikan_path ? true : false),
        entryId: @json($logbook->id),
        csrf: @json(csrf_token()),
        commentsUrl: @json(route("logbook.pdf.comments", $logbook)),
        storeUrl: @json(route("logbook.pdf.store-comment", $logbook)),
        resolveUrl: @json(url("/pdf-comments/{id}/resolve")),
        deleteUrl: @json(url("/pdf-comments/{id}")),
        burnUrl: @json(route("logbook.pdf.burn", ["logbook" => $logbook, "type" => "__TYPE__"])),
        buildFeedbackUrl: @if (auth()->user()->isDosen())
            @json(route("quick-review.build-feedback", $logbook))
        @else
            null
        @endif ,
    };
</script>
@endsection
