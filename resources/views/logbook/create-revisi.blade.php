@extends("layouts.app") @section("title", "Entri Revisi") @section("content")
<div class="max-w-2xl">
    <h1 class="text-xl font-bold mb-4">Entri Revisi</h1>
    <div class="mb-4 px-4 py-3 rounded-md bg-brand/10 text-sm border border-brand/20">
        Gunakan template catatan perbaikan: <a href="{{ config("app.template_url") }}" target="_blank" rel="noopener"
            class="text-brand font-medium underline">Template Catatan Perbaikan</a> </div>
    <form method="POST" action="{{ route("logbook.store-revisi") }}" enctype="multipart/form-data"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4"> @csrf <div> <label
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
                class="px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Batal</a>
        </div>
    </form>
</div>
@endsection @section("scripts")
@include("partials.tb-script")
<script>
    initTbToolbar('progres_kendala');
</script>
@endsection
