{{-- Isian satu kartu perbaikan (dipakai loop statis di step 2 revisi). --}}
{{-- Variabel: $i (indeks baris), $row (nilai lama/prefill), $statusOptions. --}}
<div class="grid gap-3 sm:grid-cols-2">
    <div>
        <label class="mb-1 block text-xs text-text-secondary" for="riwayat-halaman-{{ $i }}">Halaman/Bagian</label>
        <input type="text" id="riwayat-halaman-{{ $i }}" name="riwayat_perbaikan[{{ $i }}][halaman]"
            value="{{ $row['halaman'] ?? '' }}" placeholder="mis. Hal. 5, Bab 3" class="form-control">
        @error("riwayat_perbaikan.{$i}.halaman")
            <p class="form-field-error">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="mb-1 block text-xs text-text-secondary" for="riwayat-status-{{ $i }}">Status</label>
        <select id="riwayat-status-{{ $i }}" name="riwayat_perbaikan[{{ $i }}][status]" class="form-control">
            @foreach ($statusOptions as $s)
                <option value="{{ $s }}" @selected(($row['status'] ?? '') === $s || (($row['status'] ?? '') === '' && $loop->first))>{{ $s }}</option>
            @endforeach
        </select>
        @error("riwayat_perbaikan.{$i}.status")
            <p class="form-field-error">{{ $message }}</p>
        @enderror
    </div>
</div>
<div>
    <label class="mb-1 block text-xs text-text-secondary" for="riwayat-komentar-{{ $i }}">Komentar Dosen</label>
    <input type="text" id="riwayat-komentar-{{ $i }}" name="riwayat_perbaikan[{{ $i }}][komentar_dosen]"
        value="{{ $row['komentar_dosen'] ?? '' }}" placeholder="Komentar yang diperbaiki" class="form-control">
    @error("riwayat_perbaikan.{$i}.komentar_dosen")
        <p class="form-field-error">{{ $message }}</p>
    @enderror
</div>
<div>
    <label class="mb-1 block text-xs text-text-secondary" for="riwayat-perbaikan-{{ $i }}">Perbaikan yang Dilakukan</label>
    <textarea id="riwayat-perbaikan-{{ $i }}" name="riwayat_perbaikan[{{ $i }}][perbaikan]" rows="3"
        placeholder="Jelaskan perbaikan yang Anda lakukan" class="form-control">{{ $row['perbaikan'] ?? '' }}</textarea>
    @error("riwayat_perbaikan.{$i}.perbaikan")
        <p class="form-field-error">{{ $message }}</p>
    @enderror
</div>
