@extends("layouts.app") @section("title", "Edit Logbook") @section("content")
<div class="max-w-2xl">
    <h1 class="text-xl font-bold mb-4">Edit Entri</h1> @include("partials.status-badge", ["status" => $logbook->status]) <form method="POST"
        action="{{ route("logbook.update", $logbook) }}" enctype="multipart/form-data"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4 mt-3"> @csrf @method("PUT") @if ($logbook->jenis === "revisi")
            <div> <label class="block text-sm font-medium mb-1" for="tanggal_pengiriman">Tanggal Pengiriman Revisi</label>
                <input type="date" name="tanggal_pengiriman" id="tanggal_pengiriman" required
                    value="{{ old("tanggal_pengiriman", $logbook->tanggal_pengiriman?->format("Y-m-d") ?? now()->format("Y-m-d")) }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-accent-teal focus:outline-none">
                @error("tanggal_pengiriman")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        @else
            <div> <label class="block text-sm font-medium mb-1" for="tanggal_bimbingan">Tanggal Bimbingan</label> <input
                    type="date" name="tanggal_bimbingan" id="tanggal_bimbingan" required
                    value="{{ old("tanggal_bimbingan", $logbook->tanggal_bimbingan?->format("Y-m-d")) }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-accent-teal focus:outline-none">
                @error("tanggal_bimbingan")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div> <label class="block text-sm font-medium mb-1" for="topik">Topik Bimbingan</label> <input
                    type="text" name="topik" id="topik" required value="{{ old("topik", $logbook->topik) }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-accent-teal focus:outline-none">
                @error("topik")
                    <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        @endif
        <div> <label class="block text-sm font-medium mb-1" for="progres_kendala">Ringkasan Perbaikan</label>
            <textarea name="progres_kendala" id="progres_kendala" rows="6" required
                class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-accent-teal focus:outline-none">{{ old("progres_kendala", $logbook->progres_kendala) }}</textarea> @error("progres_kendala")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div> {{-- Lampiran draft --}} <div> <label class="block text-sm font-medium mb-1">Lampiran Draft
                (PDF)</label>
            @if ($logbook->lampiran_path)
                <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-bg-panel"> <span class="text-xl">📄</span>
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            {{ $logbook->lampiran_original_name ?: basename($logbook->lampiran_path) }}</p>
                        <p class="text-xs text-text-secondary">
                            {{ number_format(filesize(Storage::disk("local")->path($logbook->lampiran_path)) / 1048576, 1) }}
                            MB · {{ $logbook->updated_at->format("d M") }}</p>
                    </div>
                    <div class="flex items-center gap-1"> <a href="{{ route("logbook.pdf", $logbook) }}"
                            target="_blank" class="px-2 py-1 rounded-md bg-bg-panel hover:bg-bg-hover text-xs">Lihat</a>
                        <label
                            class="px-2 py-1 rounded-md bg-accent-blue hover:bg-accent-blue/90 text-white text-xs cursor-pointer">
                            Ganti <input type="file" name="lampiran" accept="application/pdf" class="hidden">
                        </label>
                        <form method="POST" action="{{ route("logbook.remove-lampiran", $logbook) }}"
                            onsubmit="return confirm('Hapus lampiran ini? File tidak bisa dikembalikan.')"> @csrf
                            @method("DELETE") <button
                                class="px-2 py-1 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-xs">Hapus</button>
                        </form>
                    </div>
                </div>
            @else
                <input type="file" name="lampiran" accept="application/pdf" class="w-full text-sm">
                @endif @error("lampiran")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
    </div> {{-- Catatan perbaikan (untuk revisi) --}} @if ($logbook->catatan_perbaikan_path)
        <div> <label class="block text-sm font-medium mb-1">Catatan Perbaikan (PDF)</label>
            <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-bg-panel"> <span class="text-xl">📄</span>
                <div class="flex-1">
                    <p class="text-sm font-medium">
                        {{ $logbook->catatan_original_name ?: basename($logbook->catatan_perbaikan_path) }}</p>
                </div>
                <div class="flex items-center gap-1"> <a href="{{ route("logbook.catatan-pdf", $logbook) }}"
                        target="_blank" class="px-2 py-1 rounded-md bg-bg-panel hover:bg-bg-hover text-xs">Lihat</a>
                    <form method="POST" action="{{ route("logbook.remove-catatan", $logbook) }}"
                        onsubmit="return confirm('Hapus catatan perbaikan ini?')"> @csrf @method("DELETE")
                        <button
                            class="px-2 py-1 rounded-md bg-status-danger hover:bg-status-danger/90 text-white text-xs">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <div
        class="px-3 py-2 rounded-md bg-status-pending/10 border border-status-pending/20 text-xs text-status-pending">
        ⚠ Mengganti atau menghapus file akan mengarsipkan versi lama dan tidak bisa dikembalikan. Komentar PDF
        pada file yang diganti akan otomatis ditandai selesai (resolve). </div>
    <div class="flex flex-wrap gap-2 pt-2"> <button type="submit"
            class="px-4 py-2 rounded-md bg-accent-teal hover:bg-accent-teal/90 text-white text-sm font-semibold">Simpan</button>
        <a href="{{ route("logbook.show", $logbook) }}"
            class="px-4 py-2 rounded-md bg-bg-hover hover:bg-bg-hover text-sm">Batal</a>
    </div>
</form>
</div>
@endsection
