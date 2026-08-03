@extends('layouts.app')

@section('title', 'Jadwalkan Bimbingan')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-heading font-bold text-2xl text-text-primary">Jadwalkan Bimbingan</h1>
            <p class="text-sm text-text-secondary mt-0.5">Pilih dosen untuk membuka link jadwal bimbingan mereka</p>
        </div>
    </div>

    @if ($dosen->isEmpty())
        <div class="px-4 py-6 rounded-card bg-status-pending/10 text-status-pending border border-status-pending/20 flex items-start gap-2.5">
            <span class="material-symbols-outlined icon-md mt-0.5">info</span>
            <div>
                <p class="font-semibold">Belum tersedia link bimbingan</p>
                <p class="text-sm text-text-secondary mt-1">Saat ini belum ada dosen yang mengisi link bimbingan. Silakan kontak langsung dosen yang Anda tuju melalui Chat atau WhatsApp, atau hubungi administrasi untuk informasi lebih lanjut.</p>
            </div>
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($dosen as $d)
                <a href="{{ $d->jadwal_bimbingan_url }}" target="_blank" rel="noopener"
                    class="card p-5 flex items-start gap-4 hover:border-brand/50 transition-colors">
                    <div
                        class="h-14 w-14 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
                        @if ($d->photoUrl())
                            <img src="{{ $d->photoUrl() }}" class="h-full w-full object-cover" alt="Foto {{ $d->name }}">
                        @else
                            {{ $d->initials() }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="font-semibold text-text-primary truncate">{{ $d->name }}</h2>
                        <p class="text-xs text-text-secondary truncate">{{ $d->email }}</p>
                        @if ($d->identifier)
                            <p class="text-xs text-text-secondary">{{ $d->identifier }}</p>
                        @endif
                        <span
                            class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand/10 text-brand text-xs font-medium">
                            <span class="material-symbols-outlined icon-sm">calendar_month</span>
                            Buka Jadwalkan Bimbingan
                        </span>
                    </div>
                    <span class="material-symbols-outlined icon-md text-text-secondary mt-1 flex-shrink-0">open_in_new</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection