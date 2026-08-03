@extends("layouts.app")
@section("title", "Edit Catatan Harian")
@section("content")
<div class="max-w-2xl">
    <h1 class="text-xl font-bold mb-4">Edit Catatan Harian KP</h1>
    <form method="POST" action="{{ route("logbook-harian.update", [$mahasiswaTa, $logbookHarian]) }}" enctype="multipart/form-data"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        @csrf @method("PUT")
        <div>
            <label class="block text-sm font-medium mb-1" for="tanggal">Tanggal Kegiatan</label>
            <input type="date" name="tanggal" id="tanggal" required value="{{ old("tanggal", $logbookHarian->tanggal->format("Y-m-d")) }}"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("tanggal")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="kegiatan">Kegiatan Lapangan</label>
            <textarea name="kegiatan" id="kegiatan" rows="5" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">{{ old("kegiatan", $logbookHarian->kegiatan) }}</textarea>
            @error("kegiatan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="kendala">Kendala <span class="text-text-secondary">(opsional)</span></label>
            <textarea name="kendala" id="kendala" rows="3"
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">{{ old("kendala", $logbookHarian->kendala) }}</textarea>
            @error("kendala")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Foto Kegiatan <span class="text-text-secondary">(opsional, maks 2 foto)</span></label>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_1">Foto 1</label>
                    @if ($logbookHarian->foto_1)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logbookHarian->foto_1) }}"
                            alt="Foto 1" class="w-full h-32 object-cover rounded-md mb-2">
                    @endif
                    <input type="file" name="foto_1" id="foto_1" accept="image/*"
                        class="w-full text-sm">
                    @error("foto_1")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-text-secondary mb-1" for="foto_2">Foto 2</label>
                    @if ($logbookHarian->foto_2)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logbookHarian->foto_2) }}"
                            alt="Foto 2" class="w-full h-32 object-cover rounded-md mb-2">
                    @endif
                    <input type="file" name="foto_2" id="foto_2" accept="image/*"
                        class="w-full text-sm">
                    @error("foto_2")
                        <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm font-semibold">Simpan</button>
            <a href="{{ route("logbook-harian.index", $mahasiswaTa) }}"
                class="px-4 py-2 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-sm">Batal</a>
        </div>
    </form>
</div>
@endsection