@php
    // Permintaan untuk program ini.
    $reqPending = $pendingRequests->firstWhere('mahasiswa_ta_id', $program->id);
    $reqHistory = $historyRequests->where('mahasiswa_ta_id', $program->id)->first();
    $pengujiPenuh = $program->penguji_1_id && $program->penguji_2_id;
@endphp

<div class="mt-5 border-t border-border pt-4">
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
        <form method="POST" action="{{ route('profile.profil-akademik.penguji') }}" class="space-y-2">
            @csrf
            <input type="hidden" name="mahasiswa_ta_id" value="{{ $program->id }}">
            <div class="flex flex-col sm:flex-row gap-2 items-end">
                <div class="flex-1 w-full">
                    <label class="block text-xs text-text-secondary mb-1">Usulkan Dosen Penguji</label>
                    <select name="proposed_dosen_id" required class="w-full rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                        <option value="">— Pilih dosen —</option>
                        @foreach ($dosenList as $dosen)
                            <option value="{{ $dosen->id }}">{{ $dosen->name }} ({{ $dosen->nidn ?: '—' }})</option>
                        @endforeach
                    </select>
                </div>
                <button class="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-medium hover:opacity-90 whitespace-nowrap">Usulkan Penguji</button>
            </div>
            <p class="text-xs text-text-secondary">Perlu persetujuan semua dosen terkait sebelum ditetapkan.</p>
        </form>
    @elseif ($pengujiPenuh && ! $reqPending)
        <p class="text-xs text-text-secondary">Penguji 1 & 2 sudah terisi. Untuk mengganti, silakan hubungi pembimbing Anda.</p>
    @endif
</div>