@extends("layouts.app") @section("title", "Riwayat Sidang") @section("content")
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Riwayat Sidang ({{ $sidangs->count() }})</h1>
        <div class="flex flex-wrap gap-2"> <a href="{{ route("dashboard.dosen.sidang-list.export") }}"
                class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm"><span class="material-symbols-outlined icon-sm align-text-bottom">download</span> Export PDF</a>
            <a href="{{ route("dashboard") }}" class="px-3 py-2 rounded-md bg-bg-hover hover:bg-border text-text-primary text-sm">← Dashboard</a> </div>
    </div>

    {{-- ===== Nilai yang perlu diisi ===== --}}
    @if ($myGrades->isNotEmpty())
        <div class="bg-bg-surface rounded-xl border border-border p-5">
            <h2 class="font-semibold mb-3">Nilai yang perlu Anda isi</h2>
            <div class="space-y-3">
                @foreach ($myGrades as $g)
                    <div class="rounded-xl border border-border bg-bg-panel p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <div>
                                <p class="font-medium">{{ $g->sidang->mahasiswa_name ?? $g->sidang->mahasiswaTa?->mahasiswa?->name }}</p>
                                <p class="text-xs text-text-secondary">{{ $g->sidang->jenisLabel() }} · {{ $g->sidang->tanggal?->format('d M Y') }} · {{ ucfirst($g->role) }}</p>
                            </div>
                            @if ($g->filled_at)
                                <span class="badge badge-success">Sudah dinilai</span>
                            @else
                                <span class="badge badge-pending">Belum dinilai</span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('dosen-sidang.grade', $g->sidang) }}" class="grid sm:grid-cols-2 gap-2">
                            @csrf
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Nilai (0–100) <span class="text-status-danger">*</span></label>
                                <input type="number" name="nilai" min="0" max="100" step="0.01" required value="{{ $g->nilai }}" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs text-text-secondary mb-1">Catatan (opsional)</label>
                                <input type="text" name="catatan" value="{{ $g->catatan }}" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                            </div>
                            <div class="sm:col-span-2">
                                <button class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">{{ $g->filled_at ? 'Perbarui Nilai' : 'Simpan Nilai' }}</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ===== Form catat riwayat sidang / seminar ===== --}}
    <div class="bg-bg-surface rounded-xl border border-border p-5">
        <h2 class="font-semibold mb-1">Catat Riwayat Sidang / Seminar</h2>
        <p class="text-xs text-text-secondary mb-3">Diisi setelah seminar/sidang berlangsung. Pembimbing & penguji terkait akan mengisi nilai.</p>
        <form method="POST" action="{{ route("dosen-sidang.store") }}" class="grid sm:grid-cols-2 gap-3"> @csrf
            @if ($preselect)
                <input type="hidden" name="submission_id" value="{{ $preselect->id }}">
                <input type="hidden" name="mahasiswa_ta_id" value="{{ $preselect->mahasiswa_ta_id }}">
            @endif
            <div>
                <label class="block text-xs text-text-secondary mb-1">Mahasiswa (dari bimbingan)</label>
                <select name="mahasiswa_ta_id" @if ($preselect) disabled @endif
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm @if ($preselect) opacity-60 @endif">
                    <option value="">— Pilih mahasiswa bimbingan / atau isi manual —</option>
                    @foreach ($bimbingan as $ta)
                        <option value="{{ $ta->id }}" @selected($preselect && $preselect->mahasiswa_ta_id === $ta->id)>{{ $ta->mahasiswa?->name }}</option>
                    @endforeach
                </select>
                @if ($preselect)
                    <p class="text-xs text-text-secondary mt-1">Dari bahan seminar: {{ $preselect->mahasiswaTa?->mahasiswa?->name }}</p>
                @endif
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Atau nama mahasiswa (di luar sistem)</label>
                <input type="text" name="mahasiswa_name" placeholder="Nama mahasiswa yang diuji"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            @error("mahasiswa") <p class="text-status-danger text-xs sm:col-span-2">{{ $message }}</p> @enderror
            <div>
                <label class="block text-xs text-text-secondary mb-1">Jenis</label>
                <select name="jenis" required class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="seminar_proposal" @selected($preselect && $preselect->jenis === 'seminar_proposal')>Seminar Proposal</option>
                    <option value="seminar_hasil" @selected($preselect && $preselect->jenis === 'seminar_hasil')>Seminar Hasil</option>
                    <option value="seminar_kp" @selected($preselect && $preselect->jenis === 'seminar_kp')>Seminar KP</option>
                    <option value="sidang_akhir" @selected($preselect && $preselect->jenis === 'sidang_akhir')>Sidang Akhir</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Tanggal</label>
                <input type="date" name="tanggal" required
                    value="{{ $preselect && $preselect->tanggal ? $preselect->tanggal->format('Y-m-d') : now()->format('Y-m-d') }}"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Hasil</label>
                <select name="hasil" class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <option value="">—</option>
                    <option value="lulus">Lulus</option>
                    <option value="lulus_revisi">Lulus + Revisi</option>
                    <option value="mengulang">Mengulang</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-text-secondary mb-1">Penguji <span class="text-status-danger">*</span></label>
                <input type="text" name="penguji_name" required list="dosen-penguji-list" placeholder="Nama penguji (bisa di luar sistem)"
                    class="w-full rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                <datalist id="dosen-penguji-list">
                    @foreach ($dosenList as $d) <option value="{{ $d->name }}"></option> @endforeach
                </datalist>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs text-text-secondary mb-1">Pembimbing yang diuji (maks 3, opsional)</p>
                <div class="grid sm:grid-cols-3 gap-2">
                    <input type="text" name="supervisor_1" placeholder="Pembimbing 1" class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <input type="text" name="supervisor_2" placeholder="Pembimbing 2" class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                    <input type="text" name="supervisor_3" placeholder="Pembimbing 3" class="rounded-md border border-border bg-bg-surface px-3 py-2 text-sm">
                </div>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="px-4 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">Simpan Riwayat</button>
            </div>
        </form>
    </div>

    {{-- ===== Daftar riwayat ===== --}}
    @if ($sidangs->isEmpty())
        <div class="px-4 py-8 rounded-lg bg-bg-surface border border-border text-center text-text-secondary"> Belum ada
            riwayat sidang. </div>
    @else
        <div class="bg-bg-surface rounded-xl border border-border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-text-secondary border-b border-border">
                        <th class="py-3 px-4">Mahasiswa</th>
                        <th class="py-3 px-4">Pembimbing (diuji)</th>
                        <th class="py-3 px-4 table-col-jenis">Jenis</th>
                        <th class="py-3 px-4 table-col-tanggal">Tanggal</th>
                        <th class="py-3 px-4">Hasil</th>
                        <th class="py-3 px-4">Nilai</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sidangs as $s)
                        @php
                            $filled = $s->grades->whereNotNull('filled_at')->count();
                            $total = $s->grades->count();
                        @endphp
                        <tr class="border-b border-border">
                            <td class="py-3 px-4">{{ $s->mahasiswa_name ?? $s->mahasiswaTa?->mahasiswa?->name }}</td>
                            <td class="py-3 px-4 text-xs">{{ $s->supervisor_names ? implode(', ', $s->supervisor_names) : '—' }}</td>
                            <td class="py-3 px-4 table-col-jenis">{{ $s->jenisLabel() }}</td>
                            <td class="py-3 px-4 table-col-tanggal">{{ $s->tanggal?->format('d M Y') }}</td>
                            <td class="py-3 px-4"> <span
                                    class="badge {{ $s->hasil === 'lulus' ? 'badge-success' : '' }} {{ $s->hasil === 'lulus_revisi' ? 'badge-pending' : '' }} {{ $s->hasil === 'mengulang' ? 'badge-danger' : '' }}">
                                    {{ $s->hasilLabel() }} </span> </td>
                            <td class="py-3 px-4 text-xs">
                                @if ($total > 0)
                                    <span>{{ $filled }}/{{ $total }} dinilai</span>
                                    @if ($s->nilaiFinal())
                                        <span class="text-brand"> · rerata {{ $s->nilaiFinal() }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route('dosen-sidang.destroy', $s) }}"
                                    onsubmit="return confirm('Hapus riwayat ini?')"> @csrf @method('DELETE') <button
                                        class="text-status-danger hover:underline text-xs">Hapus</button> </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
