@extends('layouts.app')

@section('title', 'Persetujuan Afiliasi Dosen')

@section('content')
<div class="max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Persetujuan Afiliasi Dosen</h1>
            <p class="text-sm text-text-secondary mt-0.5">Setujui / tolak dosen yang ingin bergabung ke institusi berlangganan Anda</p>
        </div>
    </div>

    @if ($pending->isEmpty())
        <div class="card p-6 text-sm text-text-secondary">Tidak ada permintaan afiliasi yang menunggu persetujuan Anda.</div>
    @else
        <div class="space-y-4">
            @foreach ($pending as $req)
                <div class="card p-5 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-semibold">{{ $req->dosen?->name }}</span>
                            <span class="text-xs text-text-secondary ml-2">{{ $req->dosen?->email }}</span>
                        </div>
                        <span class="text-xs text-text-secondary">
                            Diajukan: {{ $req->requested_at?->format('d M Y, H:i') }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-border bg-bg-surface p-3 text-sm text-text-secondary">
                        🔗 {{ $req->university?->name }}
                        @if ($req->prodi)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand ml-2">{{ $req->prodi->name }}</span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('affiliation-approval.reject', [$req->user_id, $req->university_id]) }}" class="space-y-2">
                        @csrf
                        <div class="flex flex-wrap items-end gap-2">
                            <input type="text" name="alasan" required placeholder="Alasan penolakan (wajib bila menolak)"
                                class="flex-1 min-w-[200px] rounded-xl border border-border bg-bg-surface px-3.5 py-2 text-sm">
                            <button type="submit" class="px-3 py-2 rounded-xl border border-status-danger/30 text-status-danger text-sm font-medium hover:bg-status-danger/10">Tolak</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('affiliation-approval.approve', [$req->user_id, $req->university_id]) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl bg-brand hover:bg-brand-hover text-[#0b1420] text-sm font-semibold">Setujui</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
