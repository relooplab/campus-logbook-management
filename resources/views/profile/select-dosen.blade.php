@extends('layouts.app')

@section('title', 'Pilih Dosen')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Pilih Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Pilih dosen pembimbing dan penguji untuk program Anda</p>
        </div>
        <a href="{{ route('profile.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">← Profil</a>
    </div>

    @if ($affiliation)
        <div class="px-4 py-3 rounded-xl bg-bg-panel border border-border text-sm">
            <span class="text-text-secondary">Menampilkan dosen dari:</span>
            <span class="font-medium text-text-primary">{{ $affiliation->name }}</span>
        </div>
    @endif

    @if ($dosenList->isEmpty())
        <div class="card p-6 text-sm">
            <p class="font-semibold text-text-primary mb-1">Belum ada dosen terdaftar dari {{ $affiliation?->name ?? 'perguruan tinggi Anda' }}</p>
            <p class="text-text-secondary">Pilih perguruan tinggi lain di halaman profil, atau hubungi admin institusi untuk mendaftarkan dosen.</p>
            <a href="{{ route('profile.index') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Atur Afiliasi di Profil</a>
        </div>
    @else
    <div class="card p-6">
        <form method="POST" action="{{ route('profile.store-dosen') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-text-secondary mb-1">Jenis Program</label>
                <select name="jenis" id="program-jenis" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="ta">{{ $jenisLabelTa }}</option>
                    <option value="kp">{{ $jenisLabelKp }}</option>
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Fase/Milestone Saat Ini <span class="text-status-danger">*</span></label>
                <select name="fase" id="program-fase" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih fase —</option>
                    @foreach ($faseLabelsTa as $key => $label)
                        <option value="{{ $key }}" data-jenis="ta">{{ $label }}</option>
                    @endforeach
                    @foreach ($faseLabelsKp as $key => $label)
                        <option value="{{ $key }}" data-jenis="kp">{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-text-secondary mt-1">Pilih fase yang sedang Anda jalani saat ini.</p>
                @error('fase') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div id="kp-members-wrap" class="hidden">
                <label class="block text-xs text-text-secondary mb-1">Anggota Kelompok <span class="text-text-secondary">(opsional, Ctrl+klik untuk pilih banyak)</span></label>
                <select name="member_ids[]" multiple size="4" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    @foreach ($memberCandidates as $cand)
                        <option value="{{ $cand->id }}">{{ $cand->name }} ({{ $cand->nim }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-text-secondary mt-1">Ajak teman Anda bergabung dalam kelompok KP yang sama. Semua anggota berbagi satu logbook &amp; bimbingan — tidak perlu submit berkas masing-masing.</p>
                @error('member_ids.*') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Pembimbing 1 <span class="text-text-secondary">(opsional)</span></label>
                <select name="pembimbing_1_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
                @error('pembimbing_1_id') <p class="text-status-danger text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Pembimbing 2 <span class="text-text-secondary">(opsional)</span></label>
                <select name="pembimbing_2_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Penguji 1 <span class="text-text-secondary">(opsional)</span></label>
                <select name="penguji_1_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-text-secondary mb-1">Penguji 2 <span class="text-text-secondary">(opsional)</span></label>
                <select name="penguji_2_id" class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                    <option value="">— Pilih dosen —</option>
                    @foreach ($dosenList as $dosen)
                        <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90">Kirim Permintaan</button>
                <a href="{{ route('profile.index') }}" class="px-4 py-2 rounded-xl bg-bg-hover text-text-primary text-sm font-medium hover:bg-border">Batal</a>
            </div>
        </form>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Toggle dropdown fase sesuai jenis program (TA/KP).
    (function () {
        var jenisSelect = document.getElementById('program-jenis');
        var faseSelect = document.getElementById('program-fase');
        if (!jenisSelect || !faseSelect) return;

        function sync() {
            var jenis = jenisSelect.value;
            // Tampilkan hanya opsi fase yang sesuai jenis.
            Array.from(faseSelect.options).forEach(function (opt) {
                if (opt.value === '') return;
                opt.hidden = opt.dataset.jenis !== jenis;
            });
            // Reset pilihan jika fase tidak cocok dengan jenis.
            if (faseSelect.selectedOptions[0] && faseSelect.selectedOptions[0].dataset.jenis !== jenis) {
                faseSelect.value = '';
            }
            // Tampilkan pemilih anggota kelompok hanya untuk KP.
            var membersWrap = document.getElementById('kp-members-wrap');
            if (membersWrap) membersWrap.classList.toggle('hidden', jenis !== 'kp');
        }

        jenisSelect.addEventListener('change', sync);
        sync();
    })();

    // Sembunyikan dosen yang sudah dipilih di field lain (cegah duplikat).
    (function () {
        var dosenSelects = Array.from(document.querySelectorAll('[name^="pembimbing_"], [name^="penguji_"]'));
        if (!dosenSelects.length) return;

        function sync() {
            var taken = [];
            dosenSelects.forEach(function (s) { if (s.value) taken.push(s.value); });
            dosenSelects.forEach(function (s) {
                Array.from(s.options).forEach(function (opt) {
                    if (opt.value === '') return; // placeholder
                    var usedElsewhere = taken.indexOf(opt.value) !== -1 && opt.value !== s.value;
                    opt.hidden = usedElsewhere;
                });
            });
        }

        dosenSelects.forEach(function (s) { s.addEventListener('change', sync); });
        sync();
    })();
</script>
@endsection
