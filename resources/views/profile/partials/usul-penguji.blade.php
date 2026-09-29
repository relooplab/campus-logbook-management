@php
    // Permintaan untuk program ini.
    $reqPending = $pendingRequests->firstWhere('mahasiswa_ta_id', $program->id);
    $reqHistory = $historyRequests->where('mahasiswa_ta_id', $program->id)->first();
    $pengujiPenuh = $program->penguji_1_id && $program->penguji_2_id;
@endphp

<div class="mt-5 border-t border-border pt-4">
    <h3 class="text-sm font-semibold">Usulkan Dosen Penguji</h3>
    <p class="mb-3 mt-1 text-xs text-text-secondary">Perlu persetujuan semua dosen terkait sebelum ditetapkan.</p>
    @if ($reqPending)
        <div class="rounded-xl bg-status-pending/10 border border-status-pending/30 px-4 py-3 text-sm">
            <p class="font-semibold text-text-primary">Permintaan penguji menunggu persetujuan</p>
            <p class="text-text-secondary mt-0.5">
                Diusulkan: <span class="font-medium text-text-primary">{{ $reqPending->proposedDosen?->name }}</span>
                ({{ $reqPending->proposed_role === 'penguji_1' ? 'Penguji 1' : 'Penguji 2' }}).
                Menunggu persetujuan semua dosen terkait.
            </p>
        </div>
    @elseif ($reqHistory && $reqHistory->status === 'rejected')
        <div class="rounded-xl bg-status-danger/10 border border-status-danger/30 px-4 py-3 text-sm mb-3">
            <p class="font-semibold text-text-primary">Permintaan penguji ditolak</p>
            <p class="text-text-secondary mt-0.5">Diusulkan: {{ $reqHistory->proposedDosen?->name }} — Alasan: {{ $reqHistory->alasan_tolak ?: '—' }}</p>
        </div>
    @endif

    @if (! $reqPending && ! $pengujiPenuh)
        <form method="POST" action="{{ route('profile.profil-akademik.penguji') }}">
            @csrf
            <input type="hidden" name="mahasiswa_ta_id" value="{{ $program->id }}">
            <div class="flex flex-col sm:flex-row gap-2 items-end">
                <div class="min-w-0 flex-1 w-full">
                    <label for="proposed-dosen-{{ $program->id }}" class="sr-only">Pilih dosen penguji untuk {{ $program->jenisLabel() }}</label>
                    <select id="proposed-dosen-{{ $program->id }}" name="proposed_dosen_id" required class="w-full min-w-0 rounded-xl border border-border bg-bg-surface px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/40">
                        <option value="">— Pilih dosen —</option>
                        @foreach ($dosenList as $dosen)
                            <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-primary px-4 py-2.5 text-sm font-medium whitespace-nowrap focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">Usulkan Penguji</button>
            </div>
        </form>
    @elseif ($pengujiPenuh && ! $reqPending)
        <p class="text-xs text-text-secondary">Penguji 1 & 2 sudah terisi. Untuk mengganti, silakan hubungi pembimbing Anda.</p>
    @endif
</div>