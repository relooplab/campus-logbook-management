@extends("layouts.app") @section("title", "Viewer PDF & Anotasi") @section("head") @vite(["resources/js/pdf-viewer.jsx"])
@endsection @section("content")
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold"> Viewer PDF &amp; Anotasi —
            {{ $logbook->jenis === "revisi" ? "Revisi" : "Sesi " . $logbook->sesi_ke }} </h1>
        <div class="flex gap-2">
            @if (auth()->user()->isDosen())
                <button type="button" id="build-feedback-btn"
                    class="px-3 py-2 rounded-md bg-accent-blue hover:bg-accent-blue/90 text-white text-sm"> ⚡ Jadikan
                    Feedback
                </button>
            @endif <a href="{{ route("logbook.show", $logbook) }}"
                class="px-3 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">← Kembali</a>
        </div>
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
        buildFeedbackUrl: @json(route("quick-review.build-feedback", $logbook)),
    };
</script>
<script>
    // Tombol "Jadikan Feedback": compile komentar unresolved, simpan ke feedback, // lalu arahkan ke halaman quick review / detail untuk edit. (function () { var btn = document.getElementById('build-feedback-btn'); if (!btn) return; btn.addEventListener('click', function () { var csrf = document.querySelector('meta[name="csrf-token"]').content; var url = window.PDF_VIEWER_DATA.buildFeedbackUrl; fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) { if (!d.feedback) { alert('Tidak ada komentar yang belum resolve.'); return; } alert('Feedback ter-compile dari komentar. Buka Quick Review untuk menerapkan.'); }); }); })();
</script>
@endsection
