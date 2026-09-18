@extends("layouts.focus") @section("title", "Viewer PDF & Anotasi") @section("head") @vite(["resources/js/pdf-viewer.jsx"])
@endsection @section("content")
<div id="pdf-viewer-root" class="h-full"></div>
@endsection @section("scripts")
<script>
    window.PDF_VIEWER_DATA = {
        title: @json($logbook->jenis === "revisi" ? "Revisi" : "Sesi " . $logbook->sesi_ke),
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
        replyUrl: @json(url("/pdf-comments/{id}/reply")),
        deleteUrl: @json(url("/pdf-comments/{id}")),
        canReply: @json($logbook->mahasiswaTa?->user_id === auth()->user()->id),
        burnUrl: @json(route("logbook.pdf.burn", ["logbook" => $logbook, "type" => "__TYPE__"])),
        buildFeedbackUrl: @if (auth()->user()->can('review', $logbook))
            @json(route("quick-review.build-feedback", $logbook))
        @else
            null
        @endif ,
        canReview: @json(auth()->user()->can('review', $logbook)),
        returnUrl: @json(route("logbook.show", $logbook)),
    };
</script>
@endsection
