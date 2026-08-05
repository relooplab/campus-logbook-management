@extends("layouts.app") @section("title", "Profil " . $profile->name) @section("content")
<div class="max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-bold">Profil</h1> <a href="{{ url()->previous() }}"
            class="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">← Kembali</a>
    </div>
    <div class="bg-bg-surface rounded-xl border border-border p-6">
        <div class="flex items-center gap-4">
            <div
                class="h-20 w-20 rounded-full overflow-hidden bg-brand text-white flex items-center justify-center text-2xl font-bold flex-shrink-0">
                @if ($profile->photoUrl())
                    <img src="{{ $profile->photoUrl() }}" class="h-full w-full object-cover" alt="Foto profil">
                @else
                    {{ $profile->initials() }}
                @endif
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="font-semibold text-lg">{{ $profile->name }}</h2>
                    @if ($profile->lastActiveStatus() === 'online')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-status-success/10 text-status-success">
                            <span class="w-2 h-2 rounded-full bg-status-success animate-pulse"></span> Online
                        </span>
                    @elseif ($profile->lastActiveStatus() === 'offline')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-bg-panel text-text-secondary">
                            <span class="w-2 h-2 rounded-full bg-text-secondary/50"></span> Offline
                        </span>
                    @endif
                </div>
                <p class="text-sm text-text-secondary">{{ $profile->email }}</p>
                @if ($profile->identifier)
                    <p class="text-xs text-text-secondary">{{ $profile->identifier }}</p>
                @endif
                @if ($profile->nidn)
                    <p class="text-xs text-text-secondary">NIDN: {{ $profile->nidn }}</p>
                @endif
                @php $profileUniv = $profile->primaryUniversity(); @endphp
                @if ($profileUniv)
                    <p class="text-xs text-text-secondary mt-0.5">
                        <span class="material-symbols-outlined icon-sm align-text-bottom">account_balance</span>
                        {{ $profileUniv->name }}
                    </p>
                @endif
                <p class="text-xs text-text-secondary mt-0.5">
                    <span class="material-symbols-outlined icon-sm align-text-bottom">schedule</span>
                    Terakhir aktif: {{ $profile->lastActiveLabel() }}
                </p>
                @foreach ($profile->roles->whereNotIn('name', ['admin', 'system_admin']) as $r)
                    <span
                        class="inline-block px-2 py-0.5 rounded-full text-xs bg-bg-panel mt-1 mr-1">{{ ucfirst($r->name) }}</span>
                @endforeach
            </div>
        </div> {{-- Kontak --}} <div class="mt-6 grid sm:grid-cols-2 gap-3 text-sm">
            @if ($profile->whatsapp)
                <a href="{{ $profile->whatsappUrl() }}" target="_blank" rel="noopener"
                    class="px-3 py-2 rounded-md bg-bg-panel hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm align-text-bottom">chat</span> WhatsApp:
                    {{ $profile->whatsapp }}
                    @if ($profile->bimbingan_via_whatsapp)
                        <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand"><span class="material-symbols-outlined icon-xs align-text-bottom">calendar_month</span> Bimbingan</span>
                    @endif
                </a>
                @endif @if ($profile->telegram)
                    <span class="px-3 py-2 rounded-md bg-bg-panel"><span class="material-symbols-outlined icon-sm align-text-bottom">send</span> Telegram: {{ $profile->telegram }}
                        @if ($profile->bimbingan_via_telegram)
                            <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-brand/10 text-brand"><span class="material-symbols-outlined icon-xs align-text-bottom">calendar_month</span> Bimbingan</span>
                        @endif
                    </span>
                    @endif @if ($profile->linkedin)
                        <a href="{{ $profile->linkedin }}" target="_blank" rel="noopener"
                            class="px-3 py-2 rounded-md bg-bg-panel hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm align-text-bottom">link</span>
                            LinkedIn: {{ $profile->linkedin }}</a>
                        @endif @if ($profile->mahasiswaTa)
                            <span class="px-3 py-2 rounded-md bg-bg-panel"> TA:
                                {{ \Illuminate\Support\Str::limit($profile->mahasiswaTa->judul_ta, 60) }} </span>
                        @endif
        </div> {{-- Tautan akademik dosen --}} @if ($profile->isDosen() && ($profile->google_scholar || $profile->orcid || $profile->sinta || $profile->researchgate || $profile->jadwal_bimbingan_url))
            <div class="mt-6 pt-4 border-t border-border">
                <h3 class="text-sm font-semibold mb-3">Tautan Akademik</h3>
                <div class="flex flex-wrap gap-2 text-sm">
                    @if ($profile->google_scholar)
                        <a href="{{ $profile->google_scholar }}" target="_blank" rel="noopener"
                            class="px-3 py-1.5 rounded-md bg-brand/10 text-brand hover:bg-brand/10 hover:bg-brand-light"><span class="material-symbols-outlined icon-sm align-text-bottom">school</span>
                            Google Scholar</a>
                        @endif @if ($profile->orcid)
                            <a href="https://orcid.org/{{ $profile->orcid }}" target="_blank" rel="noopener"
                                class="px-3 py-1.5 rounded-md bg-brand/10 text-brand hover:bg-brand/10"><span class="material-symbols-outlined icon-sm align-text-bottom">badge</span>
                                ORCID</a>
                            @endif @if ($profile->sinta)
                                <a href="https://sinta.kemdikbud.go.id/authors/profile/{{ $profile->sinta }}"
                                    target="_blank" rel="noopener"
                                    class="px-3 py-1.5 rounded-md bg-status-pending/10 text-status-pending"><span class="material-symbols-outlined icon-sm align-text-bottom">bar_chart</span>
                                    SINTA</a>
                                 @endif @if ($profile->researchgate)
                                     <a href="{{ $profile->researchgate }}" target="_blank" rel="noopener"
                                         class="px-3 py-1.5 rounded-md bg-bg-hover hover:bg-bg-hover hover:bg-bg-hover"><span class="material-symbols-outlined icon-sm align-text-bottom">science</span>
                                         ResearchGate</a>
                                 @endif @if ($profile->jadwal_bimbingan_url)
                                     <a href="{{ $profile->jadwal_bimbingan_url }}" target="_blank" rel="noopener"
                                         class="px-3 py-1.5 rounded-md bg-brand/10 text-brand"><span class="material-symbols-outlined icon-sm align-text-bottom">calendar_month</span>
                                         Jadwalkan Bimbingan</a>
                                 @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
