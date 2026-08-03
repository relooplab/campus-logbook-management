@extends("layouts.app") @section("title", "Entri Revisi") @section("content")
<div class="max-w-2xl">
    <h1 class="text-xl font-bold mb-4">Entri Revisi</h1>
    <div class="mb-4 px-4 py-3 rounded-md bg-brand/10 text-sm border border-brand/20">
        Gunakan template catatan perbaikan: <a href="{{ config("app.template_url") }}" target="_blank" rel="noopener"
            class="text-brand font-medium underline">Template Catatan Perbaikan</a> </div>
    @if ($parents->isEmpty())
        <div class="mb-4 px-4 py-3 rounded-md bg-status-pending/10 border border-status-pending/30 text-sm">
            Tidak ada feedback revisi aktif yang dapat dijawab.
        </div>
    @endif
    @if ($parents->isNotEmpty())
    <form method="POST" action="{{ route("logbook.store-revisi") }}" enctype="multipart/form-data"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4"> @csrf <div> <label
                class="block text-sm font-medium mb-1" for="parent_entry_id">Feedback yang dijawab</label>
            <select name="parent_entry_id" id="parent_entry_id" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <option value="">Pilih entri asal</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected(old("parent_entry_id", $selectedParentId) == $parent->id)>
                        Entri #{{ $parent->id }} · {{ $parent->revision_round ? "Revisi ke-{$parent->revision_round}" : "Logbook" }} · {{ $parent->reviewed_at?->format("d M Y") }}
                    </option>
                @endforeach
            </select>
            @error("parent_entry_id")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        @if ($parents->contains(fn ($parent) => $parent->exceedsRevisionRoundLimit()))
            <p class="text-xs text-status-pending">Siklus yang sudah mencapai ronde ke-3 perlu dibahas langsung dengan pembimbing sebelum mengirim perbaikan berikutnya.</p>
        @endif
        @foreach ($parents as $parent)
            <div data-parent-feedback="{{ $parent->id }}" class="rounded-md bg-bg-panel border border-border p-3 space-y-2 hidden">
                <p class="text-xs font-semibold text-text-secondary">Feedback entri #{{ $parent->id }}</p>
                <p class="text-sm whitespace-pre-wrap">{{ $parent->feedback_dosen ?: "Tidak ada feedback teks." }}</p>
                @foreach ($parent->comments->where("resolution_status", "!=", \App\Models\PdfComment::STATUS_RESOLVED) as $comment)
                    <label class="flex gap-2 items-start text-xs">
                        <input type="checkbox" name="addressed_comment_ids[]" value="{{ $comment->id }}"
                            @checked(in_array($comment->id, old("addressed_comment_ids", []))) class="mt-0.5">
                        <span>Hal. {{ $comment->page_number }}: {{ $comment->comment }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach
        <div> <label
                class="block text-sm font-medium mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label> <input
                type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                value="{{ old("tanggal_pengiriman", now()->format("Y-m-d")) }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("tanggal_pengiriman")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="progres_kendala">Ringkasan Perbaikan</label>
            <div class="flex gap-1 mb-2" id="tb-toolbar"> <button type="button" data-insert="bullet"
                    class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">•
                    Bullet</button> <button type="button" data-insert="number"
                    class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">1.
                    Number</button> <button type="button" data-insert="dash"
                    class="px-3 py-1 rounded bg-bg-panel hover:bg-bg-hover text-xs">—
                    Dash</button> </div>
            <textarea name="progres_kendala" id="progres_kendala" rows="6" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">{{ old("progres_kendala") }}</textarea> @error("progres_kendala")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="lampiran">File Perbaikan/Draft (PDF, wajib, maks 10
                MB)</label> <input type="file" name="lampiran" id="lampiran" accept="application/pdf" required
                class="w-full text-sm"> @error("lampiran")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div> <label class="block text-sm font-medium mb-1" for="catatan_perbaikan">Catatan Perbaikan (PDF, wajib, maks
                10 MB)</label> <input type="file" name="catatan_perbaikan" id="catatan_perbaikan"
                accept="application/pdf" required class="w-full text-sm"> @error("catatan_perbaikan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2"> <button type="submit"
                class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan
                Revisi</button> <button type="submit" name="submit" value="1"
                class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Kirim
                ke
                Pembimbing</button> <a href="{{ route("logbook.index") }}"
                class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Batal</a>
        </div>
    </form>
    @else
        <a href="{{ route("logbook.index") }}" class="inline-block px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Kembali ke Logbook</a>
    @endif
</div>
@endsection @section("scripts")
@include("partials.tb-script")
<script>
    if (document.getElementById('progres_kendala')) initTbToolbar('progres_kendala');

    var parentSelect = document.getElementById('parent_entry_id');
    var parentCards = document.querySelectorAll('[data-parent-feedback]');
    function syncParentFeedback() {
        if (!parentSelect) return;
        parentCards.forEach(function (card) {
            var active = card.dataset.parentFeedback === parentSelect.value;
            card.classList.toggle('hidden', !active);
            card.querySelectorAll('input[name="addressed_comment_ids[]"]').forEach(function (input) {
                input.disabled = !active;
            });
        });
    }
    if (parentSelect) {
        parentSelect.addEventListener('change', syncParentFeedback);
        syncParentFeedback();
    }
</script>
@endsection
