@extends("layouts.app")
@section("title", "Profil Perusahaan")
@section("content")
<div class="max-w-2xl">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-xl font-bold">Profil Perusahaan KP</h1>
            <p class="text-sm text-text-secondary mt-1">
                {{ $mahasiswaTa->mahasiswa?->name }} — {{ $mahasiswaTa->tempat_kp ?: "Tempat KP" }}
            </p>
        </div>
        <a href="{{ route("dashboard") }}" class="px-3 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm">← Dashboard</a>
    </div>

    @if (session("success"))
        <div class="mb-4 rounded-xl bg-status-success/10 border border-status-success/30 px-4 py-3 text-sm text-status-success">
            {{ session("success") }}
        </div>
    @endif

    <form method="POST" action="{{ route("profil-perusahaan.update", $mahasiswaTa) }}"
        class="bg-bg-surface rounded-xl border border-border p-6 space-y-4">
        @csrf @method("PUT")
        <div>
            <label class="block text-sm font-medium mb-1" for="tempat_kp">Nama Perusahaan / Instansi</label>
            <input type="text" name="tempat_kp" id="tempat_kp" required maxlength="255"
                value="{{ old("tempat_kp", $mahasiswaTa->tempat_kp) }}"
                placeholder="Nama perusahaan / instansi tempat KP"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("tempat_kp")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="alamat_perusahaan">Alamat Perusahaan</label>
            <input type="text" name="alamat_perusahaan" id="alamat_perusahaan" maxlength="500"
                value="{{ old("alamat_perusahaan", $mahasiswaTa->alamat_perusahaan) }}"
                placeholder="Alamat perusahaan / instansi tempat KP"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("alamat_perusahaan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="jenis_instansi">Jenis Instansi</label>
            <select name="jenis_instansi" id="jenis_instansi"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
                <option value="">— Pilih jenis instansi —</option>
                @foreach ($jenisInstansi as $key => $label)
                    <option value="{{ $key }}" @selected(old("jenis_instansi", $mahasiswaTa->jenis_instansi) === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error("jenis_instansi")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="pembimbing_lapangan">Pembimbing Lapangan</label>
            <input type="text" name="pembimbing_lapangan" id="pembimbing_lapangan" maxlength="255"
                value="{{ old("pembimbing_lapangan", $mahasiswaTa->pembimbing_lapangan) }}"
                placeholder="Nama pembimbing lapangan di perusahaan"
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">
            @error("pembimbing_lapangan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="profil_perusahaan">Profil Singkat Perusahaan</label>
            <textarea name="profil_perusahaan" id="profil_perusahaan" rows="6" maxlength="5000"
                placeholder="Tuliskan profil singkat perusahaan, bidang usaha, dan divisi tempat Anda bekerja..."
                class="w-full rounded-xl border border-border bg-bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-brand focus:outline-none">{{ old("profil_perusahaan", $mahasiswaTa->profil_perusahaan) }}</textarea>
            @error("profil_perusahaan")
                <p class="text-status-danger text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Simpan</button>
        </div>
    </form>
</div>
@endsection